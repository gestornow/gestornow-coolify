<?php

namespace App\Services\Integracoes\Cobli;

use App\Models\EmpresaIntegracaoCobli;
use Exception;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CobliGatewayService
{
    private string $defaultBaseUrl;
    private int $defaultTimeout;
    private ?string $defaultApiKey;

    public function __construct()
    {
        $this->defaultBaseUrl = rtrim((string) config('services.cobli.base_url', 'https://api.cobli.co'), '/');
        $this->defaultTimeout = max(1, (int) config('services.cobli.timeout', 30));

        $token = trim((string) config('services.cobli.api_key', ''));
        $this->defaultApiKey = $token !== '' ? $token : null;
    }

    public function isConfigured(?int $idEmpresa = null): bool
    {
        $config = $this->resolveConfiguration($idEmpresa);

        return !empty($config['api_key']);
    }

    public function testConnection(?int $idEmpresa = null): array
    {
        $resolved = $this->resolveConfiguration($idEmpresa);

        if (empty($resolved['api_key'])) {
            throw new Exception($this->buildNotConfiguredMessage($idEmpresa));
        }

        try {
            $result = $this->request('GET', '/public/v1/vehicles', [
                'limit' => 1,
                'page' => 1,
            ], $idEmpresa);

            $this->registerTestResult($resolved['record'], null);

            return [
                'ok' => true,
                'empresa_id' => $idEmpresa,
                'base_url' => $result['base_url'],
                'status' => $result['status'],
                'response_keys' => is_array($result['data']) ? array_keys($result['data']) : [],
            ];
        } catch (Exception $exception) {
            $this->registerTestResult($resolved['record'], $exception->getMessage());

            throw $exception;
        }
    }

    public function listVehicles(?int $idEmpresa = null, int $limit = 100, int $page = 1): array
    {
        $result = $this->request('GET', '/public/v1/vehicles', [
            'limit' => max(1, min($limit, 2000)),
            'page' => max(1, $page),
        ], $idEmpresa);

        return $result['data'];
    }

    public function listDrivers(?int $idEmpresa = null, int $limit = 100, int $page = 1): array
    {
        $result = $this->request('GET', '/public/v1/drivers', [
            'limit' => max(1, min($limit, 2000)),
            'page' => max(1, $page),
        ], $idEmpresa);

        return $result['data'];
    }

    public function listRoutes(?int $idEmpresa = null, array $query = []): array
    {
        $result = $this->request('GET', '/public/v2/routes', $query, $idEmpresa);

        return $result['data'];
    }

    public function getMaintenanceHistory(?int $idEmpresa = null, array $payload = []): array
    {
        $result = $this->request('POST', '/analytics/v1/maintenance/history', $payload, $idEmpresa);

        return $result['data'];
    }

    private function request(string $method, string $path, array $payload = [], ?int $idEmpresa = null): array
    {
        $resolved = $this->resolveConfiguration($idEmpresa);

        if (empty($resolved['api_key'])) {
            throw new Exception($this->buildNotConfiguredMessage($idEmpresa));
        }

        $http = Http::baseUrl($resolved['base_url'])
            ->timeout($resolved['timeout'])
            ->acceptJson()
            ->withHeaders([
                'cobli-api-key' => $resolved['api_key'],
            ]);

        $response = match (strtoupper($method)) {
            'GET' => $http->get($path, $payload),
            'POST' => $http->asJson()->post($path, $payload),
            'PUT' => $http->asJson()->put($path, $payload),
            'PATCH' => $http->asJson()->patch($path, $payload),
            'DELETE' => $http->delete($path, $payload),
            default => throw new Exception('Metodo HTTP nao suportado na integracao Cobli: ' . $method),
        };

        if ($response->failed()) {
            $mensagem = $this->extractErrorMessage($response, $path);

            Log::warning('Cobli request failed', [
                'empresa_id' => $idEmpresa,
                'method' => strtoupper($method),
                'path' => $path,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw new Exception($mensagem);
        }

        return [
            'status' => $response->status(),
            'data' => $response->json(),
            'base_url' => $resolved['base_url'],
            'record' => $resolved['record'],
        ];
    }

    private function resolveConfiguration(?int $idEmpresa = null): array
    {
        if ($idEmpresa !== null) {
            $record = EmpresaIntegracaoCobli::query()
                ->empresa($idEmpresa)
                ->first();

            $baseUrl = rtrim((string) ($record?->base_url ?: $this->defaultBaseUrl), '/');
            $apiKey = $record && $record->ativo ? $record->api_key_descriptografada : null;

            return [
                'record' => $record,
                'base_url' => $baseUrl,
                'timeout' => $this->defaultTimeout,
                'api_key' => $apiKey,
            ];
        }

        return [
            'record' => null,
            'base_url' => $this->defaultBaseUrl,
            'timeout' => $this->defaultTimeout,
            'api_key' => $this->defaultApiKey,
        ];
    }

    private function buildNotConfiguredMessage(?int $idEmpresa = null): string
    {
        if ($idEmpresa !== null) {
            return 'Integracao Cobli nao configurada ou inativa para a empresa #' . $idEmpresa . '.';
        }

        return 'Integracao Cobli nao configurada. Defina COBLI_API_KEY ou informe uma empresa com configuracao ativa.';
    }

    private function extractErrorMessage(Response $response, string $path): string
    {
        $body = $response->json();
        $status = $response->status();

        if (is_array($body)) {
            $candidatos = [
                $body['message'] ?? null,
                $body['error'] ?? null,
                $body['details'][0]['message'] ?? null,
                $body['errors'][0]['message'] ?? null,
            ];

            foreach ($candidatos as $candidato) {
                if (is_string($candidato) && trim($candidato) !== '') {
                    return 'Erro Cobli (' . $status . '): ' . trim($candidato);
                }
            }
        }

        return 'Erro Cobli (' . $status . ') ao acessar ' . $path . '.';
    }

    private function registerTestResult(?EmpresaIntegracaoCobli $record, ?string $error = null): void
    {
        if (!$record) {
            return;
        }

        $record->forceFill([
            'ultimo_teste_em' => now(),
            'ultimo_erro' => $error,
        ])->save();
    }
}