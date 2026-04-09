<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Domain\Auth\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class SimpleAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Se já está autenticado pelo Laravel, apenas garantir consistência
        if (Auth::check()) {
            $user = Auth::user();

            // Recarregar session_token do banco para garantir valor mais recente
            // (evita cache do modelo Eloquent devolver valor desatualizado)
            $user->refresh();

            // Regra simples: usuário logado SEM token persistido no banco deve ser bloqueado
            if (!$user || empty($user->session_token)) {
                return $this->handleUnauthenticated($request, 'Sessão inválida. Faça login novamente.');
            }
            
            // Se o usuário tem session_token no banco, sincronizar com a sessão
            if ($user && $user->session_token) {
                $sessionToken = session('session_token');
                
                // Se não tem na sessão ou é diferente, atualizar
                if (!$sessionToken || $sessionToken !== $user->session_token) {
                    session(['session_token' => $user->session_token]);
                    session(['login_time' => now()->timestamp]);
                    $this->queueSessionTokenCookie($request, $user->session_token);
                }
            }

            // Garantir id_empresa sempre presente na sessão em todas as telas protegidas
            $this->syncEmpresaSession($request, $user);
            
            return $next($request);
        }
        
        // Se não está autenticado, tentar recuperar via session_token
        $sessionToken = session('session_token');
        $cookieToken = Cookie::get('session_token');

        if (!$sessionToken && $cookieToken) {
            $sessionToken = $cookieToken;
            session(['session_token' => $sessionToken]);
        }
        
        if ($sessionToken) {
            // Tentar encontrar usuário com esse token
            $usuario = Usuario::where('session_token', $sessionToken)->first();
            
            if ($usuario) {
                // Fazer login e continuar
                Auth::login($usuario, true);
                $this->syncEmpresaSession($request, $usuario);
                $this->queueSessionTokenCookie($request, $sessionToken);
                return $next($request);
            }
        }
        
        // Última tentativa: verificar via remember token do Laravel
        if (Auth::viaRemember()) {
            $user = Auth::user();
            if ($user && $user->session_token) {
                session(['session_token' => $user->session_token]);
                session(['login_time' => now()->timestamp]);
                $this->syncEmpresaSession($request, $user);
                $this->queueSessionTokenCookie($request, $user->session_token);
                return $next($request);
            }
        }
        
        // Não conseguiu autenticar
        return $this->handleUnauthenticated($request, 'Sessão não encontrada.');
    }
    
    /**
     * Tratar requisição não autenticada
     */
    private function handleUnauthenticated(Request $request, string $message)
    {
        // Para requisições AJAX, retornar erro JSON sem destruir completamente a sessão
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'redirect' => route('login.form')
            ], 401);
        }
        
        // Para requisições normais, limpar sessão e redirecionar
        Auth::logout();
        session()->flush();
        Cookie::queue(Cookie::forget('session_token'));
        return redirect()->route('login.form');
    }

    private function queueSessionTokenCookie(Request $request, string $sessionToken): void
    {
        $secure = $request->isSecure();
        Cookie::queue('session_token', $sessionToken, 60 * 24 * 30, '/', null, $secure, true, false, 'lax');
    }

    /**
     * Sincroniza os dados mínimos da empresa/usuário na sessão.
     */
    private function syncEmpresaSession(Request $request, $user): void
    {
        if (!$user) {
            return;
        }

        $isSuporte = (int) ($user->is_suporte ?? $user->isSuporte ?? 0) === 1;
        $idEmpresaSessao = (int) session('id_empresa', 0);
        $idEmpresaUsuario = (int) ($user->id_empresa ?? 0);

        if ($isSuporte) {
            $idEmpresaSelecionadaSessao = (int) session('id_empresa_selecionada', 0);
            $idEmpresaSelecionadaCookie = (int) ($request->cookie('id_empresa_suporte') ?: 0);
            $idEmpresaSelecionada = $idEmpresaSelecionadaSessao > 0
                ? $idEmpresaSelecionadaSessao
                : $idEmpresaSelecionadaCookie;

            if ($idEmpresaSessao <= 0) {
                $idEmpresaAlvo = $idEmpresaSelecionada > 0 ? $idEmpresaSelecionada : $idEmpresaUsuario;
                if ($idEmpresaAlvo > 0) {
                    session(['id_empresa' => $idEmpresaAlvo]);
                    $idEmpresaSessao = $idEmpresaAlvo;
                }
            }

            // Se a sessão foi recriada e voltou para a empresa base do suporte, reaplica a filial escolhida.
            if ($idEmpresaSelecionada > 0 && $idEmpresaSessao === $idEmpresaUsuario && $idEmpresaSelecionada !== $idEmpresaSessao) {
                session(['id_empresa' => $idEmpresaSelecionada]);
                $idEmpresaSessao = $idEmpresaSelecionada;
            }

            if ($idEmpresaSessao > 0) {
                session(['id_empresa_selecionada' => $idEmpresaSessao]);
            }
        } elseif ($idEmpresaUsuario > 0) {
            // Usuario comum sempre fica na propria empresa.
            session(['id_empresa' => $idEmpresaUsuario]);
            session()->forget('id_empresa_selecionada');
        }

        if (!empty($user->id_usuario)) {
            session(['user_id' => (int) $user->id_usuario]);
        }
    }
}