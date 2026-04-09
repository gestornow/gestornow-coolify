<?php

namespace App\Http\Controllers\Financeiro;

use App\ActivityLog\ActionLogger;
use App\Facades\Perm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financeiro\StoreContasAReceberRequest;
use App\Http\Traits\FilterableContas;
use App\Domain\Auth\Models\Empresa;
use App\Models\ContasAReceber;
use App\Models\FluxoCaixa;
use App\Models\PagamentoContaReceber;
use App\Models\RegistroAtividade;
use App\Services\ContasAReceber\ContasAReceberService;
use App\Services\ContasAReceberStatsService;
use App\Services\ParcelamentoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ContasAReceberController extends Controller
{
    use FilterableContas;

    protected ContasAReceberStatsService $statsService;
    protected ParcelamentoService $parcelamentoService;
    protected ContasAReceberService $contasAReceberService;

    /**
     * Constructor com dependency injection
     */
    public function __construct(
        ContasAReceberStatsService $statsService,
        ParcelamentoService $parcelamentoService,
        ContasAReceberService $contasAReceberService
    ) {
        $this->statsService = $statsService;
        $this->parcelamentoService = $parcelamentoService;
        $this->contasAReceberService = $contasAReceberService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        // Definir mês/ano padrão (mês atual)
        $mesFiltro = $request->filled('mes_filtro') ? $request->mes_filtro : now()->format('Y-m');
        
        // Build query
        $query = ContasAReceber::with(['cliente', 'fornecedor', 'categoria', 'formaPagamento'])
            ->where('id_empresa', $id_empresa);

        // Aplicar filtro de mês
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $this->filterByMonth($query, $mesFiltro);
        }

        // Aplicar outros filtros usando trait
        $this->applyFilters($query, $request);

        // Aplicar ordenação
        $orderParams = $this->getOrderParams($request);
        $query->orderBy($orderParams['column'], $orderParams['direction']);

        // Paginate - permitir seleção de quantidade por página
        $perPage = $request->filled('per_page') ? (int)$request->per_page : 20;
        $perPage = in_array($perPage, [10, 20, 50, 100]) ? $perPage : 20;
        $contas = $query->paginate($perPage)->appends($request->except('page'));

        // Get statistics usando service
        $stats = $this->statsService->getStats($id_empresa, $mesFiltro);

        // Get clientes for filter
        $clientes = DB::table('clientes')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        // Get formas de pagamento e bancos para modal de baixa
        $formasPagamento = DB::table('forma_pagamento')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $bancos = DB::table('bancos')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();

        return view('financeiro.contas-a-receber.index', compact('contas', 'stats', 'clientes', 'mesFiltro', 'formasPagamento', 'bancos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.criar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        // Get data for form selects
        $clientes = DB::table('clientes')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $fornecedores = DB::table('fornecedores')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $bancos = DB::table('bancos')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();

        $categorias = DB::table('categoria_contas')
            ->where('id_empresa', $id_empresa)
            ->where('tipo', 'receita')
            ->orderBy('nome')
            ->get();

        $formasPagamento = DB::table('forma_pagamento')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        return view('financeiro.contas-a-receber.create', compact(
            'clientes',
            'fornecedores',
            'bancos',
            'categorias',
            'formasPagamento'
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreContasAReceberRequest $request)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.criar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $redirectRoute = $request->input('redirect_to') === 'fluxo-caixa'
            ? 'financeiro.fluxo-caixa.index'
            : 'financeiro.contas-a-receber.index';
        
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $validated['id_empresa'] = $id_empresa;
            
            // Set default values
            if (!isset($validated['valor_pago'])) {
                $validated['valor_pago'] = 0;
            }
            if (!isset($validated['juros'])) {
                $validated['juros'] = 0;
            }
            if (!isset($validated['multa'])) {
                $validated['multa'] = 0;
            }
            if (!isset($validated['desconto'])) {
                $validated['desconto'] = 0;
            }

            $tipoLancamento = $request->input('tipo_lancamento', 'unico');

            // Verificar se é parcelamento customizado
            if ($tipoLancamento === 'parcelado_customizado' && $request->has('parcelas_custom')) {
                $parcelasCustom = $request->input('parcelas_custom');
                $idParcelamento = (string) \Illuminate\Support\Str::uuid();
                $numeroParcela = 1;
                $contas = [];
                
                foreach ($parcelasCustom as $parcelaData) {
                    $dadosParcela = $validated;
                    $dadosParcela['descricao'] = $parcelaData['descricao'];
                    $dadosParcela['data_vencimento'] = $parcelaData['data_vencimento'];
                    $dadosParcela['valor_total'] = $parcelaData['valor'];
                    $dadosParcela['numero_parcela'] = $numeroParcela;
                    $dadosParcela['total_parcelas'] = count($parcelasCustom);
                    $dadosParcela['id_parcelamento'] = $idParcelamento;
                    $dadosParcela['status'] = 'pendente';
                    
                    $conta = ContasAReceber::create($dadosParcela);
                    $contas[] = $conta;
                    $numeroParcela++;
                }
                
                DB::commit();
                return redirect()
                    ->route($redirectRoute)
                    ->with('success', count($contas) . ' parcelas customizadas criadas com sucesso!');
                    
            } elseif ($tipoLancamento === 'parcelado' && $request->filled('total_parcelas')) {
                // Determinar intervalo entre parcelas
                $intervaloDias = 30; // Padrão mensal
                if ($request->filled('intervalo_parcelas')) {
                    if ($request->intervalo_parcelas === 'custom' && $request->filled('intervalo_custom')) {
                        $intervaloDias = (int) $request->intervalo_custom;
                    } else {
                        $intervaloDias = (int) $request->intervalo_parcelas;
                    }
                }
                
                $contas = $this->parcelamentoService->criarParcelas($validated, $request->total_parcelas, 'receber', $intervaloDias);
                
                DB::commit();
                return redirect()
                    ->route($redirectRoute)
                    ->with('success', count($contas) . ' parcelas criadas com sucesso!');
                    
            } elseif ($tipoLancamento === 'recorrente' && $request->filled('tipo_recorrencia')) {
                $quantidadeRecorrencias = $request->input('quantidade_recorrencias', 12);
                $contas = $this->parcelamentoService->criarRecorrencias(
                    $validated, 
                    $request->tipo_recorrencia, 
                    $quantidadeRecorrencias,
                    'receber'
                );
                
                DB::commit();
                return redirect()
                    ->route($redirectRoute)
                    ->with('success', count($contas) . ' contas recorrentes criadas com sucesso!');
                    
            } else {
                // Lançamento único
                $isFluxoCaixaComBaixa = $request->input('redirect_to') === 'fluxo-caixa'
                    && ($validated['status'] ?? null) === 'pago'
                    && !empty($validated['data_pagamento']);

                $dadosConta = $validated;
                if ($isFluxoCaixaComBaixa) {
                    $dadosConta['status'] = 'pendente';
                    $dadosConta['valor_pago'] = 0;
                    $dadosConta['data_pagamento'] = null;
                }

                $conta = ContasAReceber::create($dadosConta);

                if ($isFluxoCaixaComBaixa) {
                    try {
                        $valorBaixa = (float) ($validated['valor_pago'] ?? 0);
                        if ($valorBaixa <= 0) {
                            $valorBaixa = (float) ($validated['valor_total'] ?? 0);
                        }

                        $this->contasAReceberService->registrarRecebimento(
                            conta: $conta,
                            valorRecebido: $valorBaixa,
                            idFormaPagamento: $validated['id_forma_pagamento'] ?? null,
                            idBanco: $validated['id_bancos'] ?? null,
                            dataRecebimento: $validated['data_pagamento'],
                            observacoes: $validated['observacoes'] ?? null
                        );

                        $lancamentoFluxo = FluxoCaixa::where('id_fluxo', function ($query) use ($conta) {
                            $query->from('pagamentos_contas_receber')
                                ->select('id_fluxo_caixa')
                                ->where('id_conta_receber', $conta->id_contas)
                                ->orderByDesc('id_pagamento')
                                ->limit(1);
                        })
                            ->where('id_empresa', $id_empresa)
                            ->first();

                        if ($lancamentoFluxo) {
                            ActionLogger::log($lancamentoFluxo, 'lancamento_entrada');
                        }
                    } catch (\Exception $e) {
                        DB::rollBack();
                        Log::error('Erro ao registrar recebimento pelo fluxo de caixa', [
                            'error' => $e->getMessage(),
                            'conta_id' => $conta->id_contas ?? null,
                            'valor' => $valorBaixa ?? null
                        ]);
                        return redirect()
                            ->back()
                            ->withInput()
                            ->with('error', 'Erro ao registrar recebimento: ' . $e->getMessage());
                    }
                }

                // If status is 'pago' and there's a payment date, create cash flow entry
                if (!$isFluxoCaixaComBaixa && $conta->status === 'pago' && $conta->data_pagamento) {
                    DB::table('fluxo_caixa')->insert([
                        'id_empresa' => $id_empresa,
                        'tipo' => 'entrada',
                        'descricao' => 'Recebimento: ' . $conta->descricao,
                        'valor' => $conta->valor_pago ?: $conta->valor_total,
                        'data_movimentacao' => $conta->data_pagamento,
                        'id_conta_receber' => $conta->id_contas,
                        'id_bancos' => $conta->id_bancos,
                        'id_categoria_fluxo' => $conta->id_categoria_contas,
                        'id_forma_pagamento' => $conta->id_forma_pagamento,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::commit();
                return redirect()
                    ->route($redirectRoute)
                    ->with('success', 'Conta a receber criada com sucesso!');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar conta a receber: ' . $e->getMessage());
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Erro ao criar conta a receber. Por favor, tente novamente.']);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $conta = ContasAReceber::with(['cliente', 'fornecedor', 'categoria', 'formaPagamento', 'banco'])
            ->where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        return view('financeiro.contas-a-receber.show', compact('conta'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.editar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $conta = ContasAReceber::where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        // Get data for form selects
        $clientes = DB::table('clientes')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $fornecedores = DB::table('fornecedores')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        $bancos = DB::table('bancos')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();

        $categorias = DB::table('categoria_contas')
            ->where('id_empresa', $id_empresa)
            ->where('tipo', 'receita')
            ->orderBy('nome')
            ->get();

        $formasPagamento = DB::table('forma_pagamento')
            ->where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();

        return view('financeiro.contas-a-receber.edit', compact(
            'conta',
            'clientes',
            'fornecedores',
            'bancos',
            'categorias',
            'formasPagamento'
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.editar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $conta = ContasAReceber::where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        $validated = $request->validate([
            'descricao' => 'required|string|max:255',
            'id_clientes' => ['nullable', Rule::exists('clientes', 'id_clientes')->where(fn ($query) => $query->where('id_empresa', $id_empresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_fornecedores' => ['nullable', Rule::exists('fornecedores', 'id_fornecedores')->where(fn ($query) => $query->where('id_empresa', $id_empresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_bancos' => ['nullable', Rule::exists('bancos', 'id_bancos')->where(fn ($query) => $query->where('id_empresa', $id_empresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_categoria_contas' => ['nullable', Rule::exists('categoria_contas', 'id_categoria_contas')->where(fn ($query) => $query->where('id_empresa', $id_empresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'id_forma_pagamento' => ['nullable', Rule::exists('forma_pagamento', 'id_forma_pagamento')->where(fn ($query) => $query->where('id_empresa', $id_empresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
            'documento' => 'nullable|string|max:100',
            'boleto' => 'nullable|string|max:100',
            'valor_total' => 'required|numeric|min:0',
            'valor_pago' => 'nullable|numeric|min:0',
            'juros' => 'nullable|numeric|min:0',
            'multa' => 'nullable|numeric|min:0',
            'desconto' => 'nullable|numeric|min:0',
            'data_emissao' => 'nullable|date',
            'data_vencimento' => 'required|date',
            'data_pagamento' => 'nullable|date',
            'status' => 'required|in:pendente,pago,vencido,parcelado,cancelado',
            'observacoes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $statusAnterior = $conta->status;

            $conta->update($validated);

            // If status changed to 'pago' and there's a payment date, create/update cash flow entry
            if ($statusAnterior !== 'pago' && $conta->status === 'pago' && $conta->data_pagamento) {
                // Check if flow entry already exists
                $fluxoExistente = DB::table('fluxo_caixa')
                    ->where('id_conta_receber', $conta->id_contas)
                    ->first();

                if (!$fluxoExistente) {
                    DB::table('fluxo_caixa')->insert([
                        'id_empresa' => $id_empresa,
                        'tipo' => 'entrada',
                        'descricao' => 'Recebimento: ' . $conta->descricao,
                        'valor' => $conta->valor_pago ?: $conta->valor_total,
                        'data_movimentacao' => $conta->data_pagamento,
                        'id_conta_receber' => $conta->id_contas,
                        'id_bancos' => $conta->id_bancos,
                        'id_categoria_fluxo' => $conta->id_categoria_contas,
                        'id_forma_pagamento' => $conta->id_forma_pagamento,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('financeiro.contas-a-receber.index')
                ->with('success', 'Conta a receber atualizada com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar conta a receber: ' . $e->getMessage());
            
            return back()
                ->withInput()
                ->withErrors(['error' => 'Erro ao atualizar conta a receber. Por favor, tente novamente.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.excluir'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $conta = ContasAReceber::where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        try {
            DB::beginTransaction();

            // Delete related cash flow entries
            DB::table('fluxo_caixa')
                ->where('id_conta_receber', $conta->id_contas)
                ->delete();

            $conta->delete();

            DB::commit();

            return redirect()
                ->route('financeiro.contas-a-receber.index')
                ->with('success', 'Conta a receber excluída com sucesso!');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir conta a receber: ' . $e->getMessage());
            
            return back()
                ->withErrors(['error' => 'Erro ao excluir conta a receber. Por favor, tente novamente.']);
        }
    }

    /**
     * Visualizar parcelas de um parcelamento
     */
    public function parcelas(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $conta = ContasAReceber::where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        if (!$conta->id_parcelamento) {
            return redirect()
                ->route('financeiro.contas-a-receber.index')
                ->with('error', 'Esta conta não possui parcelamento.');
        }

        $parcelas = ContasAReceber::where('id_parcelamento', $conta->id_parcelamento)
            ->where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->with(['cliente', 'categoria', 'banco'])
            ->orderBy('numero_parcela')
            ->get();

        return view('financeiro.contas-a-receber.parcelas', compact('conta', 'parcelas'));
    }

    /**
     * Buscar parcelas via AJAX
     */
    public function parcelasData(string $idParcelamento)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $parcelas = ContasAReceber::where('id_parcelamento', $idParcelamento)
            ->where('id_empresa', $id_empresa)
            ->orderBy('numero_parcela')
            ->get(['id_contas', 'descricao', 'data_vencimento', 'valor_total', 'valor_pago', 'status', 'numero_parcela', 'total_parcelas']);

        return response()->json([
            'success' => true,
            'parcelas' => $parcelas
        ]);
    }

    /**
     * Visualizar recorrências
     */
    public function recorrencias(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $conta = ContasAReceber::where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        if (!$conta->id_recorrencia) {
            return redirect()
                ->route('financeiro.contas-a-receber.index')
                ->with('error', 'Esta conta não possui recorrências.');
        }

        $recorrencias = ContasAReceber::where('id_recorrencia', $conta->id_recorrencia)
            ->where('id_empresa', $id_empresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->with(['cliente', 'categoria', 'banco'])
            ->orderBy('data_vencimento')
            ->get();

        return view('financeiro.contas-a-receber.recorrencias', compact('conta', 'recorrencias'));
    }

    /**
     * Buscar recorrências via AJAX
     */
    public function recorrenciasData(string $idRecorrencia)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $recorrencias = ContasAReceber::where('id_recorrencia', $idRecorrencia)
            ->where('id_empresa', $id_empresa)
            ->orderBy('data_vencimento')
            ->get(['id_contas', 'descricao', 'data_vencimento', 'valor_total', 'status', 'tipo_recorrencia']);

        return response()->json([
            'success' => true,
            'recorrencias' => $recorrencias
        ]);
    }

    /**
     * Deletar múltiplas contas a receber
     */
    public function excluirMultiplos(Request $request)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.excluir'), 403);

        try {
            $ids = $request->input('ids', []);

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhuma conta selecionada.'
                ], 400);
            }

            $id_empresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            Log::info('=== DELETANDO MÚLTIPLAS CONTAS A RECEBER ===', [
                'qtd' => count($ids),
                'ids' => $ids,
                'id_empresa' => $id_empresa
            ]);

            // Deletar apenas contas da empresa do usuário logado
            $deletedCount = ContasAReceber::whereIn('id_contas', $ids)
                ->where('id_empresa', $id_empresa)
                ->delete();

            Log::info('=== CONTAS A RECEBER DELETADAS COM SUCESSO ===', [
                'qtd_deletados' => $deletedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "{$deletedCount} conta(s) deletada(s) com sucesso."
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO DELETAR MÚLTIPLAS CONTAS A RECEBER ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar contas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dar baixa em uma conta a receber
     */
    public function darBaixa(Request $request, string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.baixa'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $conta = ContasAReceber::where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                ->findOrFail($id);

            // Validar dados
            $validated = $request->validate([
                'data_pagamento' => 'required|date',
                'valor_pago' => 'required|numeric|min:0.01',
                'id_forma_pagamento' => ['nullable', Rule::exists('forma_pagamento', 'id_forma_pagamento')->where(fn ($query) => $query->where('id_empresa', $idEmpresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
                'id_bancos' => ['nullable', Rule::exists('bancos', 'id_bancos')->where(fn ($query) => $query->where('id_empresa', $idEmpresa))], // Segurança: restringe FK à empresa da sessão para bloquear IDOR.
                'observacoes' => 'nullable|string|max:1000',
            ]);

            $valorPagoAntes = (float) ($conta->valor_pago ?? 0);
            $valorTotalConta = (float) ($conta->valor_total ?? 0);
            $valorRecebidoParcela = (float) $validated['valor_pago'];
            $valorRestanteAntes = max(0, $valorTotalConta - $valorPagoAntes);

            Cache::put('audit_silenciar_updated_' . class_basename($conta) . '_' . $conta->getKey(), true, now()->addSeconds(20));

            // Registrar recebimento através do service
            $recebimento = $this->contasAReceberService->registrarRecebimento(
                conta: $conta,
                valorRecebido: $validated['valor_pago'],
                idFormaPagamento: $validated['id_forma_pagamento'],
                idBanco: $validated['id_bancos'],
                dataRecebimento: $validated['data_pagamento'],
                observacoes: $validated['observacoes']
            );

            // Recarregar conta para obter valores atualizados
            $conta->refresh();
            $recebimento->loadMissing(['formaPagamento', 'banco']);

            $valorPagoDepois = (float) ($conta->valor_pago ?? 0);
            $valorRestanteDepois = (float) ($conta->valorRestante ?? max(0, $valorTotalConta - $valorPagoDepois));
            $ehBaixaTotal = $valorRestanteDepois <= 0.00001;

            ActionLogger::logDireto(
                model: $conta,
                evento: $ehBaixaTotal ? 'baixa' : 'baixa_parcial',
                acao: $ehBaixaTotal ? 'conta_receber.baixa' : 'conta_receber.baixa_parcial',
                descricao: $ehBaixaTotal
                    ? sprintf(
                        'Baixa total na conta #%d — Recebido: R$ %s',
                        $conta->id_contas,
                        number_format($valorRecebidoParcela, 2, ',', '.')
                    )
                    : sprintf(
                        'Baixa parcial na conta #%d — Recebido: R$ %s — Saldo: R$ %s%s',
                        $conta->id_contas,
                        number_format($valorRecebidoParcela, 2, ',', '.'),
                        number_format($valorRestanteDepois, 2, ',', '.'),
                        (!is_null($conta->numero_parcela) && !is_null($conta->total_parcelas))
                            ? sprintf(' — Parcela %d/%d', (int) $conta->numero_parcela, (int) $conta->total_parcelas)
                            : ''
                    ),
                entidadeTipo: 'conta_receber',
                entidadeLabel: 'Conta a receber #' . $conta->id_contas . ' — ' . ($conta->descricao ?? 'Sem descrição'),
                valor: $valorRecebidoParcela,
                contexto: [
                    'evento' => $ehBaixaTotal ? 'baixa' : 'baixa_parcial',
                    'tipo_baixa' => $ehBaixaTotal ? 'total' : 'parcial',
                    'id_recebimento' => $recebimento->id_pagamento,
                    'data_pagamento' => $recebimento->data_pagamento?->format('Y-m-d') ?? $validated['data_pagamento'],
                    'valor_recebido_parcela' => $valorRecebidoParcela,
                    'valor_pago_antes' => $valorPagoAntes,
                    'valor_pago_depois' => $valorPagoDepois,
                    'saldo_restante_antes' => $valorRestanteAntes,
                    'saldo_restante_depois' => $valorRestanteDepois,
                    'forma_pagamento' => $recebimento->formaPagamento->nome ?? '-',
                    'banco' => $recebimento->banco->nome_banco ?? '-',
                    'observacoes' => $validated['observacoes'] ?? null,
                    'numero_parcela' => $conta->numero_parcela,
                    'total_parcelas' => $conta->total_parcelas,
                ],
                antes: [
                    'valor_pago' => $valorPagoAntes,
                    'saldo_restante' => $valorRestanteAntes,
                    'status' => $valorPagoAntes > 0 ? 'parcialmente_recebido' : 'pendente',
                ],
                depois: [
                    'valor_pago' => $valorPagoDepois,
                    'saldo_restante' => $valorRestanteDepois,
                    'status' => $ehBaixaTotal ? 'pago' : 'parcialmente_recebido',
                ],
                icone: $ehBaixaTotal ? 'check-circle' : 'check-square',
                cor: $ehBaixaTotal ? 'verde-escuro' : 'ciano',
                tags: $ehBaixaTotal
                    ? ['contas_receber', 'financeiro', 'recebimento']
                    : ['contas_receber', 'financeiro', 'recebimento', 'parcial']
            );

            $mensagem = $conta->status === 'pago' 
                ? 'Recebimento realizado com sucesso! Conta quitada.' 
                : 'Recebimento parcial registrado com sucesso!';

            return response()->json([
                'success' => true,
                'message' => $mensagem,
                'status' => $conta->status,
                'valor_pago_total' => $conta->valor_pago,
                'valor_restante' => $conta->valorRestante
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao dar baixa em conta: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao dar baixa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover baixa de uma conta a receber e reabri-la
     */
    public function removerBaixa(Request $request, string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.baixa'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $conta = ContasAReceber::where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                ->find($id);
            
            if (!$conta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conta n\u00e3o encontrada.'
                ], 404);
            }

            // Validar request
            if (!$request->has('id_fluxo_caixa') || !is_numeric($request->input('id_fluxo_caixa'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'ID do fluxo de caixa \u00e9 obrigat\u00f3rio e deve ser num\u00e9rico.'
                ], 422);
            }

            $idFluxoCaixa = (int) $request->input('id_fluxo_caixa');

            DB::beginTransaction();

            // Deletar registro de recebimento primeiro (FK)
            $deletedRecebimento = DB::table('pagamentos_contas_receber')
                ->where('id_fluxo_caixa', $idFluxoCaixa)
                ->where('id_conta_receber', $conta->id_contas)
                ->delete();

            // Deletar entrada do fluxo de caixa
            $deletedFluxo = DB::table('fluxo_caixa')
                ->where('id_fluxo', $idFluxoCaixa)
                ->where('id_conta_receber', $conta->id_contas)
                ->delete();

            // Reabrir a conta
            $conta->update([
                'status' => 'pendente',
                'valor_pago' => 0,
                'data_pagamento' => null,
            ]);

            ActionLogger::log($conta, 'estorno');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Baixa removida com sucesso! Conta reaberta.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao remover baixa (Contas a Receber)', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao remover baixa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o histórico de recebimentos de uma conta
     */
    public function historicoRecebimentos(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $conta = ContasAReceber::where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                ->findOrFail($id);

            // Buscar histórico através do service
            $historico = $this->contasAReceberService->buscarHistoricoRecebimentos($conta);

            return response()->json([
                'success' => true,
                ...$historico
            ]);

        } catch (\Exception $e) {
            Log::error('Erro ao buscar histórico de recebimentos: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o log de atividades da conta a receber
     */
    public function logsAtividades(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $conta = ContasAReceber::where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                ->findOrFail($id);

            if ((int) $conta->id_empresa !== (int) $idEmpresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para visualizar os logs desta conta.'
                ], 403);
            }

            $logs = RegistroAtividade::query()
                ->where('id_empresa', $idEmpresa)
                ->where('entidade_tipo', 'conta_receber')
                ->where('entidade_id', $conta->id_contas)
                ->orderByDesc('ocorrido_em')
                ->limit(50)
                ->get([
                    'id_registro',
                    'acao',
                    'descricao',
                    'nome_responsavel',
                    'email_responsavel',
                    'contexto',
                    'antes',
                    'depois',
                    'icone',
                    'cor',
                    'tags',
                    'ocorrido_em',
                ]);

            return response()->json([
                'success' => true,
                'conta' => [
                    'id_contas' => $conta->id_contas,
                    'descricao' => $conta->descricao,
                ],
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar log de atividades da conta a receber', [
                'id_conta' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar log de atividades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar recibo em PDF para conta a receber
     */
    public function recibo(string $id)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar'), 403);

        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $conta = ContasAReceber::with([
            'cliente',
            'fornecedor',
            'categoria',
            'formaPagamento',
            'banco',
            'pagamentos.formaPagamento',
            'pagamentos.banco'
        ])
            ->where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        if ((float) $conta->valor_pago <= 0) {
            abort(422, 'Não há recebimentos registrados para esta conta.');
        }

        $empresa = Empresa::where('id_empresa', $idEmpresa)->first(); // Segurança: mantém carregamento da empresa no escopo da sessão.
        $pagamentos = $conta->pagamentos;

        $pdf = Pdf::loadView('financeiro.recibos.conta', [
            'tipo' => 'receber',
            'conta' => $conta,
            'empresa' => $empresa,
            'pagamentos' => $pagamentos,
            'titulo' => 'Recibo de Recebimento',
        ])->setPaper('a4');

        return $pdf->stream('recibo-conta-receber-' . $conta->id_contas . '.pdf');
    }

    /**
     * Excluir um recebimento e recalcular a conta
     */
    public function excluirRecebimento(string $idConta, string $idRecebimento)
    {
        abort_unless(Perm::pode(auth()->user(), 'financeiro.contas-receber.excluir'), 403);

        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $conta = ContasAReceber::where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                ->findOrFail($idConta);

            $recebimento = PagamentoContaReceber::where('id_pagamento', $idRecebimento)
                ->where('id_conta_receber', $idConta)
                ->firstOrFail();

            // Excluir através do service
            $this->contasAReceberService->excluirRecebimento($recebimento);

            // Recarregar conta
            $conta->refresh();
            ActionLogger::log($conta, 'estorno');

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Recebimento excluído com sucesso.',
                    'valor_pago_total' => $conta->valor_pago,
                    'valor_restante' => $conta->valorRestante,
                    'status' => $conta->status
                ]);
            }

            return redirect()->route('financeiro.contas-a-receber.index')->with('success', 'Recebimento excluído com sucesso.');

        } catch (\Exception $e) {
            Log::error('Erro ao excluir recebimento: ' . $e->getMessage());

            if (request()->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao excluir recebimento: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->route('financeiro.contas-a-receber.index')->with('error', 'Erro ao excluir recebimento.');
        }
    }
}
