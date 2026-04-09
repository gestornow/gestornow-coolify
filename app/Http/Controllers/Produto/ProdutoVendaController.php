<?php

namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
use App\Domain\Produto\Models\ProdutoVenda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProdutoVendaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        // Query base filtrando por id_empresa da sessão
        $query = ProdutoVenda::query()
            ->where('id_empresa', $idEmpresa);

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
            'total' => ProdutoVenda::where('id_empresa', $idEmpresa)->count(),
            'ativos' => ProdutoVenda::where('id_empresa', $idEmpresa)->where('status', 'ativo')->count(),
            'inativos' => ProdutoVenda::where('id_empresa', $idEmpresa)->where('status', 'inativo')->count(),
            'estoque_baixo' => ProdutoVenda::where('id_empresa', $idEmpresa)->where('quantidade', '<=', 5)->where('quantidade', '>', 0)->count(),
            'sem_estoque' => ProdutoVenda::where('id_empresa', $idEmpresa)->where('quantidade', '<=', 0)->count(),
        ];

        return view('produtos-venda.index', compact('produtos', 'filters', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('produtos-venda.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'codigo' => ['nullable', 'string', 'max:255'],
                'numero_serie' => ['nullable', 'string', 'max:255'],
                'descricao' => ['nullable', 'string'],
                'detalhes' => ['nullable', 'string'],
                'preco_custo' => ['nullable'],
                'preco_venda' => ['nullable'],
                'preco_locacao' => ['nullable'],
                'preco_reposicao' => ['nullable'],
                'estoque_total' => ['nullable', 'integer', 'min:0'],
                'quantidade' => ['nullable', 'integer', 'min:0'],
                'altura' => ['nullable'],
                'largura' => ['nullable'],
                'profundidade' => ['nullable'],
                'peso' => ['nullable'],
                'status' => ['nullable', 'in:ativo,inativo'],
            ], [
                'nome.required' => 'O nome do produto é obrigatório.',
                'nome.max' => 'O nome do produto deve ter no máximo 255 caracteres.',
            ]);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            if (!$idEmpresa) {
                throw new \Exception('Empresa não identificada. Por favor, selecione uma filial.');
            }

            $data = $request->only([
                'nome', 'codigo', 'numero_serie', 'descricao', 'detalhes',
                'preco', 'preco_custo', 'preco_venda', 'preco_locacao', 'preco_reposicao',
                'altura', 'largura', 'profundidade', 'peso',
                'estoque_total', 'quantidade', 'status',
                'id_marca', 'id_grupo', 'id_tipo', 'unidade_medida_id', 'id_modelo', 'hex_color'
            ]);
            $data['id_empresa'] = $idEmpresa;
            $data['status'] = $data['status'] ?? 'ativo';

            // Normalizar campos numéricos
            $data = $this->normalizeNumericFields($data);

            Log::info('=== DADOS PARA CRIAR PRODUTO VENDA ===', $data);

            // Criar produto
            $produto = ProdutoVenda::create($data);

            Log::info('=== PRODUTO VENDA CRIADO COM SUCESSO ===', [
                'id_produto_venda' => $produto->id_produto_venda,
                'nome' => $produto->nome,
                'id_empresa' => $produto->id_empresa
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produto cadastrado com sucesso.',
                    'id_produto_venda' => $produto->id_produto_venda
                ]);
            }

            return redirect()->route('produtos-venda.index')->with('success', 'Produto cadastrado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('=== ERRO DE VALIDAÇÃO AO CRIAR PRODUTO VENDA ===', $e->errors());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('=== ERRO AO CRIAR PRODUTO VENDA ===', [
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
        
        $produto = ProdutoVenda::where('id_produto_venda', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$produto) {
            abort(404);
        }

        return view('produtos-venda.show', compact('produto'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $produto = ProdutoVenda::where('id_produto_venda', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$produto) {
            abort(404);
        }

        return view('produtos-venda.edit', compact('produto'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $produto = ProdutoVenda::where('id_produto_venda', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
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
                'preco_reposicao' => ['nullable'],
                'estoque_total' => ['nullable', 'integer', 'min:0'],
                'quantidade' => ['nullable', 'integer', 'min:0'],
                'altura' => ['nullable'],
                'largura' => ['nullable'],
                'profundidade' => ['nullable'],
                'peso' => ['nullable'],
                'status' => ['nullable', 'in:ativo,inativo'],
            ], [
                'nome.required' => 'O nome do produto é obrigatório.',
                'nome.max' => 'O nome do produto deve ter no máximo 255 caracteres.',
                'quantidade.min' => 'A quantidade não pode ser negativa.',
            ]);

            $data = $request->only([
                'nome', 'codigo', 'numero_serie', 'descricao', 'detalhes',
                'preco', 'preco_custo', 'preco_venda', 'preco_locacao', 'preco_reposicao',
                'altura', 'largura', 'profundidade', 'peso',
                'estoque_total', 'quantidade', 'status',
                'id_marca', 'id_grupo', 'id_tipo', 'unidade_medida_id', 'id_modelo', 'hex_color'
            ]);

            // Normalizar campos numéricos
            $data = $this->normalizeNumericFields($data);

            // Impedir estoque negativo
            if (isset($data['quantidade']) && $data['quantidade'] < 0) {
                throw new \Exception('A quantidade em estoque não pode ser negativa.');
            }

            $produto->update($data);

            Log::info('=== PRODUTO VENDA ATUALIZADO COM SUCESSO ===', [
                'id_produto_venda' => $produto->id_produto_venda,
                'nome' => $produto->nome
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Produto atualizado com sucesso.'
                ]);
            }

            return redirect()->route('produtos-venda.index')->with('success', 'Produto atualizado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('=== ERRO DE VALIDAÇÃO AO ATUALIZAR PRODUTO VENDA ===', $e->errors());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            Log::error('=== ERRO AO ATUALIZAR PRODUTO VENDA ===', [
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
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $produto = ProdutoVenda::where('id_produto_venda', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $produto->delete();

            Log::info('=== PRODUTO VENDA EXCLUÍDO COM SUCESSO ===', [
                'id_produto_venda' => $id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produto excluído com sucesso.'
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO EXCLUIR PRODUTO VENDA ===', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove múltiplos produtos
     */
    public function excluirMultiplos(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                throw new \Exception('Nenhum produto selecionado.');
            }

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            ProdutoVenda::where('id_empresa', $idEmpresa)
                ->whereIn('id_produto_venda', $ids)
                ->delete();

            Log::info('=== PRODUTOS VENDA EXCLUÍDOS EM LOTE ===', [
                'ids' => $ids
            ]);

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' produto(s) excluído(s) com sucesso.'
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO EXCLUIR PRODUTOS VENDA EM LOTE ===', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar produtos para o PDV
     */
    public function buscarProduto(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $termo = $request->input('termo', '');

            $produtos = ProdutoVenda::where('id_empresa', $idEmpresa)
                ->where('status', 'ativo')
                ->where(function ($query) use ($termo) {
                    $query->where('codigo', 'like', '%' . $termo . '%')
                        ->orWhere('nome', 'like', '%' . $termo . '%')
                        ->orWhere('numero_serie', 'like', '%' . $termo . '%');
                })
                ->select([
                    'id_produto_venda',
                    'nome',
                    'codigo',
                    'preco_venda',
                    'quantidade',
                    'foto_url'
                ])
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'produtos' => $produtos
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar produto por código (para leitor de código de barras)
     */
    public function buscarPorCodigo(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $codigo = $request->input('codigo', '');

            $produto = ProdutoVenda::where('id_empresa', $idEmpresa)
                ->where('status', 'ativo')
                ->where('codigo', $codigo)
                ->select([
                    'id_produto_venda',
                    'nome',
                    'codigo',
                    'preco_venda',
                    'quantidade',
                    'foto_url'
                ])
                ->first();

            if (!$produto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'produto' => $produto
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajuste manual de estoque
     */
    public function ajustarEstoque(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            
            $produto = ProdutoVenda::where('id_produto_venda', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$produto) {
                throw new \Exception('Produto não encontrado.');
            }

            $validated = $request->validate([
                'quantidade' => ['required', 'integer', 'min:0'],
                'motivo' => ['nullable', 'string', 'max:500'],
            ]);

            $quantidadeAnterior = $produto->quantidade ?? 0;
            $novaQuantidade = $request->input('quantidade');

            if ($novaQuantidade < 0) {
                throw new \Exception('A quantidade não pode ser negativa.');
            }

            $produto->quantidade = $novaQuantidade;
            $produto->save();

            Log::info('=== ESTOQUE AJUSTADO MANUALMENTE ===', [
                'id_produto_venda' => $produto->id_produto_venda,
                'nome' => $produto->nome,
                'quantidade_anterior' => $quantidadeAnterior,
                'nova_quantidade' => $novaQuantidade,
                'motivo' => $request->input('motivo', 'Ajuste manual')
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Estoque ajustado com sucesso.',
                'quantidade_anterior' => $quantidadeAnterior,
                'nova_quantidade' => $novaQuantidade
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO AJUSTAR ESTOQUE ===', [
                'erro' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normaliza campos numéricos (remove formatação)
     */
    private function normalizeNumericFields(array $data): array
    {
        $numericFields = [
            'preco', 'preco_reposicao', 'preco_custo', 'preco_venda', 'preco_locacao',
            'altura', 'largura', 'profundidade', 'peso'
        ];

        foreach ($numericFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                // Campo vazio ou não definido = 0
                $data[$field] = 0;
            } elseif (is_string($data[$field])) {
                // Remove R$, pontos e troca vírgula por ponto
                $valor = str_replace(['R$', ' ', '.'], '', $data[$field]);
                $valor = str_replace(',', '.', $valor);
                $data[$field] = floatval($valor);
            }
        }

        // Campos inteiros
        $intFields = ['estoque_total', 'quantidade'];
        foreach ($intFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $data[$field] = 0;
            } else {
                $data[$field] = intval($data[$field]);
            }
        }

        return $data;
    }
}
