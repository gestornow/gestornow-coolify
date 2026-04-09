<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Services\SecureAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecureAuthMiddleware
{
    protected $secureAuthService;

    public function __construct(SecureAuthService $secureAuthService)
    {
        $this->secureAuthService = $secureAuthService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Verificar se o usuário está autenticado
        if (!Auth::check()) {
            return $this->redirectToLogin($request);
        }

        $user = Auth::user();
        $sessionToken = session('session_token');

        // Verificar token de sessão
        if (!$sessionToken) {
            \Log::warning('Sessão sem token de segurança', [
                'user_id' => $user->id_usuario,
                'ip' => $request->ip()
            ]);
            
            Auth::logout();
            return $this->redirectToLogin($request);
        }

        // Validar sessão
        if (!$this->secureAuthService->validarSessao($sessionToken, $user->id_usuario)) {
            \Log::warning('Token de sessão inválido', [
                'user_id' => $user->id_usuario,
                'session_token' => substr($sessionToken, 0, 10) . '...',
                'ip' => $request->ip()
            ]);
            
            Auth::logout();
            session()->flush();
            return $this->redirectToLogin($request);
        }

        // Verificar se a sessão precisa ser renovada
        try {
            $renewData = $this->secureAuthService->renovarSessao($sessionToken, $user->id_usuario);
            
            if ($renewData['session_token'] !== $sessionToken) {
                session(['session_token' => $renewData['session_token']]);
                
                \Log::info('Sessão renovada automaticamente', [
                    'user_id' => $user->id_usuario,
                    'new_expires_at' => $renewData['expires_at']
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Erro ao renovar sessão', [
                'user_id' => $user->id_usuario,
                'error' => $e->getMessage()
            ]);
            
            Auth::logout();
            session()->flush();
            return $this->redirectToLogin($request);
        }

        // Verificar atividade suspeita (múltiplas sessões, etc.)
        $this->verificarAtividadeSuspeita($request, $user);

        // Adicionar headers de segurança
        $this->adicionarHeadersSeguranca($request);

        return $next($request);
    }

    /**
     * Redirecionar para login
     */
    protected function redirectToLogin(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Faça login para continuar.',
                'redirect' => route('login.form')
            ], 401);
        }

        return redirect()->route('login.form')
            ->with('warning', 'Faça login para continuar.');
    }

    /**
     * Verificar atividade suspeita
     */
    protected function verificarAtividadeSuspeita(Request $request, $user)
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Verificar mudança de IP durante a sessão
        $lastIp = session('login_ip');
        if ($lastIp && $lastIp !== $ip) {
            \Log::warning('Mudança de IP durante sessão ativa', [
                'user_id' => $user->id_usuario,
                'old_ip' => $lastIp,
                'new_ip' => $ip,
                'user_agent' => $userAgent
            ]);
            
            // Aqui você pode implementar ações como:
            // - Forçar logout
            // - Notificar o usuário
            // - Solicitar reautenticação
        }
        
        // Verificar mudança de User-Agent
        $lastUserAgent = session('login_user_agent');
        if ($lastUserAgent && $lastUserAgent !== $userAgent) {
            \Log::warning('Mudança de User-Agent durante sessão ativa', [
                'user_id' => $user->id_usuario,
                'old_user_agent' => $lastUserAgent,
                'new_user_agent' => $userAgent,
                'ip' => $ip
            ]);
        }
    }

    /**
     * Adicionar headers de segurança
     */
    protected function adicionarHeadersSeguranca(Request $request)
    {
        // Headers já são adicionados via middleware padrão do Laravel
        // Mas podemos adicionar headers específicos aqui se necessário
    }
}