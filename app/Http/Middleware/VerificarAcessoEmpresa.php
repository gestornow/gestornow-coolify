<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Domain\Auth\Models\Empresa;

class VerificarAcessoEmpresa
{
    /**
     * Rotas que não precisam de verificação de plano
     */
    protected array $exceto = [
        'dashboard',
        'dashboard-analytics',
        'logout',
        'planos.*',
        'billing.*',
        'onboarding.*',
        'admin.planos.*',
        'contratar.*',
        'trocar-filial',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->rotaLiberada($request)) {
            return $next($request);
        }

        // Se não estiver autenticado, deixa passar (vai cair no middleware de auth)
        if (!session('id_empresa')) {
            return $next($request);
        }

        $empresa = Empresa::find(session('id_empresa'));

        if (!$empresa) {
            return $next($request);
        }

        // Se estiver bloqueado, redireciona com mensagem
        if ($empresa->isBloqueada()) {
            // Permitir apenas acesso ao dashboard (onde verá opção de contratar)
            if (!$this->rotaLiberada($request)) {
                return redirect()
                    ->route('dashboard')
                    ->with('warning', 'Seu período de teste expirou. Escolha um plano para continuar usando o sistema.');
            }
        }

        // Se estiver em teste e expirou
        if ($empresa->status === 'teste' && $empresa->testeExpirado()) {
            // O cron deveria já ter bloqueado, mas por segurança, bloqueia no acesso
            $empresa->update([
                'status' => 'teste bloqueado',
                'data_bloqueio' => now(),
            ]);

            if (!$this->rotaLiberada($request)) {
                return redirect()
                    ->route('dashboard')
                    ->with('warning', 'Seu período de teste expirou. Escolha um plano para continuar usando o sistema.');
            }
        }

        // Verifica se a empresa tem plano ativo (não está em teste e não tem plano)
        if ($empresa->status === 'ativo' && $empresa->semPlanoAtivo()) {
            if (!$this->rotaLiberada($request)) {
                return redirect()
                    ->route('dashboard')
                    ->with('warning', 'Você não possui um plano ativo. Escolha um plano para continuar usando o sistema.');
            }
        }

        return $next($request);
    }

    private function rotaLiberada(Request $request): bool
    {
        if ($request->is('trocar-filial')) {
            return true;
        }

        // Usuário de suporte pode acessar rotas administrativas sem bloqueio por plano da filial selecionada.
        if ($request->routeIs('admin.*') && $this->usuarioSuporte()) {
            return true;
        }

        return $request->routeIs(...$this->exceto);
    }

    private function usuarioSuporte(): bool
    {
        $usuario = auth()->user();

        if (!$usuario) {
            return false;
        }

        return (int) ($usuario->is_suporte ?? $usuario->isSuporte ?? 0) === 1;
    }
}
