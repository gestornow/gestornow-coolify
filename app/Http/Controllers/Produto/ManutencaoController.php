<?php

namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
use App\Domain\Produto\Models\Manutencao;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\Patrimonio;
use App\Services\ManutencaoEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ManutencaoController extends Controller
{
    private $manutencaoEstoqueService;

    public function __construct(ManutencaoEstoqueService $manutencaoEstoqueService)
    {
        $this->manutencaoEstoqueService = $manutencaoEstoqueService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $query = Manutencao::query()
            ->where('id_empresa', $idEmpresa)
            ->with(['produto', 'patrimonio']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['tipo'])) {
            $query->where('tipo', $filters['tipo']);
        }

        if (!empty($filters['id_produto'])) {
            $query->where('id_produto', $filters['id_produto']);
        }

        if (!empty($filters['busca'])) {
            $query->where(function($q) use ($filters) {
                $q->where('descricao', 'like', "%{$filters['busca']}%")
                  ->orWhere('responsavel', 'like', "%{$filters['busca']}%");
            });
        }

        $query->orderBy('data_entrada', 'desc');
        $manutencoes = $query->paginate(50);

        $stats = [
            'total' => Manutencao::where('id_empresa', $idEmpresa)->count(),
            'em_andamento' => Manutencao::where('id_empresa', $idEmpresa)
                ->whereIn('status', ['em_andamento', 'pendente'])
                ->count(),
            'concluidas' => Manutencao::where('id_empresa', $idEmpresa)->where('status', 'concluida')->count(),
        ];

        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->get();

        return view('produtos.manutencoes.index', compact('manutencoes', 'filters', 'stats', 'produtos'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            if (!$idEmpresa) {
                throw new \Exception('Empresa não identificada.');
            }

            $validated = $request->validate([
                'id_produto' => [
                    'required',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'id_patrimonio' => [
                    'nullable',
                    'integer',
                    Rule::exists('patrimonios', 'id_patrimonio')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'quantidade' => ['nullable', 'integer', 'min:1'],
                'data_manutencao' => ['required', 'date'],
                'data_previsao' => ['nullable', 'date'],
                'hora_manutencao' => ['nullable', 'date_format:H:i'],
                'hora_previsao' => ['nullable', 'date_format:H:i'],
                'tipo' => ['required', 'string', 'max:100'],
                'descricao' => ['nullable', 'string'],
                'status' => ['nullable', 'in:em_andamento,concluida'],
                'responsavel' => ['nullable', 'string', 'max:255'],
                'valor' => ['nullable'],
                'observacoes' => ['nullable', 'string'],
            ]);

            $colunasManutencao = $this->obterColunasManutencao();
            $temColunaQuantidade = in_array('quantidade', $colunasManutencao, true);

            $produto = Produto::where('id_produto', $request->input('id_produto'))
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();

            $produtoTemPatrimonios = Patrimonio::where('id_empresa', $idEmpresa)
                ->where('id_produto', $produto->id_produto)
                ->exists();

            $data = $this->montarDadosManutencao($validated); // Segurança: monta payload apenas com dados validados.
            $data['id_empresa'] = $idEmpresa;
            $data['status'] = $this->normalizarStatusManutencao($data['status'] ?? 'em_andamento');

            if (!empty($data['id_patrimonio'])) {
                $patrimonioSelecionado = Patrimonio::where('id_patrimonio', $data['id_patrimonio'])
                    ->where('id_empresa', $idEmpresa)
                    ->first();

                if (!$patrimonioSelecionado || (int) $patrimonioSelecionado->id_produto !== (int) $produto->id_produto) {
                    throw new \Exception('Patrimônio inválido para a empresa/produto informado.');
                }
            }

            if (!$temColunaQuantidade) {
                unset($data['quantidade']);
            }

            if ($produtoTemPatrimonios) {
                if (empty($data['id_patrimonio'])) {
                    throw new \Exception('Selecione o patrimônio para registrar manutenção em produtos controlados por patrimônio.');
                }

                if ($temColunaQuantidade) {
                    $data['quantidade'] = 1;
                }
            } else {
                $data['id_patrimonio'] = null;

                if ($temColunaQuantidade) {
                    $quantidade = (int) ($data['quantidade'] ?? 0);
                    if ($quantidade < 1) {
                        throw new \Exception('Informe uma quantidade válida para a manutenção.');
                    }

                    $data['quantidade'] = $quantidade;
                }
            }

            $data = $this->normalizeNumericFields($data);

            if (!empty($data['id_patrimonio']) && $data['status'] === 'em_andamento') {
                $jaExisteEmAndamento = Manutencao::where('id_empresa', $idEmpresa)
                    ->where('id_patrimonio', $data['id_patrimonio'])
                    ->whereIn('status', ['em_andamento', 'pendente'])
                    ->exists();

                if ($jaExisteEmAndamento) {
                    throw new \Exception('Este patrimônio já possui uma manutenção em andamento. Conclua a manutenção atual antes de criar outra.');
                }
            }

            $manutencao = Manutencao::create($data);

            $this->manutencaoEstoqueService->sincronizarAoSalvar($manutencao, null);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Manutenção registrada com sucesso.',
                    'id_manutencao' => $manutencao->id_manutencao
                ]);
            }

            // Redirecionar para a página do produto se veio de lá
            if ($request->has('redirect_to')) {
                if ($request->redirect_to === 'produto') {
                    return redirect()->route('produtos.show', $request->id_produto)->with('success', 'Manutenção registrada com sucesso.');
                }
                if ($request->redirect_to === 'produto_edit') {
                    return redirect()->route('produtos.edit', $request->id_produto)->with('success', 'Manutenção registrada com sucesso.');
                }
            }

            return redirect()->route('manutencoes.index')->with('success', 'Manutenção registrada com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERRO DE VALIDAÇÃO MANUTENÇÃO ===', ['errors' => $e->errors()]);
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERRO AO CRIAR MANUTENÇÃO ===', ['erro' => $e->getMessage()]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $manutencao = Manutencao::where('id_manutencao', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$manutencao) {
                throw new \Exception('Manutenção não encontrada.');
            }

            $validated = $request->validate([
                'id_produto' => [
                    'sometimes',
                    'required',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'id_patrimonio' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    Rule::exists('patrimonios', 'id_patrimonio')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'quantidade' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'data_manutencao' => ['sometimes', 'required', 'date'],
                'data_previsao' => ['sometimes', 'nullable', 'date'],
                'hora_manutencao' => ['sometimes', 'nullable', 'date_format:H:i'],
                'hora_previsao' => ['sometimes', 'nullable', 'date_format:H:i'],
                'tipo' => ['sometimes', 'required', 'string', 'max:100'],
                'descricao' => ['sometimes', 'nullable', 'string'],
                'status' => ['sometimes', 'nullable', 'in:em_andamento,concluida'],
                'responsavel' => ['sometimes', 'nullable', 'string', 'max:255'],
                'valor' => ['sometimes', 'nullable'],
                'observacoes' => ['sometimes', 'nullable', 'string'],
            ]);

            $data = $this->montarDadosManutencao($validated); // Segurança: monta payload apenas com dados validados.
            $novoStatus = $this->normalizarStatusManutencao($data['status'] ?? $manutencao->status);
            $statusAnterior = $manutencao->status;
            $data['status'] = $novoStatus;

            $colunasManutencao = $this->obterColunasManutencao();
            $temColunaQuantidade = in_array('quantidade', $colunasManutencao, true);

            if (!$temColunaQuantidade) {
                unset($data['quantidade']);
            }

            $idProdutoReferencia = (int) ($data['id_produto'] ?? $manutencao->id_produto);

            $produto = Produto::where('id_produto', $idProdutoReferencia)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();

            $produtoTemPatrimonios = Patrimonio::where('id_empresa', $idEmpresa)
                ->where('id_produto', $produto->id_produto)
                ->exists();

            if ($produtoTemPatrimonios) {
                if (empty($data['id_patrimonio']) && empty($manutencao->id_patrimonio)) {
                    throw new \Exception('Selecione o patrimônio para registrar manutenção em produtos controlados por patrimônio.');
                }

                if ($temColunaQuantidade) {
                    $data['quantidade'] = 1;
                }
            } else {
                $data['id_patrimonio'] = null;

                if ($temColunaQuantidade) {
                    $quantidade = (int) ($data['quantidade'] ?? $manutencao->quantidade ?? 0);
                    if ($quantidade < 1) {
                        throw new \Exception('Informe uma quantidade válida para a manutenção.');
                    }

                    $data['quantidade'] = $quantidade;
                }
            }

            $data = $this->normalizeNumericFields($data);

            $idPatrimonioParaValidar = $data['id_patrimonio'] ?? $manutencao->id_patrimonio;
            if (!empty($idPatrimonioParaValidar)) {
                $patrimonioSelecionado = Patrimonio::where('id_patrimonio', $idPatrimonioParaValidar)
                    ->where('id_empresa', $idEmpresa)
                    ->first();

                if (!$patrimonioSelecionado || (int) $patrimonioSelecionado->id_produto !== (int) $produto->id_produto) {
                    throw new \Exception('Patrimônio inválido para a empresa/produto informado.');
                }
            }

            if (!empty($idPatrimonioParaValidar) && $novoStatus === 'em_andamento') {
                $jaExisteEmAndamento = Manutencao::where('id_empresa', $idEmpresa)
                    ->where('id_patrimonio', $idPatrimonioParaValidar)
                    ->whereIn('status', ['em_andamento', 'pendente'])
                    ->where('id_manutencao', '!=', $manutencao->id_manutencao)
                    ->exists();

                if ($jaExisteEmAndamento) {
                    throw new \Exception('Este patrimônio já possui uma manutenção em andamento. Conclua a manutenção atual antes de criar outra.');
                }
            }

            $manutencao->update($data);

            $manutencao->refresh();
            $this->manutencaoEstoqueService->sincronizarAoSalvar($manutencao, $statusAnterior);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Manutenção atualizada com sucesso.'
                ]);
            }

            // Redirecionar para a página do produto se veio de lá
            if ($request->has('redirect_to')) {
                if ($request->redirect_to === 'produto') {
                    return redirect()->route('produtos.show', $request->id_produto)->with('success', 'Manutenção atualizada com sucesso.');
                }
                if ($request->redirect_to === 'produto_edit') {
                    return redirect()->route('produtos.edit', $request->id_produto)->with('success', 'Manutenção atualizada com sucesso.');
                }
            }

            return redirect()->route('manutencoes.index')->with('success', 'Manutenção atualizada com sucesso.');

        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $manutencao = Manutencao::where('id_manutencao', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$manutencao) {
                throw new \Exception('Manutenção não encontrada.');
            }

            $manutencao->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Manutenção excluída com sucesso.'
                ]);
            }

            return redirect()->route('manutencoes.index')->with('success', 'Manutenção excluída com sucesso.');

        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Listar manutenções de um produto específico
     */
    public function porProduto(Request $request, $idProduto)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $manutencoes = Manutencao::where('id_empresa', $idEmpresa)
            ->where('id_produto', $idProduto)
            ->orderBy('data_manutencao', 'desc')
            ->get();

        return response()->json($manutencoes);
    }

    /**
     * Normalizar campos numéricos
     */
    private function normalizeNumericFields($data)
    {
        if (isset($data['valor']) && is_string($data['valor'])) {
            $data['valor'] = str_replace(['R$', ' ', '.'], '', $data['valor']);
            $data['valor'] = str_replace(',', '.', $data['valor']);
            $data['valor'] = floatval($data['valor']);
        }
        return $data;
    }

    private function normalizarStatusManutencao(?string $status): string
    {
        $status = trim((string) $status);

        if ($status === 'pendente') {
            return 'em_andamento';
        }

        return $status === 'concluida' ? 'concluida' : 'em_andamento';
    }

    private function montarDadosManutencao(array $dados): array
    {
        $permitidos = [
            'id_produto',
            'id_patrimonio',
            'quantidade',
            'data_manutencao',
            'tipo',
            'descricao',
            'status',
            'responsavel',
            'valor',
            'observacoes',
        ];

        $colunas = $this->obterColunasManutencao();

        foreach (['data_previsao', 'hora_manutencao', 'hora_previsao'] as $colunaOpcional) {
            if (in_array($colunaOpcional, $colunas, true)) {
                $permitidos[] = $colunaOpcional;
            }
        }

        return collect($dados)
            ->only($permitidos)
            ->toArray();
    }

    private function obterColunasManutencao(): array
    {
        static $colunas = null;

        if ($colunas === null) {
            $colunas = Schema::getColumnListing('manutencoes');
        }

        return $colunas;
    }
}
