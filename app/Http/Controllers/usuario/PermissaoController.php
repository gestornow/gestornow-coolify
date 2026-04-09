<?php

namespace App\Http\Controllers\usuario;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Services\PermissaoService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PermissaoController extends Controller
{
    protected $permissaoService;

    public function __construct(PermissaoService $permissaoService)
    {
        $this->permissaoService = $permissaoService;
    }

    /**
     * Obter permissões do usuário
     */
    public function obterPermissoes($idUsuario): JsonResponse
    {
        try {
            $idEmpresa = (int) session('id_empresa');

            if ($idEmpresa <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão'
                ], 401);
            }

            $usuario = Usuario::where('id_usuario', (int) $idUsuario)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if (!$usuario) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado para a empresa atual.'
                ], 404); // Segurança: bloqueia consulta de permissões de usuário de outra empresa (IDOR).
            }
            
            $modulos = $this->permissaoService->obterModulosEmpresa($idEmpresa);
            $permissoes = $this->permissaoService->obterPermissoesUsuario((int) $idUsuario, $idEmpresa);

            return response()->json([
                'success' => true,
                'modulos' => $modulos,
                'permissoes' => $permissoes->keyBy('id_modulo')->toArray()
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao obter permissões', [
                'id_usuario' => $idUsuario,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter permissões'
            ], 500);
        }
    }

    /**
     * Obter módulos disponíveis para configuração
     */
    public function obterModulosDisponiveis(): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');
            
            if (!$idEmpresa) {
                \Log::warning('Sessão id_empresa não encontrada');
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão'
                ], 400);
            }

            $modulos = $this->permissaoService->obterModulosEmpresa($idEmpresa);

            \Log::info('Módulos carregados', [
                'id_empresa' => $idEmpresa,
                'total_modulos' => $modulos->count()
            ]);

            if ($modulos->isEmpty()) {
                \Log::warning('Nenhum módulo encontrado', ['id_empresa' => $idEmpresa]);
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum módulo disponível para esta empresa'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'modulos' => $modulos
            ]);
        } catch (\Exception $e) {
            \Log::error('Erro ao obter módulos disponíveis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar módulos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar permissões do usuário
     */
    public function salvarPermissoes(Request $request): JsonResponse
    {
        try {
            $idEmpresa = (int) session('id_empresa');

            if ($idEmpresa <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão'
                ], 401);
            }

            $validated = $request->validate([
                'id_usuario' => [
                    'required',
                    'integer',
                    Rule::exists('usuarios', 'id_usuario')->where(function ($query) use ($idEmpresa) {
                        $query->where('id_empresa', $idEmpresa);
                    }),
                ],
                'permissoes' => 'required|array'
            ]);

            $resultado = $this->permissaoService->salvarPermissoes(
                $validated['id_usuario'],
                $idEmpresa,
                $validated['permissoes']
            );

            if (!$resultado) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao salvar permissões'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Permissões salvas com sucesso!'
            ]);

        } catch (\Exception $e) {
            \Log::error('Erro ao salvar permissões', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar permissões: ' . $e->getMessage()
            ], 500);
        }
    }
}
