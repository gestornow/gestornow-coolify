<?php

namespace App\Http\Controllers\Produto;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Domain\Produto\Models\Patrimonio;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\Manutencao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PatrimonioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $query = Patrimonio::query()
            ->where('id_empresa', $idEmpresa)
            ->with('produto');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['id_produto'])) {
            $query->where('id_produto', $filters['id_produto']);
        }

        if (!empty($filters['busca'])) {
            $query->buscar($filters['busca']);
        }

        $query->orderBy('codigo_patrimonio', 'asc');
        $patrimonios = $query->paginate(50);

        $stats = [
            'total' => Patrimonio::where('id_empresa', $idEmpresa)->count(),
            'disponiveis' => Patrimonio::where('id_empresa', $idEmpresa)->where('status', 'disponivel')->count(),
            'locados' => Patrimonio::where('id_empresa', $idEmpresa)->where('status', 'locado')->count(),
            'manutencao' => Patrimonio::where('id_empresa', $idEmpresa)->where('status', 'manutencao')->count(),
        ];

        $produtos = Produto::where('id_empresa', $idEmpresa)->where('status', 'ativo')->get();

        return view('produtos.patrimonios.index', compact('patrimonios', 'filters', 'stats', 'produtos'));
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
                'numero_serie' => ['required', 'string', 'max:255'],
                'data_aquisicao' => ['nullable', 'date'],
                'valor_aquisicao' => ['nullable'],
                'status' => ['nullable', 'in:Ativo,Inativo,Descarte'],
                'status_locacao' => ['nullable', 'in:Disponivel,Locado,Em Manutencao'],
                'ultima_manutencao' => ['nullable', 'date'],
                'proxima_manutencao' => ['nullable', 'date'],
                'observacoes' => ['nullable', 'string'],
            ]);

            // Verificar número de série duplicado na empresa
            $duplicado = Patrimonio::where('id_empresa', $idEmpresa)
                ->where('numero_serie', $validated['numero_serie'])
                ->exists();

            if ($duplicado) {
                throw new \Exception('Já existe um patrimônio com este número de série.');
            }

            $data = $validated; // Segurança: persiste apenas campos validados para evitar mass assignment.
            $data['id_empresa'] = $idEmpresa;
            $data['status'] = $data['status'] ?? 'Ativo';
            $data['status_locacao'] = $data['status_locacao'] ?? 'Disponivel';
            $data = $this->normalizeNumericFields($data);

            $patrimonio = DB::transaction(function () use ($data, $idEmpresa) {
                $idProduto = $data['id_produto'];

                $produto = Produto::where('id_produto', $idProduto)
                    ->where('id_empresa', $idEmpresa)
                    ->lockForUpdate()
                    ->firstOrFail();

                $totalPatrimonios = Patrimonio::where('id_empresa', $idEmpresa)
                    ->where('id_produto', $idProduto)
                    ->count();

                if ($totalPatrimonios === 0) {
                    $produto->update([
                        'estoque_total' => 0,
                        'quantidade' => 0,
                    ]);

                    Manutencao::where('id_empresa', $idEmpresa)
                        ->where('id_produto', $idProduto)
                        ->delete();
                }

                return Patrimonio::create($data);
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Patrimônio cadastrado com sucesso.',
                    'id_patrimonio' => $patrimonio->id_patrimonio
                ]);
            }

            // Redirecionar para a página do produto se veio de lá
            if ($request->has('redirect_to')) {
                if ($request->redirect_to === 'produto') {
                    return redirect()->route('produtos.show', $request->id_produto)->with('success', 'Patrimônio cadastrado com sucesso.');
                }
                if ($request->redirect_to === 'produto_edit') {
                    return redirect()->route('produtos.edit', $request->id_produto)->with('success', 'Patrimônio cadastrado com sucesso.');
                }
            }

            return redirect()->route('patrimonios.index')->with('success', 'Patrimônio cadastrado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== ERRO DE VALIDAÇÃO PATRIMÔNIO ===', ['errors' => $e->errors()]);
            
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $e->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            Log::error('=== ERRO AO CRIAR PATRIMÔNIO ===', ['erro' => $e->getMessage()]);

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
     * Store multiple resources in storage (em massa).
     */
    public function storeMassa(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $idProduto = $request->id_produto;
            $patrimonios = $request->patrimonios;

            if (empty($patrimonios) || !is_array($patrimonios)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum patrimônio informado.'
                ], 400);
            }

            $produtoExisteNaEmpresa = Produto::where('id_produto', $idProduto)
                ->where('id_empresa', $idEmpresa)
                ->exists();

            if (!$produtoExisteNaEmpresa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produto não encontrado para a empresa atual.'
                ], 403); // Segurança: bloqueia vínculo de patrimônio com produto de outra empresa (IDOR).
            }

            // Verificar duplicados
            $numerosSerie = array_column($patrimonios, 'numero_serie');
            $duplicados = Patrimonio::where('id_empresa', $idEmpresa)
                ->whereIn('numero_serie', $numerosSerie)
                ->pluck('numero_serie')
                ->toArray();

            if (count($duplicados) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Existem números de série duplicados.',
                    'duplicados' => $duplicados
                ], 422);
            }

            $criados = DB::transaction(function () use ($idEmpresa, $idProduto, $patrimonios) {
                $produto = Produto::where('id_produto', $idProduto)
                    ->where('id_empresa', $idEmpresa)
                    ->lockForUpdate()
                    ->firstOrFail();

                $totalPatrimonios = Patrimonio::where('id_empresa', $idEmpresa)
                    ->where('id_produto', $idProduto)
                    ->count();

                if ($totalPatrimonios === 0 && count($patrimonios) > 0) {
                    $produto->update([
                        'estoque_total' => 0,
                        'quantidade' => 0,
                    ]);

                    Manutencao::where('id_empresa', $idEmpresa)
                        ->where('id_produto', $idProduto)
                        ->delete();
                }

                $totalCriados = 0;

                foreach ($patrimonios as $patrimonio) {
                    $data = [
                        'id_empresa' => $idEmpresa,
                        'id_produto' => $idProduto,
                        'numero_serie' => $patrimonio['numero_serie'],
                        'status' => $patrimonio['status'] ?? 'Ativo',
                        'status_locacao' => $patrimonio['status_locacao'] ?? 'Disponivel',
                        'observacoes' => $patrimonio['observacoes'] ?? null,
                    ];

                    if (!empty($patrimonio['valor_aquisicao'])) {
                        $valor = str_replace(['R$', ' ', '.'], '', $patrimonio['valor_aquisicao']);
                        $valor = str_replace(',', '.', $valor);
                        $data['valor_aquisicao'] = floatval($valor);
                    }

                    Patrimonio::create($data);
                    $totalCriados++;
                }

                return $totalCriados;
            });

            return response()->json([
                'success' => true,
                'message' => "{$criados} patrimônios cadastrados com sucesso."
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO CRIAR PATRIMÔNIOS EM MASSA ===', ['erro' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $patrimonio = Patrimonio::where('id_patrimonio', $id)
            ->where('id_empresa', $idEmpresa)
            ->with(['produto', 'manutencoes'])
            ->first();

        if (!$patrimonio) {
            abort(404);
        }

        return view('produtos.patrimonios.show', compact('patrimonio'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $patrimonio = Patrimonio::where('id_patrimonio', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$patrimonio) {
                throw new \Exception('Patrimônio não encontrado.');
            }

            $validated = $request->validate([
                'id_produto' => [
                    'nullable',
                    'integer',
                    Rule::exists('produtos', 'id_produto')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'numero_serie' => ['nullable', 'string', 'max:255'],
                'data_aquisicao' => ['nullable', 'date'],
                'valor_aquisicao' => ['nullable'],
                'status' => ['nullable', 'in:Ativo,Inativo,Descarte'],
                'status_locacao' => ['nullable', 'in:Disponivel,Locado,Em Manutencao'],
                'ultima_manutencao' => ['nullable', 'date'],
                'proxima_manutencao' => ['nullable', 'date'],
                'observacoes' => ['nullable', 'string'],
            ]);

            // Verificar número de série duplicado (exceto o atual)
            if (!empty($validated['numero_serie']) && $validated['numero_serie'] !== $patrimonio->numero_serie) {
                $duplicado = Patrimonio::where('id_empresa', $idEmpresa)
                    ->where('numero_serie', $validated['numero_serie'])
                    ->where('id_patrimonio', '!=', $id)
                    ->exists();

                if ($duplicado) {
                    throw new \Exception('Já existe um patrimônio com este número de série.');
                }
            }

            $data = $validated; // Segurança: persiste apenas campos validados para evitar mass assignment.
            $data = $this->normalizeNumericFields($data);

            $statusAnterior = (string) ($patrimonio->status ?? '');
            $statusLocacaoAnterior = (string) ($patrimonio->status_locacao ?? '');

            $patrimonio->update($data);
            $patrimonio->refresh();

            $statusNovo = (string) ($patrimonio->status ?? '');
            $statusLocacaoNovo = (string) ($patrimonio->status_locacao ?? '');

            if ($statusAnterior !== $statusNovo) {
                if ($statusNovo === 'Ativo') {
                    ActionLogger::log($patrimonio, 'ativacao');
                }

                if ($statusNovo === 'Inativo') {
                    ActionLogger::log($patrimonio, 'inativacao');
                }

                if ($statusNovo === 'Descarte') {
                    ActionLogger::log($patrimonio, 'descarte');
                }
            }

            if ($statusLocacaoAnterior !== $statusLocacaoNovo && $statusLocacaoNovo === 'Disponivel') {
                ActionLogger::log($patrimonio, 'disponibilizado');
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Patrimônio atualizado com sucesso.'
                ]);
            }

            // Redirecionar para a página do produto se veio de lá
            if ($request->has('redirect_to')) {
                if ($request->redirect_to === 'produto') {
                    return redirect()->route('produtos.show', $request->id_produto)->with('success', 'Patrimônio atualizado com sucesso.');
                }
                if ($request->redirect_to === 'produto_edit') {
                    return redirect()->route('produtos.edit', $request->id_produto)->with('success', 'Patrimônio atualizado com sucesso.');
                }
            }

            return redirect()->route('patrimonios.index')->with('success', 'Patrimônio atualizado com sucesso.');

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

            $patrimonio = Patrimonio::where('id_patrimonio', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$patrimonio) {
                throw new \Exception('Patrimônio não encontrado.');
            }

            // Verificar se está em locação
            if ($patrimonio->status_locacao === 'Locado') {
                throw new \Exception('Não é possível excluir um patrimônio que está locado.');
            }

            $patrimonio->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Patrimônio excluído com sucesso.'
                ]);
            }

            return redirect()->route('patrimonios.index')->with('success', 'Patrimônio excluído com sucesso.');

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
     * Remove multiple resources from storage (em massa).
     */
    public function destroyMassa(Request $request)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
            $ids = $request->ids;

            if (empty($ids) || !is_array($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum patrimônio selecionado.'
                ], 400);
            }

            // Verificar se algum está locado
            $locados = Patrimonio::where('id_empresa', $idEmpresa)
                ->whereIn('id_patrimonio', $ids)
                ->where('status_locacao', 'Locado')
                ->count();

            if ($locados > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Não é possível excluir. {$locados} patrimônio(s) está(ão) locado(s)."
                ], 422);
            }

            $excluidos = Patrimonio::where('id_empresa', $idEmpresa)
                ->whereIn('id_patrimonio', $ids)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "{$excluidos} patrimônio(s) excluído(s) com sucesso."
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERRO AO EXCLUIR PATRIMÔNIOS EM MASSA ===', ['erro' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Listar patrimônios de um produto específico
     */
    public function porProduto(Request $request, $idProduto)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $patrimonios = Patrimonio::where('id_empresa', $idEmpresa)
            ->where('id_produto', $idProduto)
            ->orderBy('codigo_patrimonio', 'asc')
            ->get();

        return response()->json($patrimonios);
    }

    /**
     * Buscar patrimônios disponíveis para locação
     */
    public function disponiveis(Request $request, $idProduto = null)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $query = Patrimonio::where('id_empresa', $idEmpresa)
            ->where('status', 'disponivel')
            ->with('produto');

        if ($idProduto) {
            $query->where('id_produto', $idProduto);
        }

        $patrimonios = $query->get();

        return response()->json($patrimonios);
    }

    /**
     * Normalizar campos numéricos
     */
    private function normalizeNumericFields($data)
    {
        $numericFields = ['valor_aquisicao', 'valor_atual'];
        
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = str_replace(['R$', ' ', '.'], '', $data[$field]);
                $data[$field] = str_replace(',', '.', $data[$field]);
                $data[$field] = floatval($data[$field]);
            }
        }

        return $data;
    }
}
