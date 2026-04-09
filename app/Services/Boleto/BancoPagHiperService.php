<?php

namespace App\Services\Boleto;

use App\Domain\Auth\Models\Empresa;
use App\Domain\Cliente\Models\Cliente;
use App\Models\BancoBoletoConfig;
use App\Models\Boleto;
use App\Models\BoletoHistorico;
use App\Models\ContasAReceber;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BancoPagHiperService
{
    protected ?BancoBoletoConfig $config;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct(?BancoBoletoConfig $config = null)
    {
        $this->config = $config;
        $this->baseUrl = rtrim((string) config('services.paghiper.base_url', 'https://api.paghiper.com'), '/');
        $this->timeout = (int) config('services.paghiper.timeout', 60);
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
     * Retorna a API Key do PagHiper.
     */
    protected function getApiKey(): string
    {
        $apiKeyConfig = trim((string) optional($this->config)->api_key);
        if ($apiKeyConfig !== '') {
            return $apiKeyConfig;
        }

        $apiKeyEnv = trim((string) config('services.paghiper.api_key', ''));
        if ($apiKeyEnv !== '') {
            return $apiKeyEnv;
        }

        throw new Exception('ApiKey do PagHiper nao configurada.');
    }

    /**
     * Retorna o token do PagHiper.
     */
    protected function getToken(): string
    {
        $tokenConfig = trim((string) optional($this->config)->token);
        if ($tokenConfig !== '') {
            return $tokenConfig;
        }

        $tokenEnv = trim((string) config('services.paghiper.token', ''));
        if ($tokenEnv !== '') {
            return $tokenEnv;
        }

        throw new Exception('Token do PagHiper nao configurado.');
    }

    /**
     * Faz requisicao HTTP para API PagHiper.
     */
    protected function request(string $method, string $endpoint, array $data = [], array $query = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $method = strtoupper($method);

        $headers = [
            'Accept: application/json',
            'Accept-Charset: UTF-8',
            'Content-Type: application/json;charset=UTF-8',
            'User-Agent: GestorNow/2.0',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        } elseif (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

            if ($method !== 'DELETE') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
        }

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com API PagHiper: ' . $error);
        }

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true) ?? [],
            'raw' => $response,
        ];
    }

    /**
     * Emite boleto no PagHiper.
     */
    public function gerarBoleto(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): Boleto
    {
        if (!$this->config) {
            throw new Exception('Configuracao de boleto nao definida.');
        }

        $payload = $this->montarPayloadCriacao($conta, $empresa, $cliente);

        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $payload, 'etapa' => 'iniciando_paghiper_create']
        );

        $result = $this->request('POST', '/transaction/create/', $payload);
        $responseData = is_array($result['response']) ? $result['response'] : [];
        $createData = $this->extrairCreatePayload($responseData);

        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            [
                'request' => $payload,
                'response' => $responseData,
                'http_code' => $result['http_code'],
            ]
        );

        $transactionId = trim((string) ($createData['transaction_id'] ?? ''));

        if (!in_array((int) $result['http_code'], [200, 201], true)
            || $transactionId === ''
            || !$this->isCreateSucesso($createData, $responseData)
        ) {
            $mensagem = $this->extrairMensagemErroApi(
                $responseData,
                'Erro ao emitir boleto no PagHiper.',
                is_string($result['raw'] ?? null) ? $result['raw'] : null
            );

            throw new Exception('Erro PagHiper (HTTP ' . (int) $result['http_code'] . '): ' . $mensagem);
        }

        $bankSlip = is_array($createData['bank_slip'] ?? null) ? $createData['bank_slip'] : [];
        $statusBanco = trim((string) ($createData['status'] ?? 'pending'));
        $status = $this->mapearStatusBoleto($statusBanco, $createData);

        $valorNominal = $this->extrairValorNominal($createData);
        if ($valorNominal <= 0) {
            $valorNominal = $this->toFloat($conta->valor_total);
        }

        $dataVencimento = $this->parseDate($createData['due_date'] ?? null);
        if (!$dataVencimento) {
            $dataVencimento = $conta->data_vencimento ? Carbon::parse($conta->data_vencimento) : now()->addDays(3);
        }

        $urlSlipBruta = trim((string) ($this->firstByPaths($bankSlip, ['url_slip', 'url'])
            ?? $this->firstByPaths($createData, ['url_slip', 'bank_slip_url'])
            ?? ''));
        $urlSlip = $this->normalizarUrlPdfPagHiper($urlSlipBruta) ?? $urlSlipBruta;

        $boleto = Boleto::create([
            'id_empresa' => $empresa->id_empresa,
            'id_conta_receber' => $conta->id_contas,
            'id_bancos' => $this->config->id_bancos,
            'id_banco_boleto' => $this->config->id_banco_boleto,
            'codigo_solicitacao' => $transactionId,
            'nosso_numero' => trim((string) ($createData['order_id'] ?? $payload['order_id'] ?? '')),
            'linha_digitavel' => $this->toNullableString($this->firstByPaths($bankSlip, ['digitable_line'])
                ?? $this->firstByPaths($createData, ['digitable_line'])),
            'codigo_barras' => $this->toNullableString($this->firstByPaths($bankSlip, ['barcode_number', 'barcode'])
                ?? $this->firstByPaths($createData, ['barcode_number', 'barcode'])),
            'valor_nominal' => $valorNominal,
            'data_emissao' => now()->toDateString(),
            'data_vencimento' => $dataVencimento->toDateString(),
            'status' => $status,
            'situacao_banco' => $statusBanco !== '' ? $statusBanco : 'pending',
            'url_pdf' => $urlSlip !== '' ? $urlSlip : null,
            'json_resposta' => json_encode($responseData),
        ]);

        if ($status === Boleto::STATUS_PAGO) {
            $valorPago = $this->extrairValorPago($createData);
            if ($valorPago <= 0) {
                $valorPago = $valorNominal;
            }

            $dataPagamento = $this->parseDate(
                $this->firstByPaths($createData, ['paid_date', 'payment_date', 'paid_at'])
            ) ?: now();

            $boleto->update([
                'valor_pago' => $valorPago,
                'data_pagamento' => $dataPagamento->toDateTimeString(),
            ]);

            $this->atualizarContaReceberSePago($boleto, $valorPago, $dataPagamento->toDateString());
        }

        BoletoHistorico::where('id_empresa', $empresa->id_empresa)
            ->whereNull('id_boleto')
            ->latest('id_historico')
            ->limit(2)
            ->update(['id_boleto' => $boleto->id_boleto]);

        Log::info('Boleto PagHiper gerado com sucesso', [
            'boleto_id' => $boleto->id_boleto,
            'transaction_id' => $transactionId,
            'conta_id' => $conta->id_contas,
        ]);

        return $boleto;
    }

    /**
     * Consulta situacao do boleto no PagHiper.
     *
     * O endpoint oficial de consulta pode variar por conta, entao tentamos
     * consulta remota e, em fallback, usamos os dados locais persistidos.
     */
    public function consultarBoleto(Boleto $boleto): array
    {
        if (!$this->config) {
            $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
                ->where('id_empresa', $boleto->id_empresa)
                ->first();

            if ($config) {
                $this->setConfig($config);
            }
        }

        $dadosAtualizacao = null;

        try {
            $dadosAtualizacao = $this->consultarStatusRemoto($boleto);
        } catch (Exception $e) {
            Log::warning('Falha na consulta remota PagHiper; mantendo dados locais', [
                'boleto_id' => $boleto->id_boleto,
                'erro' => $e->getMessage(),
            ]);
        }

        if (!is_array($dadosAtualizacao) || $dadosAtualizacao === []) {
            $dadosAtualizacao = is_array($boleto->resposta_decodificada) ? $boleto->resposta_decodificada : [];
        }

        $atualizado = $this->atualizarBoletoComDados($boleto, $dadosAtualizacao, true);

        return [
            'status' => $atualizado->status,
            'status_banco' => $atualizado->situacao_banco,
            'linha_digitavel' => $atualizado->linha_digitavel,
            'codigo_barras' => $atualizado->codigo_barras,
            'ticket_url' => $atualizado->url_pdf,
            'dados_completos' => $dadosAtualizacao,
        ];
    }

    /**
     * Tenta obter PDF do boleto via URL retornada pelo PagHiper.
     */
    public function obterPdf(Boleto $boleto): string
    {
        if (!$boleto->url_pdf) {
            $this->consultarBoleto($boleto);
            $boleto->refresh();
        }

        $urlOriginal = trim((string) $boleto->url_pdf);
        if ($urlOriginal === '') {
            throw new Exception('URL do boleto nao encontrada no PagHiper.');
        }

        $tentativas = array_values(array_unique(array_filter([
            $this->normalizarUrlPdfPagHiper($urlOriginal),
            $urlOriginal,
        ])));

        $ultimoErro = null;

        foreach ($tentativas as $url) {
            $download = $this->baixarUrl($url);

            if ($download['body'] === false) {
                $ultimoErro = 'Erro ao baixar boleto no PagHiper: ' . $download['error'];
                continue;
            }

            if ((int) $download['http_code'] !== 200) {
                $ultimoErro = 'Nao foi possivel obter boleto no PagHiper. HTTP ' . (int) $download['http_code'] . '. URL: ' . $url;
                continue;
            }

            if (str_contains(strtolower((string) $download['content_type']), 'application/pdf')) {
                return (string) $download['body'];
            }

            $ultimoErro = 'PagHiper nao retornou PDF binario. Use a URL para pagamento: ' . $url;
        }

        throw new Exception($ultimoErro ?: ('PagHiper nao retornou PDF binario. Use a URL para pagamento: ' . $urlOriginal));
    }

    /**
     * Cancelamento nao implementado no fluxo atual.
     */
    public function cancelarBoleto(Boleto $boleto, string $motivo = 'Solicitado pelo cliente'): bool
    {
        throw new Exception('Cancelamento de boleto no PagHiper nao esta implementado neste fluxo.');
    }

    /**
     * Alteracao de vencimento nao implementada no fluxo atual.
     */
    public function alterarVencimento(Boleto $boleto, string $novaDataVencimento, float $novoValor): Boleto
    {
        throw new Exception('Alteracao de vencimento no PagHiper nao esta implementada neste fluxo.');
    }

    /**
     * Processa webhook do PagHiper.
     */
    public function processarWebhook(array $dados): void
    {
        Log::info('Processando webhook PagHiper', ['dados' => $dados]);

        $transactionId = $this->extrairTransactionIdWebhook($dados);
        if ($transactionId === null) {
            Log::warning('Webhook PagHiper sem transaction_id', ['dados' => $dados]);
            return;
        }

        $boleto = $this->resolverBoletoWebhook($transactionId, $dados);
        if (!$boleto) {
            Log::warning('Boleto nao encontrado para webhook PagHiper', [
                'transaction_id' => $transactionId,
            ]);
            return;
        }

        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_WEBHOOK,
            $dados
        );

        $config = BancoBoletoConfig::where('id_bancos', $boleto->id_bancos)
            ->where('id_empresa', $boleto->id_empresa)
            ->first();

        if ($config) {
            $this->setConfig($config);

            $tokenInformado = trim((string) ($this->firstByPaths($dados, [
                'token',
                'notification_token',
                'data.token',
            ]) ?? ''));

            $tokenEsperado = trim((string) $config->token);
            if ($tokenEsperado !== '' && $tokenInformado !== '' && !hash_equals($tokenEsperado, $tokenInformado)) {
                Log::warning('Webhook PagHiper ignorado por token divergente', [
                    'transaction_id' => $transactionId,
                    'boleto_id' => $boleto->id_boleto,
                ]);
                return;
            }
        }

        $normalizado = $this->normalizarDadosWebhook($dados, $transactionId);

        // Em alguns cenarios o webhook envia somente notification_id.
        if ((trim((string) ($normalizado['status'] ?? '')) === '') && $this->config) {
            $notificationId = trim((string) ($normalizado['notification_id'] ?? ''));

            if ($notificationId !== '') {
                $consultaNotificacao = $this->consultarNotificacaoRemota($boleto, $notificationId);
                if (is_array($consultaNotificacao) && $consultaNotificacao !== []) {
                    $normalizado = array_merge($normalizado, $consultaNotificacao);
                }
            }
        }

        $boletoAtualizado = $this->atualizarBoletoComDados($boleto, $normalizado, true);

        $boletoAtualizado->update([
            'json_webhook' => json_encode($dados),
        ]);
    }

    protected function montarPayloadCriacao(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): array
    {
        $valorCents = (int) round($this->toFloat($conta->valor_total) * 100);

        if ($valorCents < 300) {
            throw new Exception('PagHiper exige valor minimo de R$ 3,00 por boleto.');
        }

        $nomePagador = trim((string) ($cliente->nome ?: $cliente->razao_social ?: 'Cliente'));
        $emailPagador = trim((string) ($cliente->email ?: $empresa->email));
        $docPagador = preg_replace('/[^\d]/', '', (string) ($cliente->cpf_cnpj ?: $empresa->cpf ?: $empresa->cnpj));

        if ($emailPagador === '' || !filter_var($emailPagador, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('E-mail do pagador invalido para emissao no PagHiper.');
        }

        if ($docPagador === '' || !in_array(strlen($docPagador), [11, 14], true)) {
            throw new Exception('CPF/CNPJ do pagador invalido para emissao no PagHiper.');
        }

        $dataVencimento = $conta->data_vencimento ? Carbon::parse($conta->data_vencimento) : now()->addDays(3);
        $diasVencimento = now()->startOfDay()->diffInDays($dataVencimento->copy()->startOfDay(), false);

        if ($diasVencimento < 1) {
            $diasVencimento = 1;
        }

        if ($diasVencimento > 400) {
            $diasVencimento = 400;
        }

        $telefone = preg_replace('/[^\d]/', '', (string) ($cliente->telefone ?: $empresa->telefone));
        $ufBruto = trim((string) ($cliente->uf ?? $cliente->estado ?? $empresa->uf ?? ''));
        $uf = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $ufBruto), 0, 2));

        $descricao = trim((string) ($conta->descricao ?: ('Cobranca #' . $conta->id_contas)));
        if ($descricao === '') {
            $descricao = 'Cobranca';
        }

        $payload = [
            'apiKey' => $this->getApiKey(),
            'token' => $this->getToken(),
            'order_id' => $this->gerarOrderId($conta),
            'payer_email' => $this->limitarTexto($emailPagador, 255),
            'payer_name' => $this->limitarTexto($nomePagador, 255),
            'payer_cpf_cnpj' => $docPagador,
            'days_due_date' => $diasVencimento,
            'type_bank_slip' => 'boletoA4',
            'items' => [
                [
                    'description' => $this->limitarTexto($descricao, 255),
                    'quantity' => '1',
                    'item_id' => (string) $conta->id_contas,
                    'price_cents' => (string) $valorCents,
                ],
            ],
            'per_day_interest' => $this->toFloat(optional($this->config)->juros_mora) > 0,
        ];

        $notificationUrl = $this->getNotificationUrl();
        if ($notificationUrl !== '') {
            $payload['notification_url'] = $notificationUrl;
        }

        if ($telefone !== '' && strlen($telefone) >= 10) {
            $payload['payer_phone'] = $telefone;
        }

        $endereco = $this->limitarTexto((string) ($cliente->endereco ?: $empresa->endereco ?: ''), 255);
        if ($endereco !== '') {
            $payload['payer_street'] = $endereco;
        }

        $numero = $this->limitarTexto((string) ($cliente->numero ?: $empresa->numero ?: ''), 20);
        if ($numero !== '') {
            $payload['payer_number'] = $numero;
        }

        $complemento = $this->limitarTexto((string) ($cliente->complemento ?: $empresa->complemento ?: ''), 80);
        if ($complemento !== '') {
            $payload['payer_complement'] = $complemento;
        }

        $bairro = $this->limitarTexto((string) ($cliente->bairro ?: $empresa->bairro ?: ''), 80);
        if ($bairro !== '') {
            $payload['payer_district'] = $bairro;
        }

        $cidade = $this->limitarTexto((string) ($cliente->cidade ?: $empresa->cidade ?: ''), 120);
        if ($cidade !== '') {
            $payload['payer_city'] = $cidade;
        }

        if (strlen($uf) === 2) {
            $payload['payer_state'] = $uf;
        }

        $cep = preg_replace('/[^\d]/', '', (string) ($cliente->cep ?: $empresa->cep));
        if (strlen($cep) === 8) {
            $payload['payer_zip_code'] = $cep;
        }

        $multa = (int) round($this->toFloat(optional($this->config)->multa_atraso));
        if ($multa > 0) {
            $payload['late_payment_fine'] = max(1, min(2, $multa));
        }

        return array_filter($payload, static function ($valor) {
            return $valor !== null && $valor !== '';
        });
    }

    protected function consultarStatusRemoto(Boleto $boleto): ?array
    {
        if (!$this->config) {
            return null;
        }

        $payload = [
            'apiKey' => $this->getApiKey(),
            'token' => $this->getToken(),
            'transaction_id' => (string) $boleto->codigo_solicitacao,
        ];

        $result = $this->request('POST', '/transaction/status/', $payload);
        $responseData = is_array($result['response']) ? $result['response'] : [];

        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            [
                'request' => $payload,
                'response' => $responseData,
                'http_code' => $result['http_code'],
            ]
        );

        if (!in_array((int) $result['http_code'], [200, 201], true)) {
            return null;
        }

        if (!$this->statusRequestSucesso($responseData)) {
            return null;
        }

        return $this->extrairStatusPayload($responseData);
    }

    protected function consultarNotificacaoRemota(Boleto $boleto, string $notificationId): ?array
    {
        if (!$this->config) {
            return null;
        }

        try {
            $payload = [
                'apiKey' => $this->getApiKey(),
                'token' => $this->getToken(),
                'notification_id' => $notificationId,
            ];

            $result = $this->request('POST', '/transaction/notification/', $payload);
            $responseData = is_array($result['response']) ? $result['response'] : [];

            BoletoHistorico::registrar(
                $boleto->id_boleto,
                $boleto->id_empresa,
                BoletoHistorico::TIPO_CONSULTA,
                [
                    'request' => $payload,
                    'response' => $responseData,
                    'http_code' => $result['http_code'],
                    'etapa' => 'consulta_notificacao',
                ]
            );

            if (!in_array((int) $result['http_code'], [200, 201], true)) {
                return null;
            }

            if (!$this->statusRequestSucesso($responseData)) {
                return null;
            }

            return $this->extrairStatusPayload($responseData);
        } catch (Exception $e) {
            Log::warning('Falha ao consultar notificacao PagHiper', [
                'boleto_id' => $boleto->id_boleto,
                'notification_id' => $notificationId,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function atualizarBoletoComDados(Boleto $boleto, array $dados, bool $podeBaixarConta): Boleto
    {
        if ($dados === []) {
            return $boleto;
        }

        $statusBruto = trim((string) ($dados['status']
            ?? $this->firstByPaths($dados, ['transaction_status', 'current_status'])
            ?? ''));

        $valorPago = $this->extrairValorPago($dados);
        $status = ($statusBruto !== '' || $valorPago > 0)
            ? $this->mapearStatusBoleto($statusBruto, $dados)
            : $boleto->status;

        $valorNominal = $this->extrairValorNominal($dados);
        $dataPagamento = $this->parseDate(
            $this->firstByPaths($dados, ['paid_date', 'payment_date', 'paid_at', 'data_pagamento'])
        );
        $dataVencimento = $this->parseDate(
            $this->firstByPaths($dados, ['due_date', 'data_vencimento', 'bank_slip.due_date'])
        );

        $updateData = [
            'status' => $status,
            'situacao_banco' => $statusBruto !== '' ? $statusBruto : $boleto->situacao_banco,
            'json_resposta' => json_encode($dados),
        ];

        $transactionId = trim((string) ($dados['transaction_id'] ?? ''));
        if ($transactionId !== '') {
            $updateData['codigo_solicitacao'] = $transactionId;
        }

        $orderId = trim((string) ($dados['order_id'] ?? ''));
        if ($orderId !== '') {
            $updateData['nosso_numero'] = $orderId;
        }

        $linhaDigitavel = $this->toNullableString(
            $this->firstByPaths($dados, ['bank_slip.digitable_line', 'digitable_line', 'linha_digitavel'])
        );
        if ($linhaDigitavel !== null) {
            $updateData['linha_digitavel'] = $linhaDigitavel;
        }

        $codigoBarras = $this->toNullableString(
            $this->firstByPaths($dados, ['bank_slip.barcode_number', 'barcode_number', 'codigo_barras'])
        );
        if ($codigoBarras !== null) {
            $updateData['codigo_barras'] = $codigoBarras;
        }

        $urlSlip = $this->toNullableString(
            $this->firstByPaths($dados, ['bank_slip.url_slip', 'url_slip', 'bank_slip_url', 'url'])
        );
        if ($urlSlip !== null) {
            $updateData['url_pdf'] = $this->normalizarUrlPdfPagHiper($urlSlip) ?? $urlSlip;
        }

        if ($valorNominal > 0) {
            $updateData['valor_nominal'] = $valorNominal;
        }

        if ($dataVencimento) {
            $updateData['data_vencimento'] = $dataVencimento->toDateString();
        }

        if ($status === Boleto::STATUS_PAGO || $valorPago > 0) {
            if ($valorPago <= 0) {
                $valorPago = $valorNominal > 0 ? $valorNominal : $this->toFloat($boleto->valor_nominal);
            }

            if (!$dataPagamento) {
                $dataPagamento = now();
            }

            $updateData['status'] = Boleto::STATUS_PAGO;
            $updateData['valor_pago'] = $valorPago;
            $updateData['data_pagamento'] = $dataPagamento->toDateTimeString();
        }

        $boleto->update($updateData);

        if (($updateData['status'] ?? null) === Boleto::STATUS_PAGO && $podeBaixarConta) {
            $this->atualizarContaReceberSePago(
                $boleto,
                (float) ($updateData['valor_pago'] ?? $boleto->valor_nominal),
                $dataPagamento ? $dataPagamento->toDateString() : now()->toDateString()
            );
        }

        return $boleto->fresh();
    }

    protected function atualizarContaReceberSePago(Boleto $boleto, float $valorPago, string $dataPagamento): void
    {
        $conta = $boleto->contaAReceber;

        if (!$conta || $conta->status === 'pago') {
            return;
        }

        $conta->update([
            'status' => 'pago',
            'valor_pago' => $valorPago,
            'data_pagamento' => $dataPagamento,
        ]);

        Log::info('Conta a receber baixada via PagHiper', [
            'conta_id' => $conta->id_contas,
            'boleto_id' => $boleto->id_boleto,
            'valor_pago' => $valorPago,
        ]);
    }

    protected function mapearStatusBoleto(?string $statusBruto, array $dados = []): string
    {
        $status = strtolower(trim((string) $statusBruto));
        $valorPago = $this->extrairValorPago($dados);

        if ($valorPago > 0 && !$this->containsAny($status, ['cancel', 'refund', 'chargeback', 'estorn'])) {
            return Boleto::STATUS_PAGO;
        }

        if ($status === '') {
            return Boleto::STATUS_PENDENTE;
        }

        if ($this->containsAny($status, ['paid', 'approved', 'accredited', 'completed', 'success', 'recebido', 'liquidado', 'processado', 'processed'])) {
            return Boleto::STATUS_PAGO;
        }

        if ($this->containsAny($status, ['cancel', 'canceled', 'cancelled', 'refunded', 'chargeback', 'estornado', 'estornada'])) {
            return Boleto::STATUS_CANCELADO;
        }

        if ($this->containsAny($status, ['expired', 'overdue', 'vencido', 'past_due'])) {
            return Boleto::STATUS_VENCIDO;
        }

        if ($this->containsAny($status, ['pending', 'waiting', 'open', 'created', 'new', 'em_aberto'])) {
            return Boleto::STATUS_GERADO;
        }

        return Boleto::STATUS_PENDENTE;
    }

    protected function resolverBoletoWebhook(string $transactionId, array $dados): ?Boleto
    {
        $boleto = Boleto::where('codigo_solicitacao', $transactionId)->first();
        if ($boleto) {
            return $boleto;
        }

        $orderId = trim((string) ($this->firstByPaths($dados, [
            'order_id',
            'merchant_order_id',
            'data.order_id',
        ]) ?? ''));

        if ($orderId !== '') {
            $boleto = Boleto::where('nosso_numero', $orderId)
                ->orderByDesc('id_boleto')
                ->first();

            if ($boleto) {
                return $boleto;
            }

            $boleto = Boleto::where('json_resposta', 'like', '%"order_id":"' . $orderId . '"%')
                ->orderByDesc('id_boleto')
                ->first();

            if ($boleto) {
                return $boleto;
            }
        }

        return Boleto::where('json_resposta', 'like', '%"transaction_id":"' . $transactionId . '"%')
            ->orderByDesc('id_boleto')
            ->first();
    }

    protected function normalizarDadosWebhook(array $dados, string $transactionId): array
    {
        return [
            'transaction_id' => $transactionId,
            'order_id' => $this->toNullableString($this->firstByPaths($dados, [
                'order_id',
                'merchant_order_id',
                'data.order_id',
            ])),
            'status' => $this->toNullableString($this->firstByPaths($dados, [
                'status',
                'transaction_status',
                'current_status',
                'data.status',
            ])),
            'notification_id' => $this->toNullableString($this->firstByPaths($dados, [
                'notification_id',
                'data.notification_id',
            ])),
            'bank_slip' => [
                'url_slip' => $this->toNullableString($this->firstByPaths($dados, [
                    'bank_slip.url_slip',
                    'url_slip',
                    'bank_slip_url',
                    'url',
                ])),
                'digitable_line' => $this->toNullableString($this->firstByPaths($dados, [
                    'bank_slip.digitable_line',
                    'digitable_line',
                    'linha_digitavel',
                ])),
                'barcode_number' => $this->toNullableString($this->firstByPaths($dados, [
                    'bank_slip.barcode_number',
                    'barcode_number',
                    'codigo_barras',
                ])),
            ],
            'value_cents' => $this->firstByPaths($dados, ['value_cents', 'bank_slip.value_cents']),
            'value' => $this->firstByPaths($dados, ['value', 'amount', 'transaction_value']),
            'paid_value_cents' => $this->firstByPaths($dados, ['paid_value_cents', 'payment.paid_value_cents']),
            'paid_value' => $this->firstByPaths($dados, ['paid_value', 'paid_amount', 'value_paid']),
            'paid_date' => $this->firstByPaths($dados, ['paid_date', 'payment_date', 'paid_at', 'data_pagamento']),
            'due_date' => $this->firstByPaths($dados, ['due_date', 'bank_slip.due_date', 'data_vencimento']),
        ];
    }

    protected function extrairTransactionIdWebhook(array $dados): ?string
    {
        $candidatos = [
            $this->firstByPaths($dados, ['transaction_id', 'idTransacao', 'data.transaction_id']),
            $dados['id'] ?? null,
        ];

        foreach ($candidatos as $candidato) {
            if (!is_scalar($candidato)) {
                continue;
            }

            $valor = trim((string) $candidato);
            if ($valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    protected function extrairCreatePayload(array $responseData): array
    {
        if (isset($responseData['create_request']) && is_array($responseData['create_request'])) {
            return $responseData['create_request'];
        }

        return $responseData;
    }

    protected function statusRequestSucesso(array $responseData): bool
    {
        $statusRequest = strtolower(trim((string) ($responseData['status_request'] ?? '')));
        if ($statusRequest !== '' && in_array($statusRequest, ['error', 'fail', 'failed', 'false', 'unauthorized'], true)) {
            return false;
        }

        $result = strtolower(trim((string) ($responseData['result'] ?? '')));
        if ($result !== '' && in_array($result, ['error', 'fail', 'failed', 'false'], true)) {
            return false;
        }

        return true;
    }

    protected function isCreateSucesso(array $createData, array $responseData): bool
    {
        $result = strtolower(trim((string) ($createData['result'] ?? $responseData['result'] ?? '')));

        if ($result !== '' && in_array($result, ['error', 'fail', 'failed', 'false'], true)) {
            return false;
        }

        return $this->statusRequestSucesso($responseData);
    }

    protected function extrairStatusPayload(array $responseData): array
    {
        $candidatos = [
            $responseData['transaction'] ?? null,
            $responseData['notification_request'] ?? null,
            $responseData['query_request'] ?? null,
            $responseData['data'] ?? null,
            $responseData,
        ];

        foreach ($candidatos as $candidato) {
            if (!is_array($candidato)) {
                continue;
            }

            if (
                isset($candidato['transaction_id'])
                || isset($candidato['order_id'])
                || isset($candidato['status'])
                || isset($candidato['transaction_status'])
            ) {
                return $candidato;
            }
        }

        return $responseData;
    }

    protected function extrairValorNominal(array $dados): float
    {
        $valorCentavos = $this->firstByPaths($dados, [
            'value_cents',
            'bank_slip.value_cents',
            'transaction.value_cents',
        ]);

        if ($this->isNumericValue($valorCentavos)) {
            return $this->toFloat($valorCentavos) / 100;
        }

        $valor = $this->firstByPaths($dados, [
            'value',
            'amount',
            'transaction_value',
            'total_amount',
        ]);

        return $this->isNumericValue($valor) ? $this->toFloat($valor) : 0.0;
    }

    protected function extrairValorPago(array $dados): float
    {
        $valorPagoCentavos = $this->firstByPaths($dados, [
            'paid_value_cents',
            'payment.paid_value_cents',
            'transaction.paid_value_cents',
        ]);

        if ($this->isNumericValue($valorPagoCentavos)) {
            return $this->toFloat($valorPagoCentavos) / 100;
        }

        $valorPago = $this->firstByPaths($dados, [
            'paid_value',
            'paid_amount',
            'value_paid',
            'payment.paid_value',
            'payment.amount',
        ]);

        return $this->isNumericValue($valorPago) ? $this->toFloat($valorPago) : 0.0;
    }

    protected function getNotificationUrl(): string
    {
        $notificationUrlConfig = trim((string) config('services.paghiper.notification_url', ''));
        if ($notificationUrlConfig !== '') {
            return $notificationUrlConfig;
        }

        $appUrl = trim((string) config('app.url', ''));
        if ($appUrl === '') {
            return '';
        }

        return rtrim($appUrl, '/') . '/api/webhooks/boletos/paghiper';
    }

    protected function gerarOrderId(ContasAReceber $conta): string
    {
        return 'GN-' . $conta->id_contas . '-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(5));
    }

    protected function parseDate($value): ?Carbon
    {
        if (!is_scalar($value)) {
            return null;
        }

        $texto = trim((string) $value);
        if ($texto === '') {
            return null;
        }

        try {
            return Carbon::parse($texto);
        } catch (Exception $e) {
            return null;
        }
    }

    protected function extrairMensagemErroApi(?array $data, string $fallback, ?string $raw = null): string
    {
        $mensagens = [];

        if (is_array($data)) {
            foreach (['response_message', 'message', 'error', 'detail'] as $campo) {
                if (isset($data[$campo]) && is_scalar($data[$campo])) {
                    $mensagens[] = (string) $data[$campo];
                }
            }

            $createData = $this->extrairCreatePayload($data);
            foreach (['response_message', 'message', 'error'] as $campo) {
                if (isset($createData[$campo]) && is_scalar($createData[$campo])) {
                    $mensagens[] = (string) $createData[$campo];
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

    protected function containsAny(string $texto, array $palavras): bool
    {
        foreach ($palavras as $palavra) {
            if ($palavra !== '' && str_contains($texto, $palavra)) {
                return true;
            }
        }

        return false;
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

    protected function isNumericValue($value): bool
    {
        if (is_numeric($value)) {
            return true;
        }

        if (is_string($value)) {
            $normalizado = str_replace(',', '.', trim($value));
            return is_numeric($normalizado);
        }

        return false;
    }

    protected function toNullableString($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $texto = trim((string) $value);
        return $texto !== '' ? $texto : null;
    }

    protected function limitarTexto(string $texto, int $max): string
    {
        $texto = trim($texto);

        if (mb_strlen($texto) <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, $max);
    }

    protected function firstByPaths(array $data, array $paths)
    {
        foreach ($paths as $path) {
            $value = $this->getByPath($data, $path);

            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return $value;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function getByPath(array $data, string $path)
    {
        $segmentos = explode('.', $path);
        $valor = $data;

        foreach ($segmentos as $segmento) {
            if (!is_array($valor) || !array_key_exists($segmento, $valor)) {
                return null;
            }

            $valor = $valor[$segmento];
        }

        return $valor;
    }

    protected function normalizarUrlPdfPagHiper(?string $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!str_contains($url, '/checkout/boleto/')) {
            return $url;
        }

        $partes = parse_url($url);
        $path = (string) ($partes['path'] ?? '');

        if ($path === '') {
            return $url;
        }

        if (!str_ends_with($path, '/pdf')) {
            $path = rtrim($path, '/') . '/pdf';
        }

        $normalizada = '';

        if (isset($partes['scheme'])) {
            $normalizada .= $partes['scheme'] . '://';
        }

        if (isset($partes['user'])) {
            $normalizada .= $partes['user'];
            if (isset($partes['pass'])) {
                $normalizada .= ':' . $partes['pass'];
            }
            $normalizada .= '@';
        }

        if (isset($partes['host'])) {
            $normalizada .= $partes['host'];
        }

        if (isset($partes['port'])) {
            $normalizada .= ':' . $partes['port'];
        }

        $normalizada .= $path;

        if (isset($partes['query']) && $partes['query'] !== '') {
            $normalizada .= '?' . $partes['query'];
        }

        if (isset($partes['fragment']) && $partes['fragment'] !== '') {
            $normalizada .= '#' . $partes['fragment'];
        }

        return $normalizada;
    }

    protected function baixarUrl(string $url): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'body' => $body,
            'http_code' => $httpCode,
            'content_type' => $contentType,
            'error' => $error,
        ];
    }
}
