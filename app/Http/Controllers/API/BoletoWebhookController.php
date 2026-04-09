<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Boleto\BoletoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class BoletoWebhookController extends Controller
{
    protected BoletoService $boletoService;

    public function __construct(BoletoService $boletoService)
    {
        $this->boletoService = $boletoService;
    }

    /**
     * Recebe webhook do Banco Inter.
     */
    public function inter(Request $request): JsonResponse
    {
        try {
            $input = $request->getContent();
            $data = json_decode($input, true);

            // Registrar recebimento do webhook
            Log::info('Webhook Inter recebido', ['content' => $input]);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::warning('Webhook Inter com JSON inválido', [
                    'erro' => json_last_error_msg(),
                    'content' => $input,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'JSON inválido',
                ], 400);
            }

            // Processar webhook
            $this->boletoService->processarWebhook('inter', $data);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processado com sucesso',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao processar webhook Inter: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar webhook',
            ], 500);
        }
    }

    /**
     * Recebe webhook do Asaas.
     */
    public function asaas(Request $request): JsonResponse
    {
        try {
            $input = $request->getContent();
            $data = json_decode($input, true);

            // Registrar recebimento do webhook
            Log::info('Webhook Asaas recebido', ['content' => $input]);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                Log::warning('Webhook Asaas com JSON inválido', [
                    'erro' => json_last_error_msg(),
                    'content' => $input,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'JSON inválido',
                ], 400);
            }

            // Processar webhook
            $this->boletoService->processarWebhook('asaas', $data);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processado com sucesso',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao processar webhook Asaas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar webhook',
            ], 500);
        }
    }

    /**
     * Recebe webhook do Mercado Pago (topic: order).
     */
    public function mercadoPago(Request $request): JsonResponse
    {
        try {
            $input = $request->getContent();
            $data = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $fallback = $request->all();
                if (is_array($fallback) && $fallback !== []) {
                    $data = $fallback;
                }
            }

            Log::info('Webhook Mercado Pago recebido', ['content' => $input]);

            if (!is_array($data) || $data === []) {
                Log::warning('Webhook Mercado Pago com JSON invalido', [
                    'erro' => json_last_error_msg(),
                    'content' => $input,
                    'fallback' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'JSON invalido',
                ], 400);
            }

            $this->boletoService->processarWebhook('mercado_pago', $data);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processado com sucesso',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao processar webhook Mercado Pago: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar webhook',
            ], 500);
        }
    }

    /**
     * Recebe webhook do PagHiper.
     */
    public function pagHiper(Request $request): JsonResponse
    {
        try {
            $input = $request->getContent();
            $data = json_decode($input, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                $fallback = $request->all();
                if (is_array($fallback) && $fallback !== []) {
                    $data = $fallback;
                }
            }

            Log::info('Webhook PagHiper recebido', ['content' => $input]);

            if (!is_array($data) || $data === []) {
                Log::warning('Webhook PagHiper com JSON invalido', [
                    'erro' => json_last_error_msg(),
                    'content' => $input,
                    'fallback' => $request->all(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'JSON invalido',
                ], 400);
            }

            $this->boletoService->processarWebhook('paghiper', $data);

            return response()->json([
                'success' => true,
                'message' => 'Webhook processado com sucesso',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao processar webhook PagHiper: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar webhook',
            ], 500);
        }
    }
}
