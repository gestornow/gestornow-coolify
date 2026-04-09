<?php

namespace App\Http\Middleware;

use App\Models\AssinaturaPlano;
use App\Services\Billing\AssinaturaPlanoService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerificarOnboardingAssinatura
{
    protected array $rotasLiberadas = [
        'logout',
        'planos.*',
        'billing.*',
        'onboarding.*',
        'trocar-filial',
        'csrf-token',
    ];

    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->rotaLiberada($request)) {
            return $next($request);
        }

        $usuario = auth()->user();
        if (!$usuario) {
            return $next($request);
        }

        $isSuporte = (int) ($usuario->is_suporte ?? $usuario->isSuporte ?? 0) === 1;
        if ($isSuporte) {
            return $next($request);
        }

        $idEmpresa = (int) (session('id_empresa') ?: $usuario->id_empresa);
        if ($idEmpresa <= 0) {
            return $next($request);
        }

        $assinatura = $this->assinaturaPlanoService->obterAssinaturaEmpresa($idEmpresa);
        if (!$assinatura) {
            return $next($request);
        }

        if ($assinatura->status === AssinaturaPlano::STATUS_ONBOARDING_DADOS) {
            return redirect()
                ->route('onboarding.index')
                ->with('warning', 'Complete os dados cadastrais para liberar o acesso ao sistema.');
        }

        if ($assinatura->status === AssinaturaPlano::STATUS_ONBOARDING_CONTRATO) {
            return redirect()
                ->route('onboarding.index')
                ->with('warning', 'Assine o contrato digital para liberar o acesso ao sistema.');
        }

        if ($assinatura->status === AssinaturaPlano::STATUS_PENDENTE_PAGAMENTO) {
            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('warning', 'Seu pagamento de adesão está pendente. Regularize em Meu Financeiro para continuar.');
        }

        return $next($request);
    }

    private function rotaLiberada(Request $request): bool
    {
        if ($request->is('trocar-filial')) {
            return true;
        }

        return $request->routeIs(...$this->rotasLiberadas);
    }
}
