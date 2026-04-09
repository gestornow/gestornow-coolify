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

class SecureLoginController extends Controller
{
    protected $secureAuthService;

    public function __construct(SecureAuthService $secureAuthService)
    {
        $this->secureAuthService = $secureAuthService;
        $this->middleware('guest')->except(['logout', 'status', 'renewSession']);
        $this->middleware('secure.auth')->only(['logout', 'status', 'renewSession']);
    }

    /**
     * Exibir formulário de login
     */
    public function showLoginForm(): View
    {
        return view('content.authentications.auth-login-cover');
    }

    /**
     * Processar login com segurança avançada
     */
    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $resultado = $this->secureAuthService->tentarLogin(
                $request->login,
                $request->senha,
                $request->boolean('lembrar'),
                $request->userAgent() ?? '',
                $request->ip()
            );

            // Armazenar dados de segurança na sessão
            session([
                'session_token' => $resultado['session_token'],
                'login_ip' => $request->ip(),
                'login_user_agent' => $request->userAgent(),
                'login_time' => now()->timestamp
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['message'],
                    'data' => [
                        'usuario' => $resultado['usuario'],
                        'session_token' => $resultado['session_token'],
                        'expires_at' => $resultado['expires_at']
                    ],
                    'redirect' => route('dashboard')
                ]);
            }

            return redirect()->intended(route('dashboard'))
                ->with('success', $resultado['message']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Tentativa de login inválida', [
                'login' => $request->login,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'errors' => $e->errors()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciais inválidas.',
                    'errors' => $e->errors()
                ], 422);
            }

            return back()
                ->withErrors($e->errors())
                ->withInput($request->only('login'));

        } catch (\Exception $e) {
            \Log::error('Erro crítico no login', [
                'login' => $request->login,
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro interno do servidor. Tente novamente.',
                ], 500);
            }

            return back()
                ->with('error', 'Erro interno do servidor. Tente novamente.')
                ->withInput($request->only('login'));
        }
    }

    /**
     * Logout seguro
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $sessionToken = session('session_token');
            $userId = Auth::id();
            
            // Log de logout
            \Log::info('Logout realizado', [
                'user_id' => $userId,
                'ip' => $request->ip(),
                'session_duration' => session('login_time') ? (now()->timestamp - session('login_time')) : null
            ]);
            
            // Logout seguro
            $this->secureAuthService->logout($sessionToken, $userId);
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logout realizado com sucesso.',
                    'redirect' => route('login.form')
                ]);
            }

            return redirect()->route('login.form')
                ->with('success', 'Logout realizado com sucesso.');

        } catch (\Exception $e) {
            \Log::error('Erro no logout', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            // Forçar logout mesmo com erro
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Erro ao realizar logout, mas sessão foi encerrada.'
                ], 500);
            }

            return redirect()->route('login.form')
                ->with('warning', 'Logout realizado com alguns problemas.');
        }
    }

    /**
     * Verificar status da sessão
     */
    public function status(Request $request): JsonResponse
    {
        $user = Auth::user();
        $sessionToken = session('session_token');
        
        if (!$sessionToken) {
            return response()->json([
                'authenticated' => false,
                'message' => 'Sessão inválida'
            ], 401);
        }

        $isValid = $this->secureAuthService->validarSessao($sessionToken, $user->id_usuario);
        
        if (!$isValid) {
            return response()->json([
                'authenticated' => false,
                'message' => 'Sessão expirada'
            ], 401);
        }

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id_usuario,
                'nome' => $user->nome,
                'login' => $user->login,
                'empresa' => $user->empresa ? $user->empresa->nome : null
            ],
            'session' => [
                'login_time' => session('login_time'),
                'ip' => session('login_ip')
            ]
        ]);
    }

    /**
     * Renovar sessão manualmente
     */
    public function renewSession(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $sessionToken = session('session_token');
            
            $renewData = $this->secureAuthService->renovarSessao($sessionToken, $user->id_usuario);
            
            session(['session_token' => $renewData['session_token']]);
            
            return response()->json([
                'success' => true,
                'message' => 'Sessão renovada com sucesso',
                'expires_at' => $renewData['expires_at']
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Erro ao renovar sessão manualmente', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao renovar sessão'
            ], 500);
        }
    }
}