<?php

namespace App\Http\Controllers\Financeiro;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoriaRequest;
use App\Http\Resources\CategoriaResource;
use App\Models\CategoriaContas;
use App\Services\FinanceiroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class CategoriasController extends Controller
{
    /**
     * The financeiro service instance.
     */
    protected FinanceiroService $financeiroService;

    /**
     * Create a new controller instance.
     */
    public function __construct(FinanceiroService $financeiroService)
    {
        $this->financeiroService = $financeiroService;
    }

    /**
     * Store a newly created categoria in storage.
     *
     * @param \App\Http\Requests\StoreCategoriaRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreCategoriaRequest $request): JsonResponse
    {
        try {
            $categoria = $this->financeiroService->criarCategoria(
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso!',
                'data' => new CategoriaResource($categoria),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Erro ao criar categoria no controller: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar categoria. Por favor, tente novamente.',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Display the categorias page.
     *
     * @return \Illuminate\View\View
     */
    public function index(): View
    {
        return view('financeiro.categorias.index');
    }

    /**
     * Get all categorias for the current empresa (API).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function list(): JsonResponse
    {
        try {
            $id_empresa = session('id_empresa');
            $categorias = $this->financeiroService->getCategoriasByEmpresa($id_empresa);

            return response()->json([
                'success' => true,
                'data' => CategoriaResource::collection($categorias),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar categorias: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar categorias.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get categorias by type.
     *
     * @param string $tipo
     * @return \Illuminate\Http\JsonResponse
     */
    public function getByTipo(string $tipo): JsonResponse
    {
        try {
            $id_empresa = session('id_empresa');
            
            Log::info('Buscando categorias por tipo', [
                'id_empresa' => $id_empresa,
                'tipo' => $tipo,
            ]);
            
            $categorias = $this->financeiroService->getCategoriasByTipo($id_empresa, $tipo);
            
            Log::info('Categorias encontradas por tipo', [
                'count' => $categorias->count(),
                'categorias' => $categorias->toArray(),
            ]);

            return response()->json([
                'success' => true,
                'data' => CategoriaResource::collection($categorias),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao listar categorias por tipo: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar categorias.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Get a single categoria.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            $categoria = \App\Models\CategoriaContas::where('id_categoria_contas', $id)
                ->where('id_empresa', session('id_empresa'))
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'data' => new CategoriaResource($categoria),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar categoria: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Categoria não encontrada.',
            ], 404);
        }
    }

    /**
     * Update a categoria.
     *
     * @param \App\Http\Requests\StoreCategoriaRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(StoreCategoriaRequest $request, int $id): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $categoriaAtual = CategoriaContas::where('id_categoria_contas', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail(); // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.

            $categoria = $this->financeiroService->atualizarCategoria((int) $categoriaAtual->id_categoria_contas, $request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso!',
                'data' => new CategoriaResource($categoria),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar categoria: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar categoria. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Delete a categoria.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $categoria = CategoriaContas::where('id_categoria_contas', $id)
                ->where('id_empresa', $idEmpresa)
                ->firstOrFail(); // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.

            $categoria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoria excluída com sucesso!',
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao excluir categoria: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir categoria. Por favor, tente novamente.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
