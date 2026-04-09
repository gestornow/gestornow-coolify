<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Domain\Auth\Models\Usuario;

class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Verificar se há token de sessão
        $sessionToken = session('session_token');
        
        if (!$sessionToken) {
            return redirect()->route('login.form');
        }
        
        // Verificar se o usuário existe com esse token
        $usuario = Usuario::where('session_token', $sessionToken)->first();
        
        if (!$usuario) {
            session()->forget('session_token');
            return redirect()->route('login.form');
        }
        
        // Se o usuário não está autenticado pelo Laravel Auth, fazer login
        if (!Auth::check() || Auth::id() != $usuario->id_usuario) {
            Auth::login($usuario);
        }
        
        // Se estiver autenticado, vai para dashboard
        return redirect('/dashboard');
    }
}
