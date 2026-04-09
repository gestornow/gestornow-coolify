<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthSuporteMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se o usuário está autenticado
        if (!session('session_token')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Não autenticado'], 401);
            }
            return redirect()->route('login');
        }

        // Buscar o usuário atual na sessão
        $userId = session('id_usuario');
        if (!$userId) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Usuário não encontrado na sessão'], 401);
            }
            return redirect()->route('login');
        }

        // Buscar o usuário no banco
        $usuario = \App\Domain\Auth\Models\Usuario::find($userId);
        if (!$usuario) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Usuário não encontrado'], 401);
            }
            return redirect()->route('login');
        }

        // Verificar se é usuário de suporte
        // Usar a mesma lógica da navbar para compatibilidade
        $isSuporteField = $usuario->is_suporte ?? $usuario->isSuporte ?? 0;
        if ($isSuporteField != 1) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Acesso negado. Apenas usuários de suporte podem acessar esta área.'], 403);
            }
            
            return redirect()->back()->with('error', 'Acesso negado. Apenas usuários de suporte podem acessar esta área.');
        }

        return $next($request);
    }
}