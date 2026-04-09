<?php

namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
use App\Domain\Produto\Models\TabelaPreco;
use App\Domain\Produto\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TabelaPrecoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $query = TabelaPreco::query()
            ->where('id_empresa', $idEmpresa)
            ->with('produto');

        if (!empty($filters['id_produto'])) {
            $query->where('id_produto', $filters['id_produto']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy('nome', 'asc');
        $tabelas = $query->paginate(50);

        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->get();

        return view('produtos.tabela-precos.index', compact('tabelas', 'filters', 'produtos'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->get();
        $idProduto = $request->input('id_produto');

        return view('produtos.tabela-precos.create', compact('produtos', 'idProduto'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            if (!$idEmpresa) {
                throw new \Exception('Empresa não identificada na sessão.');
            }

            $validated = $request->validate([
                'id_produto' => [
                    'required',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'nome' => ['required', 'string', 'max:100'],
            ]);

            $data = $request->only($this->camposPermitidosTabelaPreco());
            $data['id_empresa'] = $idEmpresa;
            $data['id_produto'] = (int) $validated['id_produto'];
            $data['nome'] = (string) $validated['nome'];
            $data = $this->normalizeNumericFields($data);

            Log::info('=== TENTANDO CRIAR TABELA DE PREÇOS ===', [
                'id_empresa' => $idEmpresa,
                'id_produto' => $data['id_produto'] ?? null,
                'nome' => $data['nome'] ?? null,
            ]);

            $tabela = TabelaPreco::create($data);

            Log::info('=== TABELA DE PREÇOS CRIADA ===', [
                'id_tabela' => $tabela->id_tabela,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tabela de preços criada com sucesso.',
                    'id_tabela' => $tabela->id_tabela
                ]);
            }

            // Redirecionar para a página do produto se veio de lá
            if ($request->has('redirect_to')) {
                if ($request->redirect_to === 'produto') {
                    return redirect()->route('produtos.show', $request->id_produto)->with('success', 'Tabela de preços criada com sucesso.');
                }
                if ($request->redirect_to === 'produto_edit') {
                    return redirect()->route('produtos.edit', $request->id_produto)->with('success', 'Tabela de preços criada com sucesso.');
                }
            }

            return redirect()->route('tabela-precos.index')->with('success', 'Tabela de preços criada com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERRO DE VALIDAÇÃO TABELA DE PREÇOS ===', ['errors' => $e->errors()]);
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERRO AO CRIAR TABELA DE PREÇOS ===', ['erro' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

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
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $tabela = TabelaPreco::where('id_tabela', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$tabela) {
            abort(404);
        }

        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->get();

        return view('produtos.tabela-precos.edit', compact('tabela', 'produtos'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $tabela = TabelaPreco::where('id_tabela', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$tabela) {
                throw new \Exception('Tabela de preços não encontrada.');
            }

            $request->validate([
                'id_produto' => [
                    'sometimes',
                    'required',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'nome' => ['sometimes', 'required', 'string', 'max:100'],
            ]);

            $data = $request->only($this->camposPermitidosTabelaPreco()); // Segurança: persiste apenas campos permitidos para evitar mass assignment.
            unset($data['id_empresa']);

            if (empty($data)) {
                throw new \Exception('Nenhum dado válido informado para atualização.');
            }

            $data = $this->normalizeNumericFields($data);

            $tabela->update($data);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tabela de preços atualizada com sucesso.'
                ]);
            }

            // Redirecionar para a página do produto se veio de lá
            if ($request->has('redirect_to')) {
                if ($request->redirect_to === 'produto') {
                    return redirect()->route('produtos.show', $request->id_produto)->with('success', 'Tabela de preços atualizada com sucesso.');
                }
                if ($request->redirect_to === 'produto_edit') {
                    return redirect()->route('produtos.edit', $request->id_produto)->with('success', 'Tabela de preços atualizada com sucesso.');
                }
            }

            return redirect()->route('tabela-precos.index')->with('success', 'Tabela de preços atualizada com sucesso.');

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

            $tabela = TabelaPreco::where('id_tabela', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$tabela) {
                throw new \Exception('Tabela de preços não encontrada.');
            }

            $tabela->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tabela de preços excluída com sucesso.'
                ]);
            }

            return redirect()->route('tabela-precos.index')->with('success', 'Tabela de preços excluída com sucesso.');

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
     * Obter tabela de preços de um produto
     */
    public function porProduto(Request $request, $idProduto)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $tabelas = TabelaPreco::where('id_empresa', $idEmpresa)
            ->where('id_produto', $idProduto)
            ->where('status', 'ativo')
            ->get();

        return response()->json($tabelas);
    }

    /**
     * Calcular preço por período
     */
    public function calcularPreco(Request $request)
    {
        $idTabela = $request->input('id_tabela');
        $dias = $request->input('dias', 1);
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $tabela = TabelaPreco::where('id_tabela', $idTabela)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$tabela) {
            return response()->json(['preco' => 0]);
        }

        $preco = $tabela->getPrecoPorDias($dias);

        return response()->json(['preco' => $preco]);
    }

    /**
     * Normalizar campos numéricos
     */
    private function normalizeNumericFields($data)
    {
        $numericFields = [
            'd1', 'd2', 'd3', 'd4', 'd5', 'd6', 'd7', 'd8', 'd9', 'd10',
            'd11', 'd12', 'd13', 'd14', 'd15', 'd16', 'd17', 'd18', 'd19', 'd20',
            'd21', 'd22', 'd23', 'd24', 'd25', 'd26', 'd27', 'd28', 'd29', 'd30',
            'd60', 'd120', 'd360'
        ];
        
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = str_replace(['R$', ' ', '.'], '', $data[$field]);
                $data[$field] = str_replace(',', '.', $data[$field]);
                $data[$field] = floatval($data[$field]);
            }
        }

        return $data;
    }

    private function camposPermitidosTabelaPreco(): array
    {
        return [
            'id_produto',
            'nome',
            'd1', 'd2', 'd3', 'd4', 'd5', 'd6', 'd7', 'd8', 'd9', 'd10',
            'd11', 'd12', 'd13', 'd14', 'd15', 'd16', 'd17', 'd18', 'd19', 'd20',
            'd21', 'd22', 'd23', 'd24', 'd25', 'd26', 'd27', 'd28', 'd29', 'd30',
            'd60', 'd120', 'd360',
        ];
    }
}
