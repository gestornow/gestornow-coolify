<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Domain\User\Services\UserService;
use App\Services\PermissaoService as PermissaoGrupoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserApiController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    /**
     * GET /api/usuarios
     * Lista usuários com filtros e paginação
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['status', 'finalidade', 'search']);
            $filters['id_empresa'] = $this->resolverIdEmpresaEscopo($request); // Segurança: força escopo da empresa da sessão/auth para bloquear IDOR.

            if (empty($filters['id_empresa'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $perPage = $request->input('per_page', 10);
            $users = $this->userService->getUserList($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $users->items(),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('API UserIndex error', ['e' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar usuários'
            ], 500);
        }
    }

    /**
     * GET /api/usuarios/{id}
     * Retorna um usuário específico
     */
    public function show($id)
    {
        try {
            $user = $this->userService->getUserById((int) $id);
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo(request());

            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ((int) $user->id_empresa !== (int) $idEmpresaEscopo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para acessar este usuário'
                ], 403); // Segurança: impede leitura de usuário de outra empresa (IDOR).
            }

            return response()->json([
                'success' => true,
                'data' => $user->load('empresa')
            ]);
        } catch (\Exception $e) {
            Log::error('API UserShow error', ['e' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuário'
            ], 500);
        }
    }

    /**
     * POST /api/usuarios
     * Cria um novo usuário
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => [
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'login')->whereNull('deleted_at'),
            ],
            'nome' => 'required|string|max:255',
            'senha' => 'sometimes|string|min:6',
            'id_perfil_global' => 'nullable|integer',
            'telefone' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:20',
            'cep' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:100',
            'comissao' => 'nullable|numeric|min:0|max:100',
            'observacoes' => 'nullable|string',
            'id_empresa' => 'prohibited',
            'status' => 'nullable|string|in:ativo,inativo,bloqueado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $data = $validator->validated(); // Segurança: usa somente payload validado para evitar mass assignment.
            $idPerfilGlobalSelecionado = null;
            if (array_key_exists('id_perfil_global', $data)) {
                $idPerfilGlobalSelecionado = !empty($data['id_perfil_global']) ? (int) $data['id_perfil_global'] : null;
                unset($data['id_perfil_global']);
            }
            
            // Normalizar campos formatados
            if (isset($data['cpf'])) {
                $data['cpf'] = preg_replace('/\D+/', '', $data['cpf']);
            }
            if (isset($data['cep'])) {
                $data['cep'] = preg_replace('/\D+/', '', $data['cep']);
            }
            if (isset($data['telefone'])) {
                $data['telefone'] = preg_replace('/\D+/', '', $data['telefone']);
            }
            if (isset($data['comissao'])) {
                $data['comissao'] = str_replace(['.', ','], ['', '.'], $data['comissao']);
            }

            $data['id_empresa'] = $this->resolverIdEmpresaEscopo($request); // Segurança: fixa id_empresa pela sessão/auth para bloquear IDOR.

            if (empty($data['id_empresa'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }
            
            // Status padrão
            if (empty($data['status'])) {
                $data['status'] = 'ativo';
            }

            $user = $this->userService->create($data);

            if ($idPerfilGlobalSelecionado) {
                app(PermissaoGrupoService::class)->atribuirPerfilGlobal(
                    (int) $user->id_usuario,
                    (int) $data['id_empresa'],
                    $idPerfilGlobalSelecionado
                );
                $user = $this->userService->getUserById((int) $user->id_usuario);
            }

            return response()->json([
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'data' => $user->load('empresa')
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('API UserStore error', ['e' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao criar usuário'
            ], 500);
        }
    }

    /**
     * PUT/PATCH /api/usuarios/{id}
     * Atualiza um usuário
     */
    public function update($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('usuarios', 'login')->whereNull('deleted_at')->ignore((int) $id, 'id_usuario'),
            ],
            'nome' => 'sometimes|string|max:255',
            'senha' => 'sometimes|string|min:6',
            'id_perfil_global' => 'nullable|integer',
            'telefone' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:20',
            'cep' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:100',
            'comissao' => 'nullable|numeric|min:0|max:100',
            'observacoes' => 'nullable|string',
            'status' => 'nullable|string|in:ativo,inativo,bloqueado'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo($request);
            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $usuarioAtual = $this->userService->getUserById((int) $id);
            if (!$usuarioAtual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) $idEmpresaEscopo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para atualizar este usuário'
                ], 403); // Segurança: impede atualização de usuário de outra empresa (IDOR).
            }

            $data = $validator->validated(); // Segurança: usa somente payload validado para evitar mass assignment.
            $idPerfilGlobalInformado = array_key_exists('id_perfil_global', $data);
            $idPerfilGlobalSelecionado = null;
            if ($idPerfilGlobalInformado) {
                $idPerfilGlobalSelecionado = !empty($data['id_perfil_global']) ? (int) $data['id_perfil_global'] : null;
                unset($data['id_perfil_global']);
            }
            
            // Normalizar campos formatados
            if (isset($data['cpf'])) {
                $data['cpf'] = preg_replace('/\D+/', '', $data['cpf']);
            }
            if (isset($data['cep'])) {
                $data['cep'] = preg_replace('/\D+/', '', $data['cep']);
            }
            if (isset($data['telefone'])) {
                $data['telefone'] = preg_replace('/\D+/', '', $data['telefone']);
            }
            if (isset($data['comissao'])) {
                $raw = trim($data['comissao']);
                if ($raw === '') {
                    $data['comissao'] = null;
                } else {
                    $normalized = str_replace('.', '', $raw);
                    $normalized = str_replace(',', '.', $normalized);
                    $data['comissao'] = $normalized;
                }
            }

            $user = $this->userService->update($id, $data);

            if ($idPerfilGlobalInformado) {
                $servicoPermissao = app(PermissaoGrupoService::class);
                if ($idPerfilGlobalSelecionado) {
                    $servicoPermissao->atribuirPerfilGlobal((int) $id, (int) $idEmpresaEscopo, $idPerfilGlobalSelecionado);
                } else {
                    $servicoPermissao->removerPerfilGlobal((int) $id, (int) $idEmpresaEscopo);
                }
                $user = $this->userService->getUserById((int) $id);
            }
            
            // Mensagem personalizada baseada no status
            $message = 'Usuário atualizado com sucesso';
            if (isset($data['status'])) {
                if ($data['status'] === 'bloqueado') {
                    $message = 'Usuário bloqueado com sucesso';
                } elseif ($data['status'] === 'ativo') {
                    $message = 'Usuário ativado com sucesso';
                }
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $user->load('empresa')
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('API UserUpdate error', ['e' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao atualizar usuário'
            ], 500);
        }
    }

    /**
     * DELETE /api/usuarios/{id}
     * Deleta um usuário (soft delete)
     */
    public function destroy($id)
    {
        try {
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo(request());
            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $usuarioAtual = $this->userService->getUserById((int) $id);
            if (!$usuarioAtual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) $idEmpresaEscopo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para deletar este usuário'
                ], 403); // Segurança: impede exclusão de usuário de outra empresa (IDOR).
            }

            $this->userService->destroy($id);

            return response()->json([
                'success' => true,
                'message' => 'Usuário deletado com sucesso'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            Log::error('API UserDestroy error', ['e' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao deletar usuário'
            ], 500);
        }
    }

    /**
     * GET /api/usuarios/stats
     * Retorna estatísticas dos usuários
     */
    public function stats(Request $request)
    {
        try {
            $filters = [
                'id_empresa' => $this->resolverIdEmpresaEscopo($request), // Segurança: força escopo da empresa da sessão/auth para bloquear IDOR.
            ];

            if (empty($filters['id_empresa'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $stats = [
                'total' => $this->userService->countUsers($filters),
                'ativos' => $this->userService->countUsers(array_merge($filters, ['status' => 'ativo'])),
                'inativos' => $this->userService->countUsers(array_merge($filters, ['status' => 'inativo'])),
                'bloqueados' => $this->userService->countUsers(array_merge($filters, ['status' => 'bloqueado'])),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('API UserStats error', ['e' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar estatísticas'
            ], 500);
        }
    }

    /**
     * POST /api/usuarios/{id}/block
     * Bloqueia um usuário
     */
    public function block($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'motivo' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo($request);
            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $usuarioAtual = $this->userService->getUserById((int) $id);
            if (!$usuarioAtual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) $idEmpresaEscopo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para bloquear este usuário'
                ], 403); // Segurança: impede bloqueio de usuário de outra empresa (IDOR).
            }

            $data = [
                'status' => 'bloqueado'
            ];
            
            if ($request->filled('motivo')) {
                $data['observacoes'] = $request->motivo;
            }

            $user = $this->userService->update($id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Usuário bloqueado com sucesso',
                'data' => $user->load('empresa')
            ]);
        } catch (\Exception $e) {
            Log::error('API UserBlock error', ['e' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao bloquear usuário'
            ], 500);
        }
    }

    /**
     * POST /api/usuarios/{id}/unlock
     * Desbloqueia um usuário
     */
    public function unlock($id)
    {
        try {
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo(request());
            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $usuarioAtual = $this->userService->getUserById((int) $id);
            if (!$usuarioAtual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) $idEmpresaEscopo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para desbloquear este usuário'
                ], 403); // Segurança: impede desbloqueio de usuário de outra empresa (IDOR).
            }

            $user = $this->userService->update($id, ['status' => 'ativo']);

            return response()->json([
                'success' => true,
                'message' => 'Usuário desbloqueado com sucesso',
                'data' => $user->load('empresa')
            ]);
        } catch (\Exception $e) {
            Log::error('API UserUnlock error', ['e' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao desbloquear usuário'
            ], 500);
        }
    }

    /**
     * POST /api/usuarios/{id}/activate
     * Ativa um usuário inativo
     */
    public function activate($id)
    {
        try {
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo(request());
            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $usuarioAtual = $this->userService->getUserById((int) $id);
            if (!$usuarioAtual) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuário não encontrado'
                ], 404);
            }

            if ((int) $usuarioAtual->id_empresa !== (int) $idEmpresaEscopo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para ativar este usuário'
                ], 403); // Segurança: impede ativação de usuário de outra empresa (IDOR).
            }

            $user = $this->userService->update($id, ['status' => 'ativo']);

            return response()->json([
                'success' => true,
                'message' => 'Usuário ativado com sucesso',
                'data' => $user->load('empresa')
            ]);
        } catch (\Exception $e) {
            Log::error('API UserActivate error', ['e' => $e->getMessage(), 'id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao ativar usuário'
            ], 500);
        }
    }

    /**
     * GET /api/usuarios/search
     * Busca usuários por termo
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'term' => 'required|string|min:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $idEmpresaEscopo = $this->resolverIdEmpresaEscopo($request);
            if (empty($idEmpresaEscopo)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Empresa não identificada na sessão.'
                ], 401);
            }

            $users = $this->userService->searchUsers($request->term)
                ->where('id_empresa', $idEmpresaEscopo)
                ->values(); // Segurança: filtra resultados pela empresa da sessão/auth para bloquear IDOR.

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('API UserSearch error', ['e' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar usuários'
            ], 500);
        }
    }

    private function resolverIdEmpresaEscopo(Request $request): ?int
    {
        $usuarioAutenticado = $request->user();
        $idEmpresa = session('id_empresa') ?? ($usuarioAutenticado ? $usuarioAutenticado->id_empresa : null);
        return $idEmpresa !== null ? (int) $idEmpresa : null;
    }
}
