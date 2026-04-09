<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\CategoriaMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ModulosController extends Controller
{
    public function index()
    {
        // Carregar módulos com submódulos
        $modulos = Modulo::with('submodulos')
            ->principais()
            ->ordenados()
            ->get();
        
        // Carregar categorias ativas ordenadas
        $categorias = CategoriaMenu::where('ativo', 1)
            ->orderBy('ordem', 'asc')
            ->orderBy('nome', 'asc')
            ->get();
        
        return view('admin.planos.modulos', compact('modulos', 'categorias'));
    }

    public function create()
    {
        // Buscar módulos principais para escolher como pai
        $modulosPrincipais = Modulo::principais()->ordenados()->get();
        
        return view('admin.modulos.create', compact('modulosPrincipais'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100|unique:modulos,nome',
            'id_modulo_pai' => 'nullable|exists:modulos,id_modulo',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string|max:50',
            'rota' => 'nullable|string|max:100',
            'ordem' => 'nullable|integer|min:0',
        ], [
            'nome.required' => 'O nome do módulo é obrigatório.',
            'nome.unique' => 'Já existe um módulo com este nome.',
            'nome.max' => 'O nome não pode ter mais que 100 caracteres.',
            'id_modulo_pai.exists' => 'O módulo pai selecionado não existe.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return redirect()->back()
                           ->withErrors($validator)
                           ->withInput();
        }

        // Validar rota se for um submódulo e a rota foi informada
        if ($request->id_modulo_pai && $request->rota) {
            $rotaValida = $this->validarRota($request->rota);
            if (!$rotaValida) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A rota informada não existe no sistema.',
                        'errors' => ['rota' => ['A rota informada não existe no sistema.']]
                    ], 422);
                }
                
                return redirect()->back()
                               ->withErrors(['rota' => 'A rota informada não existe no sistema.'])
                               ->withInput();
            }
        }

        try {
            $modulo = Modulo::create([
                'nome' => $request->nome,
                'id_modulo_pai' => $request->id_modulo_pai,
                'descricao' => $request->descricao,
                'icone' => $request->icone,
                'rota' => $request->rota,
                'ordem' => $request->ordem ?? 0,
                'categoria' => $request->categoria,
                'ativo' => 1,
                'tem_submodulos' => $request->tem_submodulos ?? 0,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Módulo criado com sucesso!',
                    'modulo' => $modulo
                ]);
            }

            return redirect()->route('admin.modulos.index')
                           ->with('success', 'Módulo criado com sucesso!');

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao criar módulo: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Erro ao criar módulo: ' . $e->getMessage())
                           ->withInput();
        }
    }

    public function show(Modulo $modulo)
    {
        return view('admin.modulos.show', compact('modulo'));
    }

    public function edit(Modulo $modulo)
    {
        // Se for requisição AJAX, retornar JSON
        if (request()->ajax()) {
            return response()->json($modulo);
        }
        
        // Carrega módulos principais (exceto o próprio módulo) para seleção de pai
        $modulosPrincipais = Modulo::principais()
                                    ->where('id_modulo', '!=', $modulo->id_modulo)
                                    ->ordenados()
                                    ->get();
        
        return view('admin.modulos.edit', compact('modulo', 'modulosPrincipais'));
    }

    public function update(Request $request, Modulo $modulo)
    {
        $validator = Validator::make($request->all(), [
            'nome' => 'required|string|max:100|unique:modulos,nome,' . $modulo->id_modulo . ',id_modulo',
            'id_modulo_pai' => 'nullable|exists:modulos,id_modulo',
            'descricao' => 'nullable|string',
            'icone' => 'nullable|string|max:50',
            'rota' => 'nullable|string|max:100',
            'ordem' => 'nullable|integer|min:0',
            'ativo' => 'required|boolean',
        ], [
            'nome.required' => 'O nome do módulo é obrigatório.',
            'nome.unique' => 'Já existe um módulo com este nome.',
            'nome.max' => 'O nome não pode ter mais que 100 caracteres.',
            'id_modulo_pai.exists' => 'O módulo pai selecionado não existe.',
            'ativo.required' => 'O status ativo é obrigatório.',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro de validação.',
                    'errors' => $validator->errors()
                ], 422);
            }
            
            return redirect()->back()
                           ->withErrors($validator)
                           ->withInput();
        }

        // Validar rota se for um submódulo e a rota foi informada
        if ($request->id_modulo_pai && $request->rota) {
            $rotaValida = $this->validarRota($request->rota);
            if (!$rotaValida) {
                if ($request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'A rota informada não existe no sistema.',
                        'errors' => ['rota' => ['A rota informada não existe no sistema.']]
                    ], 422);
                }
                
                return redirect()->back()
                               ->withErrors(['rota' => 'A rota informada não existe no sistema.'])
                               ->withInput();
            }
        }

        try {
            $modulo->update([
                'nome' => $request->nome,
                'id_modulo_pai' => $request->id_modulo_pai,
                'descricao' => $request->descricao,
                'icone' => $request->icone,
                'rota' => $request->rota,
                'ordem' => $request->ordem ?? 0,
                'categoria' => $request->categoria,
                'ativo' => $request->ativo ?? 1,
                'tem_submodulos' => $request->tem_submodulos ?? 0,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Módulo atualizado com sucesso!',
                    'modulo' => $modulo
                ]);
            }

            return redirect()->route('admin.modulos.index')
                           ->with('success', 'Módulo atualizado com sucesso!');

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao atualizar módulo: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Erro ao atualizar módulo: ' . $e->getMessage())
                           ->withInput();
        }
    }

    public function destroy(Modulo $modulo)
    {
        try {
            // Contar quantos planos estão usando este módulo
            $planosUsando = $modulo->planosModulos()->count();
            
            // Remover o módulo de todos os planos antes de excluir
            if ($planosUsando > 0) {
                $modulo->planosModulos()->delete();
            }

            $modulo->delete();

            $mensagem = $planosUsando > 0 
                ? "Módulo excluído com sucesso! Foi removido de {$planosUsando} plano(s)."
                : 'Módulo excluído com sucesso!';

            if (request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensagem
                ]);
            }

            return redirect()->route('admin.modulos.index')
                           ->with('success', $mensagem);

        } catch (\Exception $e) {
            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao excluir módulo: ' . $e->getMessage()
                ], 500);
            }
            
            return redirect()->back()
                           ->with('error', 'Erro ao excluir módulo: ' . $e->getMessage());
        }
    }

    // Método AJAX para listar módulos
    public function list()
    {
        try {
            // Ordenar por ordem (crescente) e depois por nome (alfabético)
            $modulos = Modulo::orderBy('ordem', 'asc')
                           ->orderBy('nome', 'asc')
                           ->get();
            
            return response()->json([
                'success' => true,
                'modulos' => $modulos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar módulos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida se a rota existe no sistema Laravel
     * 
     * @param string $rota URL ou nome da rota a ser validada
     * @return bool
     */
    private function validarRota($rota)
    {
        if (empty($rota)) {
            return true; // Rota vazia é válida (opcional)
        }

        try {
            // Se começa com http:// ou https://, é URL externa - sempre válida
            if (filter_var($rota, FILTER_VALIDATE_URL)) {
                return true;
            }
            
            // Se começa com /, é uma URL relativa - verificar se existe
            if (strpos($rota, '/') === 0) {
                $uri = ltrim($rota, '/');
                
                // Tenta fazer uma requisição GET para a rota
                try {
                    $router = app('router');
                    $request = \Illuminate\Http\Request::create($rota, 'GET');
                    
                    // Tenta encontrar a rota
                    $route = $router->getRoutes()->match($request);
                    
                    // Se encontrou a rota, é válida
                    return true;
                } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
                    // Rota não encontrada
                    return false;
                } catch (\Exception $e) {
                    // Outro erro - log e rejeita
                    \Log::warning('Erro ao validar rota URL', [
                        'rota' => $rota,
                        'erro' => $e->getMessage()
                    ]);
                    return false;
                }
            }
            
            // Caso contrário, tenta verificar se é nome de rota Laravel
            return \Illuminate\Support\Facades\Route::has($rota);
            
        } catch (\Exception $e) {
            \Log::warning('Erro ao validar rota', [
                'rota' => $rota,
                'erro' => $e->getMessage()
            ]);
            return false;
        }
    }
}