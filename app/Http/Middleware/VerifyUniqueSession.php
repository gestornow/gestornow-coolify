<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;

class VerifyUniqueSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        
        // Rotas que não devem ser verificadas (para evitar logout em requisições AJAX)
        $excludedRoutes = [
            'salvar-tema',
            'trocar-filial',
            'csrf-token',
            'auth/renovar-sessao',
        ];
        
        // Também excluir rotas AJAX de busca
        $excludedPatterns = [
            'clientes/',
            'produtos/',
            'locacoes/produtos-disponiveis',
            'locacoes/buscar-',
            'locacoes/verificar-',
            'locacoes/',  // Excluir todas as requisições AJAX de locações
            '/json',      // Endpoints que retornam JSON
            '/edit',      // Páginas de edição (para evitar conflito de token)
        ];
        
        $path = $request->path();
        
        // Se é uma requisição AJAX, ser mais tolerante
        if ($request->ajax() || $request->expectsJson()) {
            return $next($request);
        }
        
        if (in_array($path, $excludedRoutes)) {
            return $next($request);
        }
        
        foreach ($excludedPatterns as $pattern) {
            if (str_contains($path, $pattern)) {
                return $next($request);
            }
        }
        
        if (Auth::check()) {
            $user = Auth::user();
            $sessionTokenFromSession = session('session_token');
            $sessionTokenFromDB = $user->session_token;

            // Regra simples: se token no banco estiver vazio, bloquear acesso
            if (empty($sessionTokenFromDB)) {
                return $this->handleSessionInvalid($request, 'Sessão inválida. Faça login novamente.');
            }

            // Verificar se a empresa ainda tem status permitido
            if ($user->empresa) {
                $statusEmpresa = $user->empresa->status;

                // Empresas bloqueadas devem conseguir logar para visualizar a tela de planos.
                // O bloqueio de navegação para outras telas fica no VerificarAcessoEmpresa.
                if (!in_array($statusEmpresa, ['ativo', 'teste', 'teste bloqueado', 'bloqueado'])) {
                    $mensagem = match($statusEmpresa) {
                        'validacao' => 'Empresa em processo de validação.',
                        'cancelado' => 'Empresa cancelada. Entre em contato com o suporte.',
                        'inativo' => 'Empresa inativa. Entre em contato com o suporte.',
                        default => 'Acesso não autorizado.'
                    };

                    return $this->handleSessionInvalid($request, $mensagem);
                }
            }

            // Se não tem token na sessão mas tem no BD, sincronizar automaticamente
            if (!$sessionTokenFromSession && $sessionTokenFromDB) {
                session(['session_token' => $sessionTokenFromDB]);
                session(['login_time' => now()->timestamp]);
                return $next($request);
            }

            // Se tokens são diferentes, sincronizar da base de dados em vez de
            // derrubar a sessão.  Isso evita o "ping-pong" de logouts quando o
            // mesmo usuário acessa de dois dispositivos/redes (IPv4 vs IPv6) ou
            // quando o token rotaciona por outro login ativo.
            if ($sessionTokenFromDB && $sessionTokenFromSession && $sessionTokenFromDB !== $sessionTokenFromSession) {
                Log::info('VerifyUniqueSession: token re-sincronizado (outro login detectado)', [
                    'user_id' => $user->id_usuario,
                    'ip' => $request->ip(),
                ]);
                session(['session_token' => $sessionTokenFromDB]);
                session(['login_time' => now()->timestamp]);
                return $next($request);
            }
        }

        return $next($request);
    }
    
    /**
     * Tratar sessão inválida
     */
    private function handleSessionInvalid(Request $request, string $message)
    {
        Auth::logout();
        Cookie::queue(Cookie::forget('session_token'));
        
        // Para requisições AJAX, retornar erro JSON sem destruir a sessão completamente
        if ($request->expectsJson() || $request->ajax()) {
            session()->forget('session_token');
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect' => route('login.form')
            ], 401);
        }
        
        // Para requisições normais, invalidar sessão completamente e redirecionar
        $request->session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookie = Cookie::make('session_warning', $message, 10);
        return redirect()->route('login.form')->withCookie($cookie);
    }
}
