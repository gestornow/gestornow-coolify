<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Auth\Services\RegistroService;
use App\Domain\Auth\Services\PasswordResetService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthApiController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private RegistroService $registroService,
        private PasswordResetService $passwordResetService
    ) {}

    // LOGIN
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|string',
            'senha' => 'required|string|min:6',
            'lembrar' => 'sometimes|boolean'
        ]);

        if($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        try {
            $resultado = $this->authService->tentarLogin(
                $request->login,
                $request->senha,
                $request->boolean('lembrar'),
                $request->userAgent() ?? 'flutter',
                $request->ip()
            );
            $usuario = $resultado['usuario'];

            $this->authService->verificarStatusUsuarioEmpresa($usuario);
            
            if (!$usuario->podeAcessarSistema()) {
                throw ValidationException::withMessages([
                    'login' => ['Seu usuário ou empresa estão inativos. Contate o suporte.']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $resultado['message'],
                'data' => [
                    'usuario' => $resultado['usuario'],
                    'session_token' => $resultado['session_token'],
                    'api_token' => $resultado['session_token']
                ]
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('API Login error', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erro no login'], 500);
        }
    }

    // PRÉ-REGISTRO (inicia fluxo e envia email)
    public function preRegistro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razao_social' => 'required|string|min:3',
            'cnpj' => 'required|string|min:11',
            'nome' => 'required|string|min:2',
            'email' => 'required|email'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        try {
            // Reutilizar lógica existente do RegistroController (assumindo que existe método registrarEmpresaEUsuario)
            $dadosEmpresa = [
                'razao_social' => $request->razao_social,
                'cnpj' => preg_replace('/\D/', '', $request->cnpj),
                'email' => $request->email,
                'nome_empresa' => $request->nome
            ];
            $dadosUsuario = [
                'login' => strtolower($request->email),
                'nome' => $request->nome,
                'status' => 'ativo'
            ];
            $resultado = $this->registroService->registrarEmpresaEUsuario($dadosEmpresa, $dadosUsuario);

            // Gerar token único para validação e salvar no cache (mesma lógica do RegistroController)
            $token = \Str::random(60);
            $dadosTemp = [
                'id_empresa' => $resultado['empresa']->id_empresa,
                'id_usuario' => $resultado['usuario']->id_usuario,
                'nome' => $request->nome,
                'email' => $request->email,
                'token' => $token,
                'expires_at' => now()->addHours(24)
            ];
            \Cache::put("pre_registro_{$token}", $dadosTemp, now()->addHours(24));


            // Preparar link de validação para deep link do app
            $linkValidacao = 'https://gestornow.com/validar-email?token=' . $token;

            // Tentar enviar email (se falhar, continua o processo e retorna sucesso)
            try {
                \Log::info('=== INICIANDO ENVIO DE EMAIL (API) ===', ['email' => $request->email, 'link' => $linkValidacao]);
                \Mail::send('emails.validacao-conta', [
                    'nome' => $request->nome,
                    'razao_social' => $request->razao_social,
                    'link' => $linkValidacao
                ], function ($message) use ($request) {
                    $message->to($request->email)
                            ->from('contato@gestornow.com', 'GestorNow')
                            ->subject('Validação de conta - GestorNow');
                });

                // Verificar falhas (SwiftMailer)
                try {
                    $failures = \Mail::failures();
                    if (!empty($failures)) {
                        \Log::error('=== MAIL FAILURES (API) ===', ['failures' => $failures, 'email' => $request->email]);
                    } else {
                        \Log::info('=== EMAIL DE VALIDAÇÃO ENVIADO COM SUCESSO (API) ===', ['email' => $request->email]);
                    }
                } catch (\Throwable $t) {
                    // Em algumas versões do mailer Mail::failures pode não existir; apenas logar que envio foi tentado
                    \Log::info('=== EMAIL DE VALIDAÇÃO ENVIADO (sem verificar failures) (API) ===', ['email' => $request->email, 'note' => $t->getMessage()]);
                }
            } catch (\Exception $emailError) {
                \Log::error('=== ERRO AO ENVIAR EMAIL (API) ===', [
                    'error' => $emailError->getMessage(),
                    'trace' => $emailError->getTraceAsString(),
                    'email' => $request->email
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pré-registro criado. Valide o e-mail para continuar.',
                'data' => [
                    'empresa_id' => $resultado['empresa']->id_empresa,
                    'usuario_id' => $resultado['usuario']->id_usuario,
                    'nome' => $request->nome,
                    'email' => $request->email,
                    'token_enviado_por_email' => true
                ]
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('API PreRegistro error', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erro ao iniciar registro'], 500);
        }
    }

    // FINALIZAR REGISTRO (definir senha após validação de email)
    public function finalizarRegistro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'empresa_id' => 'required|integer',
            'usuario_id' => 'required|integer',
            'token' => 'required|string|min:40',
            'senha' => 'required|string|min:8|confirmed'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        try {
            $dadosTemp = \Cache::get("pre_registro_{$request->token}");

            if (!$dadosTemp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token de validação inválido ou expirado.'
                ], 422);
            }

            if ((int) ($dadosTemp['id_empresa'] ?? 0) !== (int) $request->empresa_id
                || (int) ($dadosTemp['id_usuario'] ?? 0) !== (int) $request->usuario_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dados de validação não conferem com o token informado.'
                ], 422);
            }

            $resultado = $this->registroService->finalizarRegistro(
                $request->empresa_id,
                $request->usuario_id,
                $request->senha,
                isset($dadosTemp['id_plano_teste']) ? (int) $dadosTemp['id_plano_teste'] : null
            );

            \Cache::forget("pre_registro_{$request->token}");

            return response()->json([
                'success' => true,
                'message' => $resultado['message'],
                'data' => [
                    'usuario' => $resultado['usuario'],
                    'empresa' => $resultado['empresa']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('API FinalizarRegistro error', ['e' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Erro ao finalizar registro'], 500);
        }
    }

    // INICIAR RECUPERAÇÃO SENHA (envia código)
    public function iniciarResetSenha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|email'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $this->passwordResetService->enviarCodigoRedefinicao($request->login);

        // Resposta sempre neutra para não permitir enumeração de contas.
        return response()->json([
            'success' => true,
            'message' => 'Se o email estiver cadastrado, enviaremos um código de redefinição.',
        ]);
    }

    // VALIDAR CÓDIGO DE RESET
    public function validarCodigoReset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|email',
            'codigo' => 'required|string|size:6'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $resultado = $this->passwordResetService->validarCodigo($request->login, $request->codigo);
        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    // ATUALIZAR SENHA COM TOKEN RESET
    public function atualizarSenha(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login' => 'required|email',
            'token_reset' => 'required|string',
            'senha' => 'required|string|min:8|confirmed'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        $resultado = $this->passwordResetService->atualizarSenha(
            $request->login,
            $request->senha,
            $request->token_reset
        );
        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    // PRÉ-REGISTRO: obter IDs por token (para deep link validar-email)
    public function preRegistroDados(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Token ausente'], 422);
        }

        $dados = \Cache::get("pre_registro_{$token}");
        if (!$dados) {
            return response()->json(['success' => false, 'message' => 'Token inválido ou expirado'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'empresa_id' => $dados['id_empresa'] ?? null,
                'usuario_id' => $dados['id_usuario'] ?? null,
            ]
        ]);
    }

    // LOGOUT (invalidate session token)
    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string',
            'user_id' => 'required|integer',
            'api_token' => 'sometimes|string'
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        try {
            $this->authService->logout($request->session_token, $request->user_id);
            // Revogar token Sanctum se enviado
            if ($request->filled('api_token')) {
                $hashed = explode('|', $request->api_token);
                $tokenPart = $hashed[1] ?? null;
                if ($tokenPart) {
                    $token = PersonalAccessToken::findToken($request->api_token);
                    if ($token) {
                        $token->delete();
                    }
                }
            }
            return response()->json(['success' => true, 'message' => 'Logout efetuado.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Erro no logout.'], 500);
        }
    }

    // Retorna usuário autenticado via Sanctum
    public function me(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'usuario' => $user->load('empresa')
            ]
        ]);
    }
}
