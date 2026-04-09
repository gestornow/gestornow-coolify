<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Domain\Auth\Services\SecureAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class LoginController extends Controller
{
    protected $secureAuthService;

    public function __construct(SecureAuthService $secureAuthService)
    {
        Log::info('LoginController::__construct - Iniciando construtor');
        $this->secureAuthService = $secureAuthService;
        $this->middleware("guest")->only(["showLoginForm", "login"]);
    }

    public function showLoginForm(): View|RedirectResponse
    {
        // Verificar se há session token válido (sistema customizado)
        $sessionToken = session('session_token');
        $userId = session('user_id') ?? Auth::id();
        
        if ($sessionToken && $userId) {
            // Usuário tem sessão ativa, redirecionar para dashboard
            return redirect()->route('dashboard');
        }
        
        // Se Auth::check() também confirma, redirecionar
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        $pageConfigs = ['myLayout' => 'blank'];
        $rememberedLogin = Cookie::get('remember_login');
        $rememberChecked = !empty($rememberedLogin);
        
        // Verificar se há mensagem de sessão no cookie
        $sessionWarning = Cookie::get('session_warning');
        
        return view("content.authentications.auth-login-cover", [
            'pageConfigs' => $pageConfigs,
            'rememberedLogin' => $rememberedLogin,
            'rememberChecked' => $rememberChecked,
            'sessionWarning' => $sessionWarning
        ]);
    }

    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        
        try {
            $lembrar = $request->has('lembrar');
            $resultado = $this->secureAuthService->tentarLogin(
                $request->login,
                $request->senha,
                $lembrar,
                $request->userAgent() ?? '',
                $request->ip()
            );
            // Definir dados de sessão (estava ausente após último refactor)
            session([
                'session_token' => $resultado['session_token'],
                'login_ip' => $request->ip(),
                'login_time' => now()->timestamp,
                'id_empresa' => $resultado['usuario']->id_empresa
            ]);

            // Persistir session_token em cookie para recuperar sessao em caso de perda no servidor
            Cookie::queue('session_token', $resultado['session_token'], 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax');


            $statusEmpresa = $resultado['usuario']->empresa->status ?? null;
            $mensagemRestricao = match ($statusEmpresa) {
                'teste bloqueado' => 'Período de teste expirado. Escolha um plano para continuar usando o sistema.',
                'bloqueado' => 'Sua empresa está bloqueada. Escolha um plano para reativar o acesso.',
                default => null,
            };

            $response = $mensagemRestricao
                ? redirect()->route('dashboard')->with('warning', $mensagemRestricao)
                : redirect()->intended('/')->with('success', $resultado['message']);

            // Gerenciar cookie remember_login (simplificado para evitar problema de secure/domínio em ambiente http)
            if ($lembrar) {
                Cookie::queue('remember_login', $request->login, 60 * 24 * 30); // 30 dias
            } else {
                Cookie::queue(Cookie::forget('remember_login'));
            }

            return $response;

        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput($request->only("login"));
        } catch (\Exception $e) {
            return back()->with("error", "Erro interno do servidor.");
        }
    }

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        try {
            // LOG CRÍTICO: Capturar de onde veio a requisição de logout
            
            $sessionToken = session("session_token");
            $userId = Auth::id();
            $usuario = Auth::user();
            
            
            // Limpar session token do banco ANTES de fazer logout
            if ($usuario) {
                $usuario->limparSessionToken();
                $usuario->refresh(); // Recarregar do banco
            }
            
            // Fazer logout no serviço (limpa cache)
            $this->secureAuthService->logout($sessionToken, $userId);
            
            // Fazer logout do Laravel Auth
            Auth::logout();
            
            // Limpar completamente a sessão
            session()->forget('session_token');
            session()->forget('login_ip');
            session()->forget('login_time');
            Cookie::queue(Cookie::forget('session_token'));
            session()->flush();
            session()->invalidate();
            session()->regenerateToken();
            
            return redirect()->route("login.form")->with("success", "Logout realizado com sucesso.");
        } catch (\Exception $e) {
            // Em caso de erro, garantir limpeza da sessão
            Auth::logout();
            session()->flush();
            session()->invalidate();
            session()->regenerateToken();
            return redirect()->route("login.form");
        }
    }

    public function status(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(["authenticated" => false], 401);
        }

        $sessionToken = session("session_token");

        // Se perdeu token da sessão, recuperar do banco (token persistente)
        if (!$sessionToken && !empty($user->session_token)) {
            $sessionToken = $user->session_token;
            session(['session_token' => $sessionToken]);
            Cookie::queue('session_token', $sessionToken, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax');
        }

        if (!$sessionToken) {
            return response()->json(["authenticated" => false], 401);
        }

        $isValid = $this->secureAuthService->validarSessao($sessionToken, $user->id_usuario);
        
        return response()->json([
            "authenticated" => $isValid,
            "user" => $isValid ? $user : null
        ]);
    }

    public function renewSession(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Se não temos usuário autenticado, retornar token CSRF atual
            if (!$user) {
                return response()->json([
                    "success" => true,
                    "message" => "Token CSRF renovado",
                    "csrf_token" => csrf_token()
                ]);
            }
            
            // Usuário autenticado - renovar normalmente
            $sessionToken = session("session_token");
            
            // Garantir que temos um session_token válido e estável
            if (!$sessionToken) {
                $sessionToken = $user->session_token;
                if (!$sessionToken) {
                    $sessionToken = $user->gerarSessionToken();
                }
                session(['session_token' => $sessionToken]);
                Cookie::queue('session_token', $sessionToken, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax');
            }
            
            // Garantir que sessão confere com token persistido no banco
            if (!$this->secureAuthService->validarSessao($sessionToken, $user->id_usuario)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faça login para continuar.',
                    'redirect' => route('login.form')
                ], 401);
            }

            // Mantém token sem rotação (regra simples)
            try {
                if (method_exists($this->secureAuthService, 'renovarSessao')) {
                    $renewData = $this->secureAuthService->renovarSessao($sessionToken, $user->id_usuario);
                    if (isset($renewData["session_token"])) {
                        session(["session_token" => $renewData["session_token"]]);
                        Cookie::queue('session_token', $renewData["session_token"], 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax');
                    }
                }
            } catch (\Exception $e) {
                // Ignorar erro do service, a sessão já está válida
                Log::debug('Erro ao renovar sessão via service: ' . $e->getMessage());
            }
            
            return response()->json([
                "success" => true,
                "message" => "Sessão válida",
                "csrf_token" => csrf_token()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao renovar sessão: ' . $e->getMessage());
            // Sempre retornar sucesso para evitar bloqueios
            return response()->json([
                "success" => true,
                "message" => "Token CSRF renovado",
                "csrf_token" => csrf_token()
            ]);
        }
    }
}
