<?php

namespace App\Services\Boleto;

use App\Domain\Auth\Models\Empresa;
use App\Domain\Cliente\Models\Cliente;
use App\Models\BancoBoletoConfig;
use App\Models\Boleto;
use App\Models\BoletoHistorico;
use App\Models\ContasAReceber;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BancoMercadoPagoService
{
    protected ?BancoBoletoConfig $config;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct(?BancoBoletoConfig $config = null)
    {
        $this->config = $config;
        $this->baseUrl = rtrim((string) config('services.mercado_pago.base_url', 'https://api.mercadopago.com'), '/');
        $this->timeout = (int) config('services.mercado_pago.timeout', 60);
    }

    /**
     * Define a configuracao a ser usada.
     */
    public function setConfig(BancoBoletoConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Retorna Access Token do Mercado Pago.
     */
    protected function getAccessToken(): string
    {
        $tokenConfig = trim((string) optional($this->config)->api_key);
        if ($tokenConfig !== '') {
            return $tokenConfig;
        }

        $tokenEnv = trim((string) config('services.mercado_pago.access_token', ''));
        if ($tokenEnv !== '') {
            return $tokenEnv;
        }

        throw new Exception('Access Token do Mercado Pago nao configurado (campo API Key).');
    }

    /**
     * Faz requisicao para a API do Mercado Pago.
     */
    protected function request(string $method, string $endpoint, array $data = [], array $query = [], bool $usarIdempotencia = false): array
    {
        $token = $this->getAccessToken();
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
            'User-Agent: GestorNow/2.0',
        ];

        $idempotencyKey = null;
        if ($usarIdempotencia) {
            $idempotencyKey = (string) Str::uuid();
            $headers[] = 'X-Idempotency-Key: ' . $idempotencyKey;
        }

        $method = strtoupper($method);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            if ($method !== 'DELETE') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com API Mercado Pago: ' . $error);
        }

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true) ?? [],
            'raw' => $response,
            'idempotency_key' => $idempotencyKey,
        ];
    }

    /**
     * Gera boleto via Orders API do Mercado Pago.
     */
    public function gerarBoleto(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): Boleto
    {
        if (!$this->config) {
            throw new Exception('Configuracao de boleto nao definida.');
        }

        $payload = $this->montarPayloadOrder($conta, $empresa, $cliente);

        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $payload, 'etapa' => 'iniciando_mercado_pago_order']
        );

        $result = $this->request('POST', '/v1/orders', $payload, [], true);
        $data = is_array($result['response']) ? $result['response'] : [];

        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            [
                'request' => $payload,
                'response' => $data,
                'http_code' => $result['http_code'],
                'idempotency_key' => $result['idempotency_key'],
            ]
        );

        if (!in_array((int) $result['http_code'], [200, 201], true) || empty($data['id'])) {
            $mensagem = $this->extrairMensagemErroApi(
                $data,
                'Erro ao criar order de boleto no Mercado Pago.',
                is_string($result['raw'] ?? null) ? $result['raw'] : null
            );

            throw new Exception('Erro Mercado Pago (HTTP ' . (int) $result['http_code'] . '): ' . $mensagem);
        }

        $payment = $this->extrairPrimeiroPagamento($data);
        $paymentMethod = is_array($payment['payment_method'] ?? null) ? $payment['payment_method'] : [];

        $statusBoleto = $this->mapearStatusBoleto($data, $payment);
        $statusBanco = $this->extrairStatusBanco($data, $payment);
        $ticketUrl = $paymentMethod['ticket_url'] ?? null;

        $boleto = Boleto::create([
            'id_empresa' => $empresa->id_empresa,
            'id_conta_receber' => $conta->id_contas,
            'id_bancos' => $this->config->id_bancos,
            'id_banco_boleto' => $this->config->id_banco_boleto,
            'codigo_solicitacao' => (string) $data['id'],
            'nosso_numero' => $paymentMethod['reference'] ?? $payment['reference_id'] ?? null,
            'linha_digitavel' => $paymentMethod['digitable_line'] ?? null,
            'codigo_barras' => $paymentMethod['barcode_content'] ?? null,
            'valor_nominal' => (float) ($payment['amount'] ?? $data['total_amount'] ?? $conta->valor_total),
            'data_emissao' => now()->toDateString(),
            'data_vencimento' => $conta->data_vencimento,
            'status' => $statusBoleto,
            'situacao_banco' => $statusBanco,
            'url_pdf' => is_string($ticketUrl) && trim($ticketUrl) !== '' ? $ticketUrl : null,
            'json_resposta' => json_encode($data),
        ]);

        if ($statusBoleto === Boleto::STATUS_PAGO) {
            $valorPago = (float) ($payment['amount'] ?? $boleto->valor_nominal);
            $boleto->update([
                'valor_pago' => $valorPago,
                'data_pagamento' => now()->toDateTimeString(),
            ]);

            $this->atualizarContaReceberSePago($boleto, $valorPago);
        }

        BoletoHistorico::where('id_empresa', $empresa->id_empresa)
            ->whereNull('id_boleto')
            ->latest('id_historico')
            ->limit(2)
            ->update(['id_boleto' => $boleto->id_boleto]);

        Log::info('Boleto Mercado Pago gerado com sucesso', [
            'boleto_id' => $boleto->id_boleto,
            'order_id' => $data['id'],
            'conta_id' => $conta->id_contas,
        ]);

        return $boleto;
    }

    /**
     * Consulta order de boleto no Mercado Pago.
     */
    public function consultarBoleto(Boleto $boleto): array
    {
        if (!$this->config) {
            $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
                ->where('id_empresa', $boleto->id_empresa)
                ->first();

            if (!$config) {
                throw new Exception('Configuracao de boleto nao encontrada para consulta no Mercado Pago.');
            }

            $this->setConfig($config);
        }

        $result = $this->request('GET', '/v1/orders/' . urlencode((string) $boleto->codigo_solicitacao));
        $data = is_array($result['response']) ? $result['response'] : [];

        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            [
                'http_code' => $result['http_code'],
                'response' => $data,
            ]
        );

        if ((int) $result['http_code'] !== 200 || empty($data['id'])) {
            $mensagem = $this->extrairMensagemErroApi(
                $data,
                'Erro ao consultar order de boleto no Mercado Pago.',
                is_string($result['raw'] ?? null) ? $result['raw'] : null
            );

            throw new Exception('Erro Mercado Pago (HTTP ' . (int) $result['http_code'] . '): ' . $mensagem);
        }

        $atualizado = $this->atualizarBoletoComOrder($boleto, $data);

        return [
            'status' => $atualizado->status,
            'status_banco' => $atualizado->situacao_banco,
            'linha_digitavel' => $atualizado->linha_digitavel,
            'codigo_barras' => $atualizado->codigo_barras,
            'ticket_url' => $atualizado->url_pdf,
            'dados_completos' => $data,
        ];
    }

    /**
     * Tenta obter PDF do boleto.
     */
    public function obterPdf(Boleto $boleto): string
    {
        if (!$boleto->url_pdf) {
            $this->consultarBoleto($boleto);
            $boleto->refresh();
        }

        $ticketUrl = trim((string) $boleto->url_pdf);
        if ($ticketUrl === '') {
            throw new Exception('URL do boleto nao encontrada no Mercado Pago.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ticketUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new Exception('Erro ao baixar boleto no Mercado Pago: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('Nao foi possivel obter boleto no Mercado Pago. HTTP ' . $httpCode . '. URL: ' . $ticketUrl);
        }

        if (!str_contains(strtolower($contentType), 'application/pdf')) {
            throw new Exception('Mercado Pago nao retorna PDF binario para este boleto. Use a URL para pagamento: ' . $ticketUrl);
        }

        return $body;
    }

    /**
     * Cancelamento nao suportado neste fluxo.
     */
    public function cancelarBoleto(Boleto $boleto, string $motivo = 'Solicitado pelo cliente'): bool
    {
        throw new Exception('Cancelamento de boleto no Mercado Pago nao esta implementado neste fluxo de Orders API.');
    }

    /**
     * Alteracao de vencimento nao suportada neste fluxo.
     */
    public function alterarVencimento(Boleto $boleto, string $novaDataVencimento, float $novoValor): Boleto
    {
        throw new Exception('Alteracao de vencimento no Mercado Pago nao esta implementada neste fluxo de Orders API.');
    }

    /**
     * Processa webhook do topico Order.
     */
    public function processarWebhook(array $dados): void
    {
        Log::info('Processando webhook Mercado Pago', ['dados' => $dados]);

        $resourceId = $this->extrairResourceIdWebhook($dados);
        if ($resourceId === null) {
            Log::warning('Webhook Mercado Pago sem identificador de recurso', ['dados' => $dados]);
            return;
        }

        $boleto = $this->resolverBoletoWebhook($resourceId);
        if (!$boleto) {
            Log::warning('Boleto nao encontrado para webhook Mercado Pago', [
                'resource_id' => $resourceId,
            ]);
            return;
        }

        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_WEBHOOK,
            $dados
        );

        $boleto->update(['json_webhook' => json_encode($dados)]);

        $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
            ->where('id_empresa', $boleto->id_empresa)
            ->first();

        $orderId = $this->resolverOrderIdParaSincronizacao($resourceId, $boleto);
        if ($orderId === null) {
            Log::warning('Nao foi possivel resolver order id para webhook Mercado Pago', [
                'resource_id' => $resourceId,
                'boleto_id' => $boleto->id_boleto,
            ]);
            return;
        }

        // Se o webhook ja trouxe a order completa (order.processed), aplica imediatamente.
        $orderWebhook = $this->extrairOrderDoWebhook($dados, $orderId);
        if ($orderWebhook !== null) {
            try {
                $this->atualizarBoletoComOrder($boleto, $orderWebhook);
            } catch (\Throwable $e) {
                Log::warning('Falha ao aplicar payload de order do webhook Mercado Pago', [
                    'order_id' => $orderId,
                    'boleto_id' => $boleto->id_boleto,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        if (!$config) {
            Log::warning('Webhook Mercado Pago sem configuracao para consultar order', [
                'order_id' => $orderId,
                'boleto_id' => $boleto->id_boleto,
            ]);
            return;
        }

        $this->setConfig($config);

        try {
            $result = $this->request('GET', '/v1/orders/' . urlencode($orderId));
            $data = is_array($result['response']) ? $result['response'] : [];

            if ((int) $result['http_code'] === 200 && !empty($data['id'])) {
                $this->atualizarBoletoComOrder($boleto, $data);
            }
        } catch (Exception $e) {
            Log::warning('Falha ao sincronizar order no webhook Mercado Pago', [
                'order_id' => $orderId,
                'boleto_id' => $boleto->id_boleto,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    protected function atualizarBoletoComOrder(Boleto $boleto, array $order): Boleto
    {
        $payment = $this->extrairPrimeiroPagamento($order);
        $paymentMethod = is_array($payment['payment_method'] ?? null) ? $payment['payment_method'] : [];
        $paymentReference = is_array($payment['reference'] ?? null) ? $payment['reference'] : [];
        $status = $this->mapearStatusBoleto($order, $payment);

        $valorNominal = $this->toFloat($payment['amount'] ?? $order['total_amount'] ?? $boleto->valor_nominal);
        $valorPago = null;

        if ($status === Boleto::STATUS_PAGO) {
            $valorPago = $valorNominal > 0 ? $valorNominal : $this->toFloat($boleto->valor_nominal);
        }

        $updateData = [
            'status' => $status,
            'situacao_banco' => $this->extrairStatusBanco($order, $payment),
            'nosso_numero' => $paymentMethod['reference']
                ?? $payment['reference_id']
                ?? $paymentReference['id']
                ?? $boleto->nosso_numero,
            'linha_digitavel' => $paymentMethod['digitable_line'] ?? $boleto->linha_digitavel,
            'codigo_barras' => $paymentMethod['barcode_content'] ?? $boleto->codigo_barras,
            'url_pdf' => $paymentMethod['ticket_url'] ?? $boleto->url_pdf,
            'json_resposta' => json_encode($order),
            'valor_nominal' => $valorNominal > 0 ? $valorNominal : $boleto->valor_nominal,
        ];

        if ($valorPago !== null) {
            $updateData['valor_pago'] = $valorPago;
            $updateData['data_pagamento'] = now()->toDateTimeString();
        }

        $boleto->update($updateData);

        if ($valorPago !== null) {
            $this->atualizarContaReceberSePago($boleto, $valorPago);
        }

        return $boleto->fresh();
    }

    protected function atualizarContaReceberSePago(Boleto $boleto, float $valorPago): void
    {
        $conta = $boleto->contaAReceber;

        if (!$conta || $conta->status === 'pago') {
            return;
        }

        $conta->update([
            'status' => 'pago',
            'valor_pago' => $valorPago,
            'data_pagamento' => now()->toDateString(),
        ]);

        Log::info('Conta a receber baixada via Mercado Pago', [
            'conta_id' => $conta->id_contas,
            'boleto_id' => $boleto->id_boleto,
            'valor_pago' => $valorPago,
        ]);
    }

    protected function montarPayloadOrder(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): array
    {
        $valor = number_format((float) $conta->valor_total, 2, '.', '');
        $identificacao = $this->resolverIdentificacaoPagador($cliente, $empresa);
        $nome = $this->resolverNomePagador($cliente);
        $endereco = $this->resolverEnderecoPagador($cliente, $empresa);
        $descricao = trim((string) ($conta->descricao ?: ('Cobranca #' . $conta->id_contas)));

        $payment = [
            'amount' => $valor,
            'payment_method' => [
                'id' => 'boleto',
                'type' => 'ticket',
            ],
        ];

        $expirationTime = $this->montarExpirationTime($conta);
        if ($expirationTime !== null) {
            $payment['expiration_time'] = $expirationTime;
        }

        return [
            'type' => 'online',
            'external_reference' => (string) $conta->id_contas,
            'processing_mode' => (string) config('services.mercado_pago.processing_mode', 'automatic'),
            'total_amount' => $valor,
            'description' => $this->limitarTexto($descricao, 255),
            'payer' => [
                'email' => $this->resolverEmailPagador($cliente, $empresa),
                'first_name' => $nome['first_name'],
                'last_name' => $nome['last_name'],
                'identification' => $identificacao,
                'address' => $endereco,
            ],
            'transactions' => [
                'payments' => [$payment],
            ],
        ];
    }

    protected function resolverNomePagador(Cliente $cliente): array
    {
        $nomeCompleto = trim((string) ($cliente->nome ?: $cliente->razao_social ?: 'Cliente'));
        $partes = preg_split('/\s+/', $nomeCompleto) ?: [];

        $firstName = $this->limitarTexto((string) array_shift($partes), 80);
        if ($firstName === '') {
            $firstName = 'Cliente';
        }

        $lastName = $this->limitarTexto(trim(implode(' ', $partes)), 80);
        if ($lastName === '') {
            $lastName = 'GestorNow';
        }

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
        ];
    }

    protected function resolverIdentificacaoPagador(Cliente $cliente, Empresa $empresa): array
    {
        $doc = preg_replace('/[^\d]/', '', (string) ($cliente->cpf_cnpj ?: $empresa->cpf ?: $empresa->cnpj));

        if ($doc === '' || !in_array(strlen($doc), [11, 14], true)) {
            throw new Exception('CPF/CNPJ do pagador invalido. Informe CPF/CNPJ valido no cliente ou empresa.');
        }

        return [
            'type' => strlen($doc) === 14 ? 'CNPJ' : 'CPF',
            'number' => $doc,
        ];
    }

    protected function resolverEnderecoPagador(Cliente $cliente, Empresa $empresa): array
    {
        $cidade = trim((string) ($cliente->cidade ?? $empresa->cidade ?? ''));
        $estadoBruto = trim((string) ($cliente->uf ?? $cliente->estado ?? $empresa->uf ?? $empresa->estado ?? ''));
        $uf = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $estadoBruto), 0, 2));

        if ($cidade === '' || strlen($uf) !== 2) {
            throw new Exception('Cidade e UF do pagador sao obrigatorias para boleto Mercado Pago.');
        }

        $cep = preg_replace('/[^\d]/', '', (string) ($cliente->cep ?: $empresa->cep));
        if (strlen($cep) !== 8) {
            throw new Exception('CEP do pagador invalido. Informe um CEP com 8 digitos no cliente ou empresa.');
        }

        $streetName = $this->limitarTexto((string) ($cliente->endereco ?: $empresa->endereco ?: ''), 120);
        $streetNumber = $this->limitarTexto((string) ($cliente->numero ?: $empresa->numero ?: 'S/N'), 20);
        $bairro = $this->limitarTexto((string) ($cliente->bairro ?: $empresa->bairro ?: 'Centro'), 80);

        if ($streetName === '') {
            throw new Exception('Endereco do pagador obrigatorio para boleto Mercado Pago.');
        }

        if ($streetNumber === '') {
            $streetNumber = 'S/N';
        }

        return [
            'street_name' => $streetName,
            'street_number' => $streetNumber,
            'zip_code' => $cep,
            'neighborhood' => $bairro,
            'state' => $uf,
            'city' => $this->limitarTexto($cidade, 120),
        ];
    }

    protected function resolverEmailPagador(Cliente $cliente, Empresa $empresa): string
    {
        $email = trim((string) ($cliente->email ?: $empresa->email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail do pagador invalido. Informe um e-mail valido no cliente ou empresa.');
        }

        return $this->limitarTexto($email, 255);
    }

    protected function montarExpirationTime(ContasAReceber $conta): ?string
    {
        if (!$conta->data_vencimento) {
            return null;
        }

        $dias = now()->startOfDay()->diffInDays($conta->data_vencimento->copy()->startOfDay(), false);

        if ($dias < 1) {
            $dias = 1;
        }

        if ($dias > 30) {
            $dias = 30;
        }

        return 'P' . $dias . 'D';
    }

    protected function extrairPrimeiroPagamento(array $order): array
    {
        $payments = $order['transactions']['payments'] ?? [];

        if (is_array($payments) && isset($payments[0]) && is_array($payments[0])) {
            return $payments[0];
        }

        return [];
    }

    protected function mapearStatusBoleto(array $order, array $payment): string
    {
        $status = strtolower(trim((string) ($payment['status'] ?? $order['status'] ?? '')));
        $detail = strtolower(trim((string) ($payment['status_detail'] ?? $order['status_detail'] ?? '')));

        $totalPaid = $this->toFloat($payment['paid_amount'] ?? $order['total_paid_amount'] ?? 0);
        $totalAmount = $this->toFloat($payment['amount'] ?? $order['total_amount'] ?? 0);

        if (in_array($status, ['approved', 'paid', 'accredited', 'succeeded'], true)) {
            return Boleto::STATUS_PAGO;
        }

        if ($totalPaid > 0 && ($totalAmount <= 0 || $totalPaid >= $totalAmount)) {
            return Boleto::STATUS_PAGO;
        }

        // Orders API do MP pode retornar status=processed e detail=accredited quando pago.
        if (in_array($detail, ['accredited', 'approved', 'paid'], true)) {
            return Boleto::STATUS_PAGO;
        }

        if ($status === 'processed') {
            return Boleto::STATUS_PAGO;
        }

        if (str_contains($detail, 'expired') || str_contains($detail, 'past_due')) {
            return Boleto::STATUS_VENCIDO;
        }

        if (in_array($status, ['cancelled', 'canceled', 'rejected', 'refunded', 'charged_back'], true)) {
            return Boleto::STATUS_CANCELADO;
        }

        if (in_array($status, ['action_required', 'pending', 'in_process', 'processing', 'waiting_payment'], true)) {
            return Boleto::STATUS_GERADO;
        }

        return Boleto::STATUS_PENDENTE;
    }

    protected function extrairStatusBanco(array $order, array $payment): string
    {
        $detail = trim((string) ($payment['status_detail'] ?? $order['status_detail'] ?? ''));
        if ($detail !== '') {
            return $detail;
        }

        return trim((string) ($payment['status'] ?? $order['status'] ?? 'PENDING'));
    }

    protected function extrairResourceIdWebhook(array $dados): ?string
    {
        $candidatos = [
            $dados['data']['id'] ?? null,
            $dados['id'] ?? null,
            $dados['resource']['id'] ?? null,
            $dados['resource'] ?? null,
        ];

        foreach ($candidatos as $candidato) {
            if (!is_scalar($candidato)) {
                continue;
            }

            $valor = trim((string) $candidato);
            if ($valor === '') {
                continue;
            }

            if (str_contains($valor, '/')) {
                $partes = explode('/', trim($valor, '/'));
                $valor = trim((string) end($partes));
            }

            if ($valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    protected function resolverBoletoWebhook(string $resourceId): ?Boleto
    {
        $boleto = Boleto::where('codigo_solicitacao', $resourceId)->first();
        if ($boleto) {
            return $boleto;
        }

        // Em eventos de "pagamentos", o id costuma ser PAY... e fica dentro do json_resposta.
        return Boleto::where('json_resposta', 'like', '%"id":"' . $resourceId . '"%')
            ->orderByDesc('id_boleto')
            ->first();
    }

    protected function resolverOrderIdParaSincronizacao(string $resourceId, Boleto $boleto): ?string
    {
        if (str_starts_with(strtoupper($resourceId), 'ORD')) {
            return $resourceId;
        }

        $codigoSolicitacao = trim((string) $boleto->codigo_solicitacao);
        if ($codigoSolicitacao !== '') {
            return $codigoSolicitacao;
        }

        $resposta = $boleto->resposta_decodificada;
        $orderId = trim((string) ($resposta['id'] ?? ''));

        return $orderId !== '' ? $orderId : null;
    }

    protected function extrairOrderDoWebhook(array $dados, string $orderId): ?array
    {
        $data = $dados['data'] ?? null;
        if (!is_array($data)) {
            return null;
        }

        $id = trim((string) ($data['id'] ?? ''));
        if ($id === '' || $id !== $orderId) {
            return null;
        }

        if (isset($data['transactions']) && is_array($data['transactions'])) {
            return $data;
        }

        return null;
    }

    protected function extrairMensagemErroApi(?array $data, string $fallback, ?string $raw = null): string
    {
        $mensagens = [];

        if (is_array($data)) {
            foreach (['message', 'error', 'detail'] as $campo) {
                if (isset($data[$campo]) && is_scalar($data[$campo])) {
                    $mensagens[] = (string) $data[$campo];
                }
            }

            if (!empty($data['cause']) && is_array($data['cause'])) {
                foreach ($data['cause'] as $cause) {
                    if (!is_array($cause)) {
                        continue;
                    }

                    $descricao = trim((string) ($cause['description'] ?? $cause['message'] ?? ''));
                    $codigo = trim((string) ($cause['code'] ?? ''));

                    $texto = trim(($codigo !== '' ? $codigo . ': ' : '') . $descricao);
                    if ($texto !== '') {
                        $mensagens[] = $texto;
                    }
                }
            }
        }

        $mensagens = array_values(array_unique(array_filter(array_map('trim', $mensagens))));

        if ($mensagens === [] && is_string($raw) && trim($raw) !== '') {
            $resumo = trim(preg_replace('/\s+/', ' ', $raw));
            $mensagens[] = 'resposta: ' . substr($resumo, 0, 300);
        }

        return $mensagens === [] ? $fallback : implode(' | ', $mensagens);
    }

    protected function toFloat($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            $normalizado = str_replace(',', '.', trim($value));
            if (is_numeric($normalizado)) {
                return (float) $normalizado;
            }
        }

        return 0.0;
    }

    protected function limitarTexto(string $texto, int $max): string
    {
        $texto = trim($texto);

        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max);
    }
}
