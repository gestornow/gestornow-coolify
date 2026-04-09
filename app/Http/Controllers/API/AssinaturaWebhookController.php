<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Billing\AssinaturaPlanoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AssinaturaWebhookController extends Controller
{
    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService
    ) {
    }

    public function asaas(Request $request): JsonResponse
    {
        try {
            $tokenEsperado = trim((string) config('services.asaas.webhook_token', ''));
            if ($tokenEsperado !== '') {
                $tokenRecebido = (string) (
                    $request->header('asaas-access-token')
                    ?? $request->header('x-asaas-access-token')
                    ?? ''
                );

                if (!hash_equals($tokenEsperado, $tokenRecebido)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Webhook não autorizado.',
                    ], 401);
                }
            }

            $payload = $request->json()->all();

            if (!is_array($payload) || empty($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payload inválido.',
                ], 400);
            }

            $this->assinaturaPlanoService->processarWebhookAsaas($payload);

            return response()->json([
                'success' => true,
                'message' => 'Webhook de assinatura processado com sucesso.',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao processar webhook Asaas de assinatura', [
                'erro' => $e->getMessage(),
                'payload' => $request->getContent(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar webhook de assinatura.',
            ], 500);
        }
    }
}
