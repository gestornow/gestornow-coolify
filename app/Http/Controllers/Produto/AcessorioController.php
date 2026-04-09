<?php

namespace App\Http\Controllers\Produto;

use App\Http\Controllers\Controller;
use App\Domain\Produto\Models\Acessorio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AcessorioController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->all();
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

        $query = Acessorio::query()->where('id_empresa', $idEmpresa);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['busca'])) {
            $query->buscar($filters['busca']);
        }

        $query->orderBy('nome', 'asc');
        $acessorios = $query->paginate(50);

        $stats = [
            'total' => Acessorio::where('id_empresa', $idEmpresa)->count(),
            'ativos' => Acessorio::where('id_empresa', $idEmpresa)->where('status', 'ativo')->count(),
            'inativos' => Acessorio::where('id_empresa', $idEmpresa)->where('status', 'inativo')->count(),
        ];

        return view('acessorios.index', compact('acessorios', 'filters', 'stats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('acessorios.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'descricao' => ['nullable', 'string'],
                'quantidade' => ['nullable', 'integer', 'min:0'],
                'preco_custo' => ['nullable'],
                'valor' => ['nullable'],
                'status' => ['nullable', 'in:ativo,inativo,esgotado'],
            ], [
                'nome.required' => 'O nome do acessório é obrigatório.',
            ]);

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            if (!$idEmpresa) {
                throw new \Exception('Empresa não identificada.');
            }

            $data = $validated; // Segurança: persiste apenas campos validados para evitar mass assignment.
            $data['id_empresa'] = $idEmpresa;
            $data = $this->normalizeNumericFields($data);

            $acessorio = Acessorio::create($data);

            Log::info('=== ACESSÓRIO CRIADO ===', [
                'id_acessorio' => $acessorio->id_acessorio,
                'nome' => $acessorio->nome,
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Acessório cadastrado com sucesso.',
                    'id_acessorio' => $acessorio->id_acessorio
                ]);
            }

            return redirect()->route('acessorios.index')->with('success', 'Acessório cadastrado com sucesso.');

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
            Log::error('=== ERRO AO CRIAR ACESSÓRIO ===', ['erro' => $e->getMessage()]);

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
        
        $acessorio = Acessorio::where('id_acessorio', $id)
            ->where('id_empresa', $idEmpresa)
            ->with('produtos')
            ->first();

        if (!$acessorio) {
            abort(404);
        }

        return view('acessorios.show', compact('acessorio'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        
        $acessorio = Acessorio::where('id_acessorio', $id)
            ->where('id_empresa', $idEmpresa)
            ->first();

        if (!$acessorio) {
            abort(404);
        }

        return view('acessorios.edit', compact('acessorio'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            $acessorio = Acessorio::where('id_acessorio', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$acessorio) {
                throw new \Exception('Acessório não encontrado.');
            }

            $validated = $request->validate([
                'nome' => ['required', 'string', 'max:255'],
                'descricao' => ['nullable', 'string'],
                'quantidade' => ['nullable', 'integer', 'min:0'],
                'preco_custo' => ['nullable'],
                'valor' => ['nullable'],
                'status' => ['nullable', 'in:ativo,inativo,esgotado'],
            ]);

            $data = $validated; // Segurança: persiste apenas campos validados para evitar mass assignment.
            $data = $this->normalizeNumericFields($data);

            $acessorio->update($data);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Acessório atualizado com sucesso.'
                ]);
            }

            return redirect()->route('acessorios.index')->with('success', 'Acessório atualizado com sucesso.');

        } catch (\Exception $e) {
            Log::error('=== ERRO AO ATUALIZAR ACESSÓRIO ===', ['erro' => $e->getMessage()]);

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

            $acessorio = Acessorio::where('id_acessorio', $id)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$acessorio) {
                throw new \Exception('Acessório não encontrado.');
            }

            $acessorio->delete();

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Acessório excluído com sucesso.'
                ]);
            }

            return redirect()->route('acessorios.index')->with('success', 'Acessório excluído com sucesso.');

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
     * Excluir múltiplos acessórios
     */
    public function excluirMultiplos(Request $request)
    {
        try {
            $ids = $request->input('ids', []);
            
            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum acessório selecionado.'
                ], 400);
            }

            $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;

            Acessorio::where('id_empresa', $idEmpresa)
                ->whereIn('id_acessorio', $ids)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' acessório(s) excluído(s) com sucesso.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar acessórios para select (AJAX)
     */
    public function buscar(Request $request)
    {
        $idEmpresa = session('id_empresa') ?? Auth::user()->id_empresa ?? null;
        $termo = $request->input('q', '');

        $acessorios = Acessorio::where('id_empresa', $idEmpresa)
            ->where('status', 'ativo')
            ->where(function($q) use ($termo) {
                $q->where('nome', 'like', "%{$termo}%")
                  ->orWhere('codigo', 'like', "%{$termo}%");
            })
            ->limit(20)
            ->get(['id_acessorio', 'nome', 'codigo', 'quantidade', 'preco_locacao']);

        return response()->json($acessorios);
    }

    /**
     * Normalizar campos numéricos
     */
    private function normalizeNumericFields($data)
    {
        $numericFields = ['preco_custo', 'valor'];
        
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
