<?php

namespace App\Services\Billing;

use App\Domain\Auth\Models\Empresa;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AsaasGatewayService
{
    private string $baseUrl;
    private ?string $accessToken;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.asaas.base_url', 'https://api.asaas.com'), '/');

        $token = trim((string) config('services.asaas.api_key', ''));
        $this->accessToken = $token !== '' ? $token : null;
    }

    public function isConfigured(): bool
    {
        return !empty($this->accessToken);
    }

    public function createOrGetCustomer(Empresa $empresa): string
    {
        $cpfCnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf));
        if (empty($cpfCnpj)) {
            throw new Exception('CPF/CNPJ da empresa não informado para cadastro no Asaas.');
        }

        $search = $this->request('GET', '/v3/customers', [
            'cpfCnpj' => $cpfCnpj,
        ]);

        $data = $search['data']['data'] ?? [];
        if (!empty($data[0]['id'])) {
            return (string) $data[0]['id'];
        }

        $nome = trim((string) ($empresa->razao_social ?: $empresa->nome_empresa));
        if ($nome === '') {
            throw new Exception('Razão social/nome da empresa não informado.');
        }

        $payload = [
            'name' => Str::limit($nome, 200, ''),
            'cpfCnpj' => $cpfCnpj,
            'email' => Str::limit((string) ($empresa->email ?: ''), 255, ''),
            'mobilePhone' => preg_replace('/\D/', '', (string) ($empresa->telefone ?: '')),
            'address' => Str::limit((string) ($empresa->endereco ?: ''), 255, ''),
            'addressNumber' => Str::limit((string) ($empresa->numero ?: 'S/N'), 20, ''),
            'complement' => Str::limit((string) ($empresa->complemento ?: ''), 100, ''),
            'province' => Str::limit((string) ($empresa->bairro ?: ''), 60, ''),
            'postalCode' => preg_replace('/\D/', '', (string) ($empresa->cep ?: '')),
            'externalReference' => 'EMPRESA:' . (int) $empresa->id_empresa,
        ];

        $payload = array_filter($payload, function ($value) {
            return $value !== null && $value !== '';
        });

        $created = $this->request('POST', '/v3/customers', $payload);
        $customerId = $created['data']['id'] ?? null;

        if (!$customerId) {
            throw new Exception('Não foi possível criar cliente no Asaas.');
        }

        return (string) $customerId;
    }

    public function createPayment(array $payload): array
    {
        return $this->request('POST', '/v3/payments', $payload)['data'];
    }

    public function createSubscription(array $payload): array
    {
        return $this->request('POST', '/v3/subscriptions', $payload)['data'];
    }

    public function updateSubscription(string $subscriptionId, array $payload): array
    {
        return $this->request('PUT', '/v3/subscriptions/' . $subscriptionId, $payload)['data'];
    }

    /**
     * Atualiza o cartão de crédito de uma assinatura existente.
     * Tenta múltiplos endpoints em ordem de prioridade para compatibilidade entre contas.
     */
    public function updateSubscriptionCreditCard(string $subscriptionId, array $creditCard, array $creditCardHolderInfo, string $remoteIp): array
    {
        $payloadBase = [
            'creditCard' => $creditCard,
            'creditCardHolderInfo' => $creditCardHolderInfo,
            'remoteIp' => $remoteIp,
        ];

        $attempts = [
            [
                'name' => 'put_subscription_credit_card',
                'method' => 'PUT',
                'endpoint' => '/v3/subscriptions/' . $subscriptionId . '/creditCard',
                'payload' => $payloadBase,
            ],
            [
                'name' => 'post_subscription_update_credit_card',
                'method' => 'POST',
                'endpoint' => '/v3/subscriptions/' . $subscriptionId . '/updateCreditCard',
                'payload' => $payloadBase,
            ],
        ];

        $falhas = [];

        foreach ($attempts as $attempt) {
            $result = $this->request(
                $attempt['method'],
                $attempt['endpoint'],
                $attempt['payload'],
                false
            );

            $status = (int) ($result['status'] ?? 0);
            $body = is_array($result['data'] ?? null) ? $result['data'] : [];

            if ($status >= 200 && $status < 300) {
                Log::info('Asaas card update successful', [
                    'strategy' => $attempt['name'],
                    'method' => $attempt['method'],
                    'endpoint' => $attempt['endpoint'],
                    'status' => $status,
                    'payload' => $this->mascaraPayloadParaLog($attempt['payload']),
                ]);

                return $body;
            }

            $mensagemErro = $this->extrairMensagemErroAsaas($body, $status);

            $falhas[] = [
                'strategy' => $attempt['name'],
                'method' => $attempt['method'],
                'endpoint' => $attempt['endpoint'],
                'status' => $status,
                'message' => $mensagemErro,
                'response' => $body,
            ];

            Log::warning('Asaas card update attempt failed', [
                'strategy' => $attempt['name'],
                'method' => $attempt['method'],
                'endpoint' => $attempt['endpoint'],
                'status' => $status,
                'message' => $mensagemErro,
                'payload' => $this->mascaraPayloadParaLog($attempt['payload']),
                'response' => $body,
            ]);
        }

        $mensagemFinal = 'Não foi possível atualizar o cartão da assinatura no Asaas.';
        foreach ($falhas as $falha) {
            if (!empty($falha['message'])) {
                $mensagemFinal = (string) $falha['message'];
                break;
            }
        }

        Log::error('Asaas card update failed on all endpoint strategies', [
            'subscription_id' => $subscriptionId,
            'attempts' => $falhas,
            'base_payload' => $this->mascaraPayloadParaLog($payloadBase),
        ]);

        throw new Exception($mensagemFinal);
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->request('DELETE', '/v3/subscriptions/' . $subscriptionId)['data'];
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->request('GET', '/v3/subscriptions/' . $subscriptionId)['data'];
    }

    public function getSubscriptionPayments(string $subscriptionId): array
    {
        $result = $this->request('GET', '/v3/subscriptions/' . $subscriptionId . '/payments', [], false);

        return $result['data']['data'] ?? [];
    }

    public function tokenizeCreditCard(string $customerId, array $cardData, array $holderInfo): array
    {
        $payload = [
            'customer' => $customerId,
            'creditCard' => [
                'holderName' => $cardData['holderName'],
                'number' => preg_replace('/\D/', '', $cardData['number']),
                'expiryMonth' => $cardData['expiryMonth'],
                'expiryYear' => $cardData['expiryYear'],
                'ccv' => $cardData['ccv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $holderInfo['name'],
                'email' => $holderInfo['email'] ?? '',
                'cpfCnpj' => preg_replace('/\D/', '', $holderInfo['cpfCnpj'] ?? ''),
                'postalCode' => preg_replace('/\D/', '', $holderInfo['postalCode'] ?? ''),
                'addressNumber' => $holderInfo['addressNumber'] ?? 'S/N',
                'phone' => preg_replace('/\D/', '', $holderInfo['phone'] ?? ''),
            ],
        ];

        return $this->request('POST', '/v3/creditCard/tokenize', $payload)['data'];
    }

    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/v3/payments/' . $paymentId)['data'];
    }

    public function deletePayment(string $paymentId): array
    {
        return $this->request('DELETE', '/v3/payments/' . $paymentId)['data'];
    }

    public function payWithCreditCard(string $paymentId, array $creditCard, array $creditCardHolderInfo, string $remoteIp): array
    {
        $payload = [
            'creditCard' => $creditCard,
            'creditCardHolderInfo' => $creditCardHolderInfo,
            'remoteIp' => $remoteIp,
        ];

        return $this->request('POST', '/v3/payments/' . $paymentId . '/payWithCreditCard', $payload)['data'];
    }

    public function getPixQrCode(string $paymentId): ?array
    {
        $result = $this->request('GET', '/v3/payments/' . $paymentId . '/pixQrCode', [], false);

        if (($result['status'] ?? 0) >= 200 && ($result['status'] ?? 0) < 300) {
            return $result['data'];
        }

        return null;
    }

    public function normalizeBillingType(string $metodo): string
    {
        $upper = strtoupper(trim($metodo));

        return match ($upper) {
            'CARTAO_CREDITO', 'CREDITO', 'CREDIT_CARD' => 'CREDIT_CARD',
            'CARTAO_DEBITO', 'DEBITO', 'DEBIT_CARD' => 'DEBIT_CARD',
            'PIX' => 'PIX',
            default => 'BOLETO',
        };
    }

    private function request(string $method, string $endpoint, array $payload = [], bool $throwOnError = true): array
    {
        if (!$this->isConfigured()) {
            throw new Exception('ASAAS API Key não configurada (services.asaas.api_key).');
        }

        $http = Http::withHeaders([
            'access_token' => (string) $this->accessToken,
            'Content-Type' => 'application/json',
            'User-Agent' => 'GestorNow/2.0',
        ])->timeout(60);

        $url = $this->baseUrl . $endpoint;

        $response = match (strtoupper($method)) {
            'GET' => $http->get($url, $payload),
            'POST' => $http->post($url, $payload),
            'PUT' => $http->put($url, $payload),
            'DELETE' => $http->delete($url, $payload),
            default => throw new Exception('Método HTTP não suportado para Asaas: ' . $method),
        };

        $body = $response->json();
        $status = $response->status();

        if ($throwOnError && !$response->successful()) {
            $mensagem = $this->extrairMensagemErroAsaas(is_array($body) ? $body : [], $status);

            Log::error('Asaas API Error', [
                'method' => $method,
                'endpoint' => $endpoint,
                'url' => $url,
                'status' => $status,
                'payload' => $this->mascaraPayloadParaLog($payload),
                'response' => $body,
            ]);

            throw new Exception((string) $mensagem);
        }

        return [
            'status' => $status,
            'data' => is_array($body) ? $body : [],
        ];
    }

    private function extrairMensagemErroAsaas(array $body, int $status): string
    {
        $errors = data_get($body, 'errors', []);
        if (is_array($errors) && count($errors) > 0) {
            $partes = [];
            foreach ($errors as $err) {
                $code = $err['code'] ?? '';
                $desc = $err['description'] ?? '';
                $partes[] = $code !== '' ? "[{$code}] {$desc}" : $desc;
            }

            $mensagem = implode(' | ', array_filter($partes));
            if ($mensagem !== '') {
                return $mensagem;
            }
        }

        $mensagem = (string) data_get($body, 'message', '');
        if ($mensagem !== '') {
            return $mensagem;
        }

        return 'Erro na API Asaas. HTTP ' . $status;
    }

    private function mascaraPayloadParaLog(array $payload): array
    {
        $masked = $payload;

        if (isset($masked['creditCard']['number'])) {
            $num = $masked['creditCard']['number'];
            $masked['creditCard']['number'] = str_repeat('*', max(0, strlen($num) - 4)) . substr($num, -4);
        }
        if (isset($masked['creditCard']['ccv'])) {
            $masked['creditCard']['ccv'] = '***';
        }

        return $masked;
    }

    /**
     * Lista pagamentos pendentes de uma assinatura de cartão.
     */
    public function listPendingPayments(string $subscriptionId): array
    {
        $payments = $this->getSubscriptionPayments($subscriptionId);

        return array_filter($payments, function ($payment) {
            $status = strtoupper((string) ($payment['status'] ?? ''));
            return in_array($status, ['PENDING', 'OVERDUE', 'AWAITING_RISK_ANALYSIS'], true);
        });
    }

    /**
     * Atualiza a data de vencimento de um payment (força re-processamento se for cartão).
     */
    public function updatePaymentDueDate(string $paymentId, string $dueDate): array
    {
        return $this->request('POST', '/v3/payments/' . $paymentId, [
            'dueDate' => $dueDate,
        ])['data'];
    }

    /**
     * Paga um payment usando token de cartão já salvo na assinatura.
     * Nota: Funciona apenas se o payment estiver em status que permite cobrança.
     */
    public function payWithCreditCardToken(string $paymentId, string $creditCardToken): array
    {
        $payload = [
            'creditCardToken' => $creditCardToken,
        ];

        return $this->request('POST', '/v3/payments/' . $paymentId . '/payWithCreditCard', $payload)['data'];
    }

    /**
     * Recupera informações da assinatura incluindo dados do cartão tokenizado.
     */
    public function getSubscriptionWithCard(string $subscriptionId): array
    {
        $subscription = $this->getSubscription($subscriptionId);
        
        return [
            'subscription' => $subscription,
            'creditCardToken' => (string) data_get($subscription, 'creditCard.creditCardToken', ''),
            'creditCardBrand' => (string) data_get($subscription, 'creditCard.creditCardBrand', ''),
            'creditCardNumber' => (string) data_get($subscription, 'creditCard.creditCardNumber', ''),
            'last4' => substr((string) data_get($subscription, 'creditCard.creditCardNumber', ''), -4) ?: '',
        ];
    }
}
