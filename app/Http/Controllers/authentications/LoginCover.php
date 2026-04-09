<?php

namespace App\Http\Controllers\authentications;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\AlterarSenhaRequest;
use App\Http\Requests\Auth\RecuperarSenhaRequest;
use App\Http\Requests\Auth\RedefinirSenhaRequest;
use App\Domain\Auth\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class LoginCover extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
        $this->middleware('guest')->except(['logout', 'alterarSenha']);
        $this->middleware('auth')->only(['logout', 'alterarSenha']);
    }

    public function index(): View
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('content.authentications.auth-login-cover', ['pageConfigs' => $pageConfigs]);
    }

    public function showLoginForm(): View
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('content.authentications.auth-login-cover', ['pageConfigs' => $pageConfigs]);
    }

    public function login(LoginRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $resultado = $this->authService->tentarLogin(
                $request->login,
                $request->senha,
                $request->lembrar
            );

            // Armazenar session token na sessão
            session(['session_token' => $resultado['session_token']]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['message'],
                    'data' => [
                        'usuario' => $resultado['usuario'],
                        'session_token' => $resultado['session_token'],
                        'redirect_url' => route('dashboard-analytics')
                    ]
                ]);
            }

            return redirect()
                ->intended(route('dashboard-analytics'))
                ->with('success', $resultado['message']);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 401);
            }

            return back()
                ->withInput($request->only('login', 'lembrar'))
                ->withErrors(['login' => $e->getMessage()]);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        try {
            $this->authService->logout();
            
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Logout realizado com sucesso'
                ]);
            }

            return redirect()
                ->route('login.form')
                ->with('success', 'Logout realizado com sucesso');

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }

            return redirect()
                ->route('login.form')
                ->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function showRecuperarSenhaForm(): View
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('auth.recuperar-senha', ['pageConfigs' => $pageConfigs]);
    }

    public function recuperarSenha(RecuperarSenhaRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $resultado = $this->authService->enviarLinkRecuperacao($request->email);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['message']
                ]);
            }

            return back()->with('success', $resultado['message']);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $e->getMessage()]);
        }
    }

    public function showRedefinirSenhaForm(string $codigo): View
    {
        $pageConfigs = ['myLayout' => 'blank'];
        return view('auth.redefinir-senha', [
            'codigo' => $codigo,
            'pageConfigs' => $pageConfigs
        ]);
    }

    public function redefinirSenha(RedefinirSenhaRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $resultado = $this->authService->redefinirSenha(
                $request->codigo,
                $request->senha
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['message']
                ]);
            }

            return redirect()
                ->route('login.form')
                ->with('success', $resultado['message']);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()
                ->withInput($request->only('codigo'))
                ->withErrors(['codigo' => $e->getMessage()]);
        }
    }

    public function alterarSenha(AlterarSenhaRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $resultado = $this->authService->alterarSenha(
                $request->senha_atual,
                $request->senha_nova
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $resultado['message']
                ]);
            }

            return back()->with('success', $resultado['message']);

        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 422);
            }

            return back()->withErrors(['senha_atual' => $e->getMessage()]);
        }
    }
}
