<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CategoriaMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriasMenuController extends Controller
{
    /**
     * Exibir página de gerenciamento de categorias
     */
    public function index()
    {
        return view('admin.planos.categorias');
    }

    /**
     * Listar todas as categorias (AJAX)
     */
    public function list()
    {
        try {
            $categorias = CategoriaMenu::ordenadas()->get();
            
            return response()->json([
                'success' => true,
                'categorias' => $categorias
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar categorias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Armazenar nova categoria
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:100|unique:categorias_menu,nome',
                'cor' => 'nullable|string|max:50',
                'icone' => 'nullable|string|max:100',
                'ordem' => 'nullable|integer|min:0'
            ], [
                'nome.required' => 'O nome da categoria é obrigatório.',
                'nome.unique' => 'Já existe uma categoria com este nome.',
                'nome.max' => 'O nome deve ter no máximo 100 caracteres.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $categoria = CategoriaMenu::create([
                'nome' => $request->nome,
                'cor' => $request->cor,
                'icone' => $request->icone,
                'ordem' => $request->ordem ?? 0,
                'ativo' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoria criada com sucesso!',
                'categoria' => $categoria
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buscar categoria por ID (AJAX)
     */
    public function show($id)
    {
        try {
            $categoria = CategoriaMenu::findOrFail($id);
            
            return response()->json($categoria);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Categoria não encontrada.'
            ], 404);
        }
    }

    /**
     * Atualizar categoria
     */
    public function update(Request $request, $id)
    {
        try {
            $categoria = CategoriaMenu::findOrFail($id);
            
            $validator = Validator::make($request->all(), [
                'nome' => 'required|string|max:100|unique:categorias_menu,nome,' . $id . ',id_categoria',
                'cor' => 'nullable|string|max:50',
                'icone' => 'nullable|string|max:100',
                'ordem' => 'nullable|integer|min:0',
                'ativo' => 'nullable|boolean'
            ], [
                'nome.required' => 'O nome da categoria é obrigatório.',
                'nome.unique' => 'Já existe uma categoria com este nome.',
                'nome.max' => 'O nome deve ter no máximo 100 caracteres.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $categoria->update([
                'nome' => $request->nome,
                'cor' => $request->cor,
                'icone' => $request->icone,
                'ordem' => $request->ordem ?? $categoria->ordem,
                'ativo' => $request->has('ativo') ? $request->ativo : $categoria->ativo,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Categoria atualizada com sucesso!',
                'categoria' => $categoria
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar categoria: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Excluir categoria
     */
    public function destroy($id)
    {
        try {
            $categoria = CategoriaMenu::findOrFail($id);
            $categoria->delete();

            return response()->json([
                'success' => true,
                'message' => 'Categoria excluída com sucesso!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao excluir categoria: ' . $e->getMessage()
            ], 500);
        }
    }
}
