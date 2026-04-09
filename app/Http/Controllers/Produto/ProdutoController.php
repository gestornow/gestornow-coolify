<?php

namespace App\Http\Controllers\Produto;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Http\Traits\VerificaLimite;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\Patrimonio;
use App\Domain\Produto\Models\Manutencao;
use App\Domain\Produto\Models\MovimentacaoEstoque;
use App\Domain\Produto\Models\ProdutoHistorico;
use App\Domain\Produto\Models\TabelaPreco;
use App\Domain\Locacao\Models\LocacaoProduto;
use App\Domain\Auth\Models\Empresa;
use App\Models\RegistroAtividade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ProdutoController extends Controller
{
    use VerificaLimite;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        // Query base filtrando por id_empresa da sessão
        $query = Produto::query()
            ->where('id_empresa', $idEmpresa)
            ->withCount([
                'patrimonios',
                'patrimonios as patrimonios_disponiveis_count' => function ($q) {
                    $q->where('status', 'Ativo')
                        ->where('status_locacao', 'Disponivel');
                },
            ]);

        // Filtro por status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtro por busca (nome, código, número de série)
        if (!empty($filters['busca'])) {
            $query->buscar($filters['busca']);
        }

        if (!empty($filters['codigo'])) {
            $query->where('codigo', 'like', '%' . trim((string) $filters['codigo']) . '%');
        }

        // Ordenação
        $query->orderBy('nome', 'asc');

        // Paginação
        $produtos = $query->paginate(50);

        // Estatísticas
        $stats = [
            'total' => Produto::where('id_empresa', $idEmpresa)->count(),
            'ativos' => Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->count(),
            'inativos' => Produto::where('id_empresa', $idEmpresa)->where('status', 'inativo')->count(),
        ];

        return view('produtos.index', compact('produtos', 'filters', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('produtos.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Verificar limite de produtos antes de criar
            $limiteResponse = $this->verificarLimiteProduto();
            if ($limiteResponse) {
                if ($request->wantsJson() || $request->ajax()) {
                    return $limiteResponse;
                }
                return redirect()->back()
                    ->with('error', 'Limite de produtos atingido. Faça upgrade do seu plano.')
                    ->withInput();
            }

            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'codigo' => ['nullable', 'string', 'max:255'],
                'numero_serie' => ['nullable', 'string', 'max:255'],
                'descricao' => ['nullable', 'string'],
                'detalhes' => ['nullable', 'string'],
                'preco_custo' => ['nullable'],
                'preco_venda' => ['nullable'],
                'preco_locacao' => ['nullable'],
                'estoque_total' => ['nullable', 'integer', 'min:0'],
                'quantidade' => ['nullable', 'integer', 'min:0'],
                'altura' => ['nullable'],
                'largura' => ['nullable'],
                'profundidade' => ['nullable'],
                'peso' => ['nullable'],
                'status' => ['nullable', 'in:ativo,inativo'],
                'id_marca' => ['nullable', 'integer'],
                'id_grupo' => ['nullable', 'integer'],
                'id_tipo' => ['nullable', 'integer'],
                'unidade_medida_id' => ['nullable', 'integer'],
                'id_modelo' => ['nullable', 'integer'],
                'hex_color' => ['nullable', 'string', 'max:20'],
                'foto_url' => ['nullable', 'string', 'max:500'],
                'foto_filename' => ['nullable', 'string', 'max:255'],
            ], [
                'nome.required' => 'O nome do produto é obrigatório.',
                'nome.max' => 'O nome do produto deve ter no máximo 255 caracteres.',
            ]);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            if (!$idEmpresa) {
                throw new \Exception('Empresa não identificada. Por favor, selecione uma filial.');
            }

            $data = $validated; // Segurança: persiste apenas campos validados para evitar mass assignment.
            $data['id_empresa'] = $idEmpresa;

            // Normalizar campos numéricos
            $data = $this->normalizeNumericFields($data);

            // Criar produto
            $produto = Produto::create($data);

            \Log::info('=== PRODUTO CRIADO COM SUCESSO ===', [
                'id_produto' => $produto->id_produto,
                'nome' => $produto->nome,
                'id_empresa' => $produto->id_empresa
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produto cadastrado com sucesso.',
                    'id_produto' => $produto->id_produto
                ]);
            }

            return redirect()->route('produtos.index')->with('success', 'Produto cadastrado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('=== ERRO DE VALIDAÇÃO AO CRIAR PRODUTO ===', $e->errors());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO CRIAR PRODUTO ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
     * Display the specified resource.
     */
    public function show($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $produto = Produto::where('id_produto', $id)
            ->where('id_empresa', $idEmpresa)
            ->with(['patrimonios', 'manutencoes', 'tabelasPreco', 'acessorios'])
            ->first();

        if (!$produto) {
            abort(404);
        }

        // Buscar acessórios disponíveis que ainda não estão vinculados ao produto
        $acessoriosVinculadosIds = $produto->acessorios->pluck('id_acessorio')->toArray();
        $acessoriosDisponiveis = \App\Domain\Produto\Models\Acessorio::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->whereNotIn('id_acessorio', $acessoriosVinculadosIds)
            ->orderBy('nome')
            ->get();

        return view('produtos.show', compact('produto', 'acessoriosDisponiveis'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $produto = Produto::where('id_produto', $id)
            ->where('id_empresa', $idEmpresa)
            ->with(['patrimonios', 'tabelasPreco', 'manutencoes'])
            ->first();

        if (!$produto) {
            abort(404);
        }

        return view('produtos.edit', compact('produto'));
    }

    /**
     * Retorna informações financeiras do produto (JSON)
     */
    public function informacoesProduto($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->with(['patrimonios'])
                ->firstOrFail();

            $dados = $this->obterDadosInfoProduto($produto, $idEmpresa);

            return response()->json([
                'success' => true,
                'produto' => [
                    'id_produto' => $produto->id_produto,
                    'nome' => $produto->nome,
                    'codigo' => $produto->codigo,
                ],
                'info_financeira' => $dados['infoFinanceiraProduto'],
                'info_patrimonios' => $dados['infoPatrimonios'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar informações do produto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Exporta informações do produto em PDF
     */
    public function informacoesProdutoPdf($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $produto = Produto::where('id_produto', $id)
            ->where('id_empresa', $idEmpresa)
            ->with(['patrimonios'])
            ->firstOrFail();

        $empresa = Empresa::where('id_empresa', $idEmpresa)->first();

        $dados = $this->obterDadosInfoProduto($produto, $idEmpresa);

        $pdf = Pdf::loadView('produtos.info-pdf', [
            'produto' => $produto,
            'empresa' => $empresa,
            'infoFinanceiraProduto' => $dados['infoFinanceiraProduto'],
            'infoPatrimonios' => $dados['infoPatrimonios'],
            'dataGeracao' => now(),
        ]);

        return $pdf->download('informacoes-produto-' . $produto->id_produto . '.pdf');
    }

    /**
     * Exporta informações do produto em Excel (CSV)
     */
    public function informacoesProdutoExcel($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $produto = Produto::where('id_produto', $id)
            ->where('id_empresa', $idEmpresa)
            ->with(['patrimonios'])
            ->firstOrFail();

        $empresa = Empresa::where('id_empresa', $idEmpresa)->first();
        $nomeEmpresa = $empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome_empresa ?? 'GestorNow';

        $dados = $this->obterDadosInfoProduto($produto, $idEmpresa);
        $infoFinanceira = $dados['infoFinanceiraProduto'];
        $infoPatrimonios = $dados['infoPatrimonios'];

        $formataValor = function ($valor) {
            return number_format((float) $valor, 2, ',', '.');
        };

        $nomeArquivo = 'informacoes-produto-' . $produto->id_produto . '.csv';

        return response()->streamDownload(function () use ($produto, $nomeEmpresa, $infoFinanceira, $infoPatrimonios, $formataValor) {
            $output = fopen('php://output', 'w');

            echo "\xEF\xBB\xBF";

            fputcsv($output, ['Empresa', $nomeEmpresa], ';');
            fputcsv($output, ['Produto', $produto->nome], ';');
            fputcsv($output, ['Código', $produto->codigo ?? '-'], ';');
            fputcsv($output, ['Data de geração', now()->format('d/m/Y H:i')], ';');
            fputcsv($output, [], ';');

            fputcsv($output, ['Resumo Financeiro', 'Valor'], ';');
            fputcsv($output, ['Receita em Locações', $formataValor($infoFinanceira['receita'] ?? 0)], ';');
            fputcsv($output, ['Gasto com Manutenções', $formataValor($infoFinanceira['gasto_manutencao'] ?? 0)], ';');
            fputcsv($output, ['Lucratividade do Produto', $formataValor($infoFinanceira['lucro'] ?? 0)], ';');
            fputcsv($output, ['Itens de locação contabilizados', (int) ($infoFinanceira['qtd_locacoes_rentaveis'] ?? 0)], ';');
            fputcsv($output, ['Manutenções contabilizadas', (int) ($infoFinanceira['qtd_manutencoes'] ?? 0)], ';');
            fputcsv($output, [], ';');

            fputcsv($output, ['Rentabilidade por Patrimônio'], ';');
            fputcsv($output, ['Patrimônio', 'Status', 'Receita', 'Gasto Manutenção', 'Lucro'], ';');

            foreach ($infoPatrimonios as $item) {
                fputcsv($output, [
                    $item['numero_serie'] ?? '-',
                    $item['status_locacao'] ?? '-',
                    $formataValor($item['receita'] ?? 0),
                    $formataValor($item['gasto_manutencao'] ?? 0),
                    $formataValor($item['lucro'] ?? 0),
                ], ';');
            }

            fclose($output);
        }, $nomeArquivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            // Se está atualizando apenas foto (foto_url ou foto_filename presente)
            $isOnlyPhotoUpdate = ($request->has('foto_url') || $request->has('foto_filename')) 
                                 && !$request->has('nome');

            if ($isOnlyPhotoUpdate) {
                // Atualização apenas da foto - sem validação de outros campos
                $data = $request->only(['foto_url', 'foto_filename']);
                $produto->update($data);

                \Log::info('=== FOTO DO PRODUTO ATUALIZADA ===', [
                    'id_produto' => $produto->id_produto,
                    'foto_url' => $data['foto_url'] ?? null
                ]);

                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Foto atualizada com sucesso.'
                    ]);
                }

                return redirect()->back()->with('success', 'Foto atualizada com sucesso.');
            }

            // Validação completa para atualização de dados
            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'codigo' => ['nullable', 'string', 'max:255'],
                'numero_serie' => ['nullable', 'string', 'max:255'],
                'descricao' => ['nullable', 'string'],
                'detalhes' => ['nullable', 'string'],
                'preco_custo' => ['nullable'],
                'preco_venda' => ['nullable'],
                'preco_locacao' => ['nullable'],
                'estoque_total' => ['nullable', 'integer', 'min:0'],
                'quantidade' => ['nullable', 'integer', 'min:0'],
                'altura' => ['nullable'],
                'largura' => ['nullable'],
                'profundidade' => ['nullable'],
                'peso' => ['nullable'],
                'status' => ['nullable', 'in:ativo,inativo'],
                'foto_url' => ['nullable', 'string', 'max:500'],
                'foto_filename' => ['nullable', 'string', 'max:255'],
                'id_marca' => ['nullable', 'integer'],
                'id_grupo' => ['nullable', 'integer'],
                'id_tipo' => ['nullable', 'integer'],
                'unidade_medida_id' => ['nullable', 'integer'],
                'id_modelo' => ['nullable', 'integer'],
                'hex_color' => ['nullable', 'string', 'max:20'],
            ], [
                'nome.required' => 'O nome do produto é obrigatório.',
            ]);

            $data = $validated; // Segurança: persiste apenas campos validados para evitar mass assignment.
            
            // Normalizar campos numéricos
            $data = $this->normalizeNumericFields($data);

            $produto->update($data);

            \Log::info('=== PRODUTO ATUALIZADO COM SUCESSO ===', [
                'id_produto' => $produto->id_produto,
                'nome' => $produto->nome
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produto atualizado com sucesso.'
                ]);
            }

            return redirect()->route('produtos.index')->with('success', 'Produto atualizado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO ATUALIZAR PRODUTO ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $produto->delete();

            \Log::info('=== PRODUTO DELETADO COM SUCESSO ===', [
                'id_produto' => $id
            ]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produto deletado com sucesso.'
                ]);
            }

            return redirect()->route('produtos.index')->with('success', 'Produto deletado com sucesso.');

        } catch (\Exception $e) {
            \Log::error('=== ERRO AO DELETAR PRODUTO ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Retorna o log de atividades do produto
     */
    public function logsAtividades($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $escopo = (string) request()->query('escopo', 'todos');

            if (!in_array($escopo, ['todos', 'produto', 'patrimonios', 'tabela_precos'], true)) {
                $escopo = 'todos';
            }

            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado.'
                ], 404);
            }

            $idsPatrimonios = Patrimonio::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_produto', $produto->id_produto)
                ->pluck('id_patrimonio')
                ->all();

            $idsTabelasPreco = TabelaPreco::query()
                ->where('id_empresa', $idEmpresa)
                ->where('id_produto', $produto->id_produto)
                ->pluck('id_tabela')
                ->all();

            $tiposProduto = ['produto', 'Produto'];
            $tiposPatrimonio = ['patrimonio', 'Patrimonio', 'patrimonios'];
            $tiposTabelaPreco = ['tabela_preco', 'tabela_precos', 'TabelaPreco'];

            $contagens = [
                'produto' => RegistroAtividade::query()
                    ->where('id_empresa', $idEmpresa)
                    ->whereIn('entidade_tipo', $tiposProduto)
                    ->where('entidade_id', $produto->id_produto)
                    ->count(),
                'patrimonios' => empty($idsPatrimonios)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposPatrimonio)
                        ->whereIn('entidade_id', $idsPatrimonios)
                        ->count(),
                'tabela_precos' => empty($idsTabelasPreco)
                    ? 0
                    : RegistroAtividade::query()
                        ->where('id_empresa', $idEmpresa)
                        ->whereIn('entidade_tipo', $tiposTabelaPreco)
                        ->whereIn('entidade_id', $idsTabelasPreco)
                        ->count(),
            ];

            $contagens['todos'] = $contagens['produto'] + $contagens['patrimonios'] + $contagens['tabela_precos'];

            if ($escopo === 'patrimonios' && empty($idsPatrimonios)) {
                return response()->json([
                    'success' => true,
                    'produto' => [
                        'id_produto' => $produto->id_produto,
                        'nome' => $produto->nome,
                    ],
                    'escopo' => $escopo,
                    'contagens' => $contagens,
                    'logs' => [],
                ]);
            }

            if ($escopo === 'tabela_precos' && empty($idsTabelasPreco)) {
                return response()->json([
                    'success' => true,
                    'produto' => [
                        'id_produto' => $produto->id_produto,
                        'nome' => $produto->nome,
                    ],
                    'escopo' => $escopo,
                    'contagens' => $contagens,
                    'logs' => [],
                ]);
            }

            $logs = RegistroAtividade::query()
                ->where('id_empresa', $idEmpresa)
                ->where(function ($query) use (
                    $escopo,
                    $tiposProduto,
                    $tiposPatrimonio,
                    $tiposTabelaPreco,
                    $produto,
                    $idsPatrimonios,
                    $idsTabelasPreco
                ) {
                    if ($escopo === 'produto' || $escopo === 'todos') {
                        $query->orWhere(function ($sub) use ($tiposProduto, $produto) {
                            $sub->whereIn('entidade_tipo', $tiposProduto)
                                ->where('entidade_id', $produto->id_produto);
                        });
                    }

                    if (($escopo === 'patrimonios' || $escopo === 'todos') && !empty($idsPatrimonios)) {
                        $query->orWhere(function ($sub) use ($tiposPatrimonio, $idsPatrimonios) {
                            $sub->whereIn('entidade_tipo', $tiposPatrimonio)
                                ->whereIn('entidade_id', $idsPatrimonios);
                        });
                    }

                    if (($escopo === 'tabela_precos' || $escopo === 'todos') && !empty($idsTabelasPreco)) {
                        $query->orWhere(function ($sub) use ($tiposTabelaPreco, $idsTabelasPreco) {
                            $sub->whereIn('entidade_tipo', $tiposTabelaPreco)
                                ->whereIn('entidade_id', $idsTabelasPreco);
                        });
                    }
                })
                ->orderByDesc('ocorrido_em')
                ->limit(50)
                ->get([
                    'id_registro',
                    'acao',
                    'descricao',
                    'entidade_tipo',
                    'entidade_id',
                    'entidade_label',
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
                'produto' => [
                    'id_produto' => $produto->id_produto,
                    'nome' => $produto->nome,
                ],
                'escopo' => $escopo,
                'contagens' => $contagens,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao buscar log de atividades do produto', [
                'id_produto' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar log de atividades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir múltiplos produtos
     */
    public function excluirMultiplos(Request $request)
    {
        try {
            $request->validate([
                'ids' => 'required|array|min:1',
                'ids.*' => 'required|integer'
            ], [
                'ids.required' => 'Nenhum produto selecionado.',
                'ids.array' => 'Formato inválido.',
                'ids.min' => 'Selecione pelo menos um produto.',
            ]);

            $ids = $request->input('ids');
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $deletados = 0;
            $erros = [];

            foreach ($ids as $id) {
                try {
                    $produto = Produto::where('id_produto', $id)
                        ->where('id_empresa', $idEmpresa)
                        ->first();

                    if ($produto) {
                        $produto->delete();
                        $deletados++;
                    }
                } catch (\Exception $e) {
                    $erros[] = "ID {$id}: " . $e->getMessage();
                }
            }

            $mensagem = "{$deletados} produto(s) deletado(s) com sucesso.";
            if (count($erros) > 0) {
                $mensagem .= " Alguns erros ocorreram: " . implode('; ', $erros);
            }

            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => $deletados > 0,
                    'message' => $mensagem,
                    'deletados' => $deletados,
                    'erros' => $erros
                ]);
            }

            return redirect()->route('produtos.index')->with(
                $deletados > 0 ? 'success' : 'error',
                $mensagem
            );

        } catch (\Illuminate\Validation\ValidationException $e) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors());

        } catch (\Exception $e) {
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Normalizar campos numéricos (converter formato BR para EN)
     */
    private function normalizeNumericFields(array $data): array
    {
        $numericFields = [
            'preco', 'preco_reposicao', 'preco_custo', 'preco_venda', 'preco_locacao',
            'altura', 'largura', 'profundidade', 'peso'
        ];

        foreach ($numericFields as $field) {
            if (isset($data[$field])) {
                $raw = trim($data[$field]);
                if ($raw === '') {
                    $data[$field] = null;
                } else {
                    // Remove pontos de milhar e troca vírgula por ponto
                    $normalized = str_replace('.', '', $raw);
                    $normalized = str_replace(',', '.', $normalized);
                    $data[$field] = $normalized;
                }
            }
        }

        return $data;
    }

    /**
     * Obter histórico de movimentações de estoque
     */
    public function movimentacoesEstoque($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
            
            $movimentacoes = MovimentacaoEstoque::where('id_produto', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            $movimentacoesLocacao = ProdutoHistorico::where('id_empresa', $idEmpresa)
                ->where('id_produto', $id)
                ->whereIn('tipo_movimentacao', ['saida', 'retorno'])
                ->with(['locacao'])
                ->orderBy('data_movimentacao', 'desc')
                ->get();

            $movimentacoesManutencao = Manutencao::where('id_empresa', $idEmpresa)
                ->where('id_produto', $id)
                ->whereIn('status', ['em_andamento', 'pendente', 'concluida'])
                ->orderBy('updated_at', 'desc')
                ->get();

            $eventosSistema = [];

            foreach ($movimentacoesLocacao as $mov) {
                $dataMov = $mov->data_movimentacao ?? $mov->created_at;
                if (!$dataMov) {
                    continue;
                }

                $tipoMov = $mov->tipo_movimentacao === 'retorno' ? 'entrada' : 'saida';
                $referencia = $mov->locacao
                    ? ('Contrato #' . ($mov->locacao->numero_contrato ?? $mov->locacao->id_locacao))
                    : ($mov->motivo ?? 'Locação');

                $eventosSistema[] = [
                    'id_movimentacao' => 'hist-' . $mov->id_historico,
                    'tipo' => $tipoMov,
                    'quantidade' => (int) ($mov->quantidade ?? 0),
                    'estoque_anterior' => $mov->estoque_anterior,
                    'estoque_posterior' => $mov->estoque_novo,
                    'motivo' => $mov->motivo,
                    'observacoes' => $mov->observacoes,
                    'origem' => 'locacao',
                    'referencia' => $referencia,
                    'created_at_obj' => Carbon::parse($dataMov),
                ];
            }

            $colunasManutencao = Schema::getColumnListing('manutencoes');
            $temDataPrevisao = in_array('data_previsao', $colunasManutencao, true);
            $temHoraManutencao = in_array('hora_manutencao', $colunasManutencao, true);
            $temHoraPrevisao = in_array('hora_previsao', $colunasManutencao, true);

            $normalizarData = function ($valor, $padrao = null) {
                if ($valor instanceof \Carbon\CarbonInterface) {
                    return $valor->format('Y-m-d');
                }

                if ($valor instanceof \DateTimeInterface) {
                    return $valor->format('Y-m-d');
                }

                if (is_string($valor) && trim($valor) !== '') {
                    return Carbon::parse($valor)->format('Y-m-d');
                }

                return $padrao;
            };

            foreach ($movimentacoesManutencao as $manutencao) {
                if (empty($manutencao->data_manutencao)) {
                    continue;
                }

                $horaInicio = $temHoraManutencao && !empty($manutencao->hora_manutencao)
                    ? substr((string) $manutencao->hora_manutencao, 0, 8)
                    : '00:00:00';

                $dataInicioBase = $normalizarData($manutencao->data_manutencao);
                if (empty($dataInicioBase)) {
                    continue;
                }

                $dataInicio = Carbon::parse($dataInicioBase . ' ' . $horaInicio);
                $quantidade = (int) ($manutencao->id_patrimonio ? 1 : ($manutencao->quantidade ?? 1));
                $referenciaManutencao = 'Manutenção #' . $manutencao->id_manutencao;

                $eventosSistema[] = [
                    'id_movimentacao' => 'man-start-' . $manutencao->id_manutencao,
                    'tipo' => 'saida',
                    'quantidade' => $quantidade,
                    'estoque_anterior' => null,
                    'estoque_posterior' => null,
                    'motivo' => 'Início de manutenção',
                    'observacoes' => $manutencao->descricao,
                    'origem' => 'manutencao',
                    'referencia' => $referenciaManutencao,
                    'created_at_obj' => $dataInicio,
                ];

                if ($manutencao->status !== 'concluida') {
                    continue;
                }

                $horaFim = $temHoraPrevisao && !empty($manutencao->hora_previsao)
                    ? substr((string) $manutencao->hora_previsao, 0, 8)
                    : '23:59:59';

                $dataFimBruta = $temDataPrevisao && !empty($manutencao->data_previsao)
                    ? $normalizarData($manutencao->data_previsao)
                    : ($manutencao->updated_at ? $normalizarData($manutencao->updated_at) : null);

                if (empty($dataFimBruta)) {
                    continue;
                }

                $dataFim = Carbon::parse($dataFimBruta . ' ' . $horaFim);

                $eventosSistema[] = [
                    'id_movimentacao' => 'man-end-' . $manutencao->id_manutencao,
                    'tipo' => 'entrada',
                    'quantidade' => $quantidade,
                    'estoque_anterior' => null,
                    'estoque_posterior' => null,
                    'motivo' => 'Fim de manutenção',
                    'observacoes' => $manutencao->descricao,
                    'origem' => 'manutencao',
                    'referencia' => $referenciaManutencao,
                    'created_at_obj' => $dataFim,
                ];
            }

            $movimentacoesManuais = $movimentacoes->map(function ($m) {
                return [
                    'id_movimentacao' => $m->id_movimentacao,
                    'tipo' => $m->tipo,
                    'quantidade' => (int) $m->quantidade,
                    'estoque_anterior' => $m->estoque_anterior,
                    'estoque_posterior' => $m->estoque_posterior,
                    'motivo' => $m->motivo,
                    'observacoes' => $m->observacoes,
                    'origem' => 'manual',
                    'referencia' => 'Movimentação manual',
                    'created_at_obj' => $m->created_at,
                ];
            })->toArray();

            $movimentacoesUnificadas = collect(array_merge($movimentacoesManuais, $eventosSistema))
                ->sortByDesc(function ($item) {
                    return $item['created_at_obj'] ? $item['created_at_obj']->timestamp : 0;
                })
                ->take(100)
                ->map(function ($item) {
                    return [
                        'id_movimentacao' => $item['id_movimentacao'],
                        'tipo' => $item['tipo'],
                        'quantidade' => $item['quantidade'],
                        'estoque_anterior' => $item['estoque_anterior'],
                        'estoque_posterior' => $item['estoque_posterior'],
                        'motivo' => $item['motivo'],
                        'observacoes' => $item['observacoes'],
                        'origem' => $item['origem'],
                        'referencia' => $item['referencia'],
                        'created_at' => $item['created_at_obj'] ? $item['created_at_obj']->format('d/m/Y H:i') : '-',
                    ];
                })
                ->values();
            
            // Calcular estoque disponível baseado em patrimônios
            $temPatrimonios = $produto->patrimonios()->count() > 0;
            $patrimoniosAtivos = $produto->patrimonios()->where('status', 'Ativo')->count();
            $patrimoniosDisponiveis = $produto->patrimonios()->where('status', 'Ativo')->where('status_locacao', 'Disponivel')->count();
            $patrimoniosOcupados = max(0, $patrimoniosAtivos - $patrimoniosDisponiveis);
            
            return response()->json([
                'success' => true,
                'produto' => [
                    'id_produto' => $produto->id_produto,
                    'nome' => $produto->nome,
                    'estoque_total' => $temPatrimonios ? $patrimoniosAtivos : ($produto->estoque_total ?? 0),
                    'quantidade' => $temPatrimonios ? $patrimoniosDisponiveis : ($produto->quantidade ?? 0),
                    'em_uso' => $temPatrimonios ? $patrimoniosOcupados : (($produto->estoque_total ?? 0) - ($produto->quantidade ?? 0)),
                ],
                'tem_patrimonios' => $temPatrimonios,
                'patrimonios_disponiveis' => $patrimoniosDisponiveis,
                'movimentacoes' => $movimentacoesUnificadas,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar movimentações: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar movimentação de estoque
     */
    public function registrarMovimentacao(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
            
            // Verificar se tem patrimônios - não permite movimentação manual
            if ($produto->patrimonios()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este produto possui patrimônios cadastrados. O estoque é calculado automaticamente com base nos patrimônios.'
                ], 400);
            }
            
            $validated = $request->validate([
                'tipo' => 'required|in:entrada,saida',
                'quantidade' => 'required|integer|min:1',
                'motivo' => 'nullable|string|max:255',
                'observacoes' => 'nullable|string|max:1000',
            ]);
            
            $movimentacao = MovimentacaoEstoque::registrar(
                $id,
                $validated['tipo'],
                $validated['quantidade'],
                $validated['motivo'],
                $validated['observacoes']
            );

            // Disponibiliza contexto para descricao detalhada do map sem alterar regra de negocio.
            $produto->setAttribute('audit_quantidade_movimentada', (int) $movimentacao->quantidade);
            $produto->setAttribute('audit_estoque_anterior', (int) $movimentacao->estoque_anterior);
            $produto->setAttribute('audit_estoque_posterior', (int) $movimentacao->estoque_posterior);
            ActionLogger::log($produto, $movimentacao->tipo === 'entrada' ? 'entrada_estoque' : 'saida_estoque');
            
            // Recarregar produto para obter valores atualizados
            $produto->refresh();
            
            return response()->json([
                'success' => true,
                'message' => 'Movimentação registrada com sucesso!',
                'movimentacao' => [
                    'id_movimentacao' => $movimentacao->id_movimentacao,
                    'tipo' => $movimentacao->tipo,
                    'quantidade' => $movimentacao->quantidade,
                    'estoque_anterior' => $movimentacao->estoque_anterior,
                    'estoque_posterior' => $movimentacao->estoque_posterior,
                    'motivo' => $movimentacao->motivo,
                    'observacoes' => $movimentacao->observacoes,
                    'created_at' => $movimentacao->created_at->format('d/m/Y H:i'),
                ],
                'produto' => [
                    'estoque_total' => $produto->estoque_total ?? 0,
                    'quantidade' => $produto->quantidade ?? 0,
                    'em_uso' => ($produto->estoque_total ?? 0) - ($produto->quantidade ?? 0),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o histórico de locações de um produto
     */
    public function historicoLocacoes(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idPatrimonio = $request->query('id_patrimonio');
            
            $produto = Produto::where('id_produto', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail();
            
            // Buscar locações que contêm este produto
            $locacoesProduto = LocacaoProduto::where('id_produto', $id)
                ->when($idPatrimonio, function ($q) use ($idPatrimonio) {
                    $q->where('id_patrimonio', $idPatrimonio);
                })
                ->with(['locacao' => function($query) {
                    $query->with('cliente');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            $movimentacoes = ProdutoHistorico::where('id_empresa', $idEmpresa)
                ->where('id_produto', $id)
                ->whereIn('tipo_movimentacao', ['entrada', 'saida', 'retorno', 'reserva', 'ajuste'])
                ->with(['usuario'])
                ->orderBy('data_movimentacao', 'desc')
                ->limit(80)
                ->get();

            $manutencoesQuery = Manutencao::where('id_empresa', $idEmpresa)
                ->where('id_produto', $id);

            if (!empty($idPatrimonio)) {
                $manutencoesQuery->where('id_patrimonio', $idPatrimonio);
            }

            $totalManutencoes = (clone $manutencoesQuery)->count();
            
            // Formatar dados para retorno
            $locacoes = $locacoesProduto->map(function($lp) {
                $locacao = $lp->locacao;
                if (!$locacao) return null;
                
                return [
                    'id_locacao' => $locacao->id_locacao,
                    'numero_contrato' => $locacao->numero_contrato ?? $locacao->id_locacao,
                    'cliente' => $locacao->cliente ? $locacao->cliente->nome : 'N/A',
                    'cliente_nome' => $locacao->cliente ? $locacao->cliente->nome : 'N/A',
                    'data_inicio' => $locacao->data_inicio ? \Carbon\Carbon::parse($locacao->data_inicio)->format('d/m/Y') : null,
                    'data_fim' => $locacao->data_fim ? \Carbon\Carbon::parse($locacao->data_fim)->format('d/m/Y') : null,
                    'hora_saida' => $locacao->hora_saida,
                    'hora_retorno' => $locacao->hora_retorno,
                    'quantidade' => $lp->quantidade ?? 1,
                    'id_patrimonio' => $lp->id_patrimonio,
                    'status' => $locacao->status,
                    'status_label' => $this->getStatusLabel($locacao->status),
                    'status_class' => $this->getStatusClass($locacao->status),
                    'valor_unitario' => $lp->valor_unitario ?? 0,
                    'valor_total' => $lp->valor_total ?? (($lp->quantidade ?? 1) * ($lp->valor_unitario ?? 0)),
                ];
            })->filter()->values();
            
            // Estatísticas
            $stats = [
                'total' => $locacoes->count(),
                'finalizadas' => $locacoes->where('status', 'finalizada')->count(),
                'em_andamento' => $locacoes->where('status', 'em_andamento')->count(),
                'reservas' => $locacoes->where('status', 'reserva')->count(),
                'canceladas' => $locacoes->where('status', 'cancelada')->count(),
                'quantidade_total_locada' => $locacoes->sum('quantidade'),
                'valor_total' => $locacoes->sum('valor_total'),
            ];

            $movimentacoesFormatadas = $movimentacoes->map(function ($mov) {
                $tipo = $mov->tipo_movimentacao === 'retorno' ? 'entrada' : $mov->tipo_movimentacao;

                return [
                    'data' => ($mov->data_movimentacao ?? $mov->created_at)?->format('d/m/Y H:i') ?? '-',
                    'tipo' => in_array($tipo, ['entrada', 'saida'], true) ? $tipo : 'saida',
                    'quantidade' => (int) ($mov->quantidade ?? 0),
                    'motivo' => $mov->motivo,
                    'usuario' => $mov->usuario->name ?? 'Sistema',
                ];
            })->values();
            
            return response()->json([
                'success' => true,
                'locacoes' => $locacoes,
                'movimentacoes' => $movimentacoesFormatadas,
                'total' => $stats['total'],
                'finalizadas' => $stats['finalizadas'],
                'em_andamento' => $stats['em_andamento'],
                'manutencoes' => $totalManutencoes,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retorna o label do status da locação
     */
    private function getStatusLabel($status)
    {
        $labels = [
            'reserva' => 'Reserva',
            'em_andamento' => 'Em Andamento',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            'atrasada' => 'Atrasada',
        ];
        return $labels[$status] ?? ucfirst($status);
    }

    /**
     * Retorna a classe CSS do status da locação
     */
    private function getStatusClass($status)
    {
        $classes = [
            'reserva' => 'bg-warning',
            'em_andamento' => 'bg-primary',
            'finalizada' => 'bg-success',
            'cancelada' => 'bg-danger',
            'atrasada' => 'bg-danger',
        ];
        return $classes[$status] ?? 'bg-secondary';
    }

    /**
     * Calcula dados financeiros de produto e patrimônios
     */
    private function obterDadosInfoProduto(Produto $produto, $idEmpresa): array
    {
        $statusRentaveis = ['aprovado', 'finalizada', 'encerrada', 'encerrado'];

        $itensLocacao = LocacaoProduto::where('id_produto', $produto->id_produto)
            ->where('id_empresa', $idEmpresa)
            ->whereHas('locacao', function ($query) use ($statusRentaveis) {
                $query->whereIn('status', $statusRentaveis);
            })
            ->get();

        $calcularValorItem = function ($item) {
            $valorTotal = $item->preco_total ?? $item->valor_total ?? null;
            if ($valorTotal !== null) {
                return (float) $valorTotal;
            }

            $quantidade = (int) ($item->quantidade ?? 1);
            $valorUnitario = $item->preco_unitario ?? $item->valor_unitario ?? 0;

            return $quantidade * (float) $valorUnitario;
        };

        $receitaProduto = $itensLocacao->sum($calcularValorItem);

        $manutencoesProduto = Manutencao::where('id_empresa', $idEmpresa)
            ->where('id_produto', $produto->id_produto)
            ->get();

        $calcularCustoManutencao = function ($manutencao) {
            return (float) ($manutencao->valor ?? $manutencao->custo ?? 0);
        };

        $gastoManutencaoProduto = $manutencoesProduto->sum($calcularCustoManutencao);
        $lucroProduto = $receitaProduto - $gastoManutencaoProduto;

        $receitaPorPatrimonio = $itensLocacao
            ->filter(function ($item) {
                return !empty($item->id_patrimonio);
            })
            ->groupBy('id_patrimonio')
            ->map(function ($itens) use ($calcularValorItem) {
                return $itens->sum($calcularValorItem);
            });

        $gastoPorPatrimonio = $manutencoesProduto
            ->filter(function ($manutencao) {
                return !empty($manutencao->id_patrimonio);
            })
            ->groupBy('id_patrimonio')
            ->map(function ($itens) use ($calcularCustoManutencao) {
                return $itens->sum($calcularCustoManutencao);
            });

        $infoPatrimonios = $produto->patrimonios->map(function ($patrimonio) use ($receitaPorPatrimonio, $gastoPorPatrimonio) {
            $receita = (float) ($receitaPorPatrimonio->get($patrimonio->id_patrimonio) ?? 0);
            $gasto = (float) ($gastoPorPatrimonio->get($patrimonio->id_patrimonio) ?? 0);

            return [
                'id_patrimonio' => $patrimonio->id_patrimonio,
                'numero_serie' => $patrimonio->numero_serie ?? ('PAT-' . $patrimonio->id_patrimonio),
                'status_locacao' => $patrimonio->status_locacao,
                'receita' => $receita,
                'gasto_manutencao' => $gasto,
                'lucro' => $receita - $gasto,
            ];
        })->values()->toArray();

        $infoFinanceiraProduto = [
            'receita' => (float) $receitaProduto,
            'gasto_manutencao' => (float) $gastoManutencaoProduto,
            'lucro' => (float) $lucroProduto,
            'qtd_locacoes_rentaveis' => (int) $itensLocacao->count(),
            'qtd_manutencoes' => (int) $manutencoesProduto->count(),
        ];

        return [
            'infoFinanceiraProduto' => $infoFinanceiraProduto,
            'infoPatrimonios' => $infoPatrimonios,
        ];
    }
}
