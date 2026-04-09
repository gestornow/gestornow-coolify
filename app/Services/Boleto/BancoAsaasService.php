<?php

namespace App\Services\Boleto;

use App\ActivityLog\ActionLogger;
use App\Models\Banco;
use App\Models\BancoBoletoConfig;
use App\Models\Boleto;
use App\Models\BoletoHistorico;
use App\Models\ContasAReceber;
use App\Domain\Auth\Models\Empresa;
use App\Domain\Cliente\Models\Cliente;
use Illuminate\Support\Facades\Log;
use Exception;

class BancoAsaasService
{
    protected $config;
    protected $baseUrl = 'https://api.asaas.com';
    
    // Para ambiente sandbox, use: https://sandbox.asaas.com/api
    
    /**
     * Construtor do serviço.
     */
    public function __construct(?BancoBoletoConfig $config = null)
    {
        $this->config = $config;

        $baseUrlConfig = trim((string) config('services.asaas.base_url', ''));
        if ($baseUrlConfig !== '') {
            $this->baseUrl = rtrim($baseUrlConfig, '/');
        }
    }

    /**
     * Define a configuração a ser usada.
     */
    public function setConfig(BancoBoletoConfig $config): self
    {
        $this->config = $config;
        return $this;
    }

    /**
     * Obtém a API Key.
     */
    protected function getApiKey(): string
    {
        if (!$this->config) {
            throw new Exception('Configuração de boleto não definida.');
        }

        if (!$this->config->api_key) {
            throw new Exception('API Key do Asaas não configurada.');
        }

        return $this->config->api_key;
    }

    /**
     * Faz requisição para a API do Asaas.
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $apiKey = $this->getApiKey();

        $ch = curl_init();

        $url = $this->baseUrl . $endpoint;

        $headers = [
            'access_token: ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: GestorNow/2.0',
        ];

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com API Asaas: ' . $error);
        }

        // Log para debug
        if ($httpCode < 200 || $httpCode >= 300) {
            Log::warning('Asaas API response', [
                'url' => $url,
                'method' => $method,
                'http_code' => $httpCode,
                'raw_response' => substr($response, 0, 1000),
            ]);
        }

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true) ?? [],
            'raw' => $response,
        ];
    }

    /**
     * Monta a URL pública do webhook de boletos do Asaas.
     */
    protected function obterWebhookUrl(): string
    {
        $webhookUrl = trim((string) config('services.asaas.webhook_url', ''));

        if ($webhookUrl === '') {
            $baseUrl = $this->obterBaseUrlPublica();

            if ($baseUrl === '') {
                throw new Exception('Não foi possível resolver a URL pública para montagem do webhook do Asaas. Configure ASAAS_WEBHOOK_URL ou APP_URL.');
            }

            $webhookUrl = $baseUrl . '/api/webhooks/boletos/asaas';
        }

        return rtrim($webhookUrl, '/');
    }

    /**
     * Resolve a base pública da aplicação para uso no webhook.
     */
    protected function obterBaseUrlPublica(): string
    {
        $baseUrl = rtrim(trim((string) config('app.url', '')), '/');
        $hostConfigurado = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));

        $hostEhLocal = in_array($hostConfigurado, ['', 'localhost', '127.0.0.1', '::1'], true);

        if ($baseUrl === '' || $hostEhLocal) {
            try {
                if (app()->bound('request')) {
                    $request = request();
                    $hostRequest = strtolower((string) $request->getHost());
                    $requestEhLocal = in_array($hostRequest, ['', 'localhost', '127.0.0.1', '::1'], true);

                    if (!$requestEhLocal) {
                        $baseUrl = rtrim((string) $request->getSchemeAndHttpHost(), '/');
                    }
                }
            } catch (Exception $e) {
                Log::warning('Não foi possível obter URL pública via request para webhook Asaas', [
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        if (strpos($baseUrl, 'http://') === 0) {
            $hostFinal = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
            $hostFinalEhLocal = in_array($hostFinal, ['localhost', '127.0.0.1', '::1'], true);

            if (!$hostFinalEhLocal) {
                $baseUrl = 'https://' . substr($baseUrl, strlen('http://'));
            }
        }

        return $baseUrl;
    }

    /**
     * Extrai mensagem amigável de erro da API do Asaas.
     */
    protected function extrairMensagemErroAsaas(array $response, string $default = 'Erro na API do Asaas'): string
    {
        $erro = $response['errors'][0]['description']
            ?? $response['errors'][0]['message']
            ?? $response['message']
            ?? $default;

        return trim((string) $erro);
    }

    /**
     * Eventos necessários para sincronizar boletos via webhook do Asaas.
     */
    protected function obterEventosWebhookBoleto(): array
    {
        return [
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
            'PAYMENT_RECEIVED_IN_CASH_UNDONE',
            'PAYMENT_DELETED',
            'PAYMENT_REFUNDED',
            'PAYMENT_CHARGEBACK_REQUESTED',
            'PAYMENT_OVERDUE',
            'PAYMENT_UPDATED',
        ];
    }

    /**
     * Extrai do retorno da API quais eventos foram marcados como inválidos.
     */
    protected function extrairEventosInvalidosAsaas(array $response): array
    {
        $eventosInvalidos = [];
        $erros = $response['errors'] ?? [];

        if (!is_array($erros)) {
            return [];
        }

        foreach ($erros as $erro) {
            $descricao = trim((string) ($erro['description'] ?? $erro['message'] ?? ''));
            if ($descricao === '') {
                continue;
            }

            if (preg_match('/evento\s*\[([^\]]+)\]/i', $descricao, $matches) === 1) {
                $evento = trim((string) ($matches[1] ?? ''));
                if ($evento !== '') {
                    $eventosInvalidos[] = $evento;
                }
            }
        }

        return array_values(array_unique($eventosInvalidos));
    }

    /**
     * Verifica se uma resposta da API contém uma mensagem específica.
     */
    protected function respostaAsaasContemMensagem(array $response, string $trecho): bool
    {
        $payload = mb_strtolower(json_encode($response, JSON_UNESCAPED_UNICODE));
        return $payload !== '' && str_contains($payload, mb_strtolower($trecho));
    }

    /**
     * Verifica se um webhook listado atende ao fluxo de boletos.
     */
    protected function webhookEhValidoParaBoletos(array $webhook, string $webhookUrl, bool $validarEventos = true): bool
    {
        $urlAtual = rtrim((string) ($webhook['url'] ?? ''), '/');
        if ($urlAtual === '' || $urlAtual !== $webhookUrl) {
            return false;
        }

        if (array_key_exists('enabled', $webhook) && $webhook['enabled'] === false) {
            return false;
        }

        if (!$validarEventos) {
            return true;
        }

        $eventosWebhook = $webhook['events'] ?? [];
        if (!is_array($eventosWebhook) || $eventosWebhook === []) {
            // Alguns ambientes podem não retornar os eventos no payload de listagem.
            return true;
        }

        $eventosWebhook = array_map(static fn($evento) => (string) $evento, $eventosWebhook);
        $faltantes = array_diff($this->obterEventosWebhookBoleto(), $eventosWebhook);

        return empty($faltantes);
    }

    /**
     * Busca um webhook de boletos já cadastrado no Asaas.
     */
    protected function buscarWebhookBoletoExistente(string $webhookUrl, bool $validarEventos = true): ?array
    {
        $limit = 100;
        $offset = 0;
        $tentativas = 0;

        do {
            $result = $this->request('GET', '/v3/webhooks?limit=' . $limit . '&offset=' . $offset);

            if ($result['http_code'] !== 200) {
                $errorMsg = $this->extrairMensagemErroAsaas($result['response'], 'Erro ao listar webhooks no Asaas');

                throw new Exception($errorMsg);
            }

            $lista = $result['response']['data'] ?? [];
            if (!is_array($lista)) {
                $lista = [];
            }

            foreach ($lista as $webhook) {
                if (is_array($webhook) && $this->webhookEhValidoParaBoletos($webhook, $webhookUrl, $validarEventos)) {
                    return $webhook;
                }
            }

            $hasMore = (bool) ($result['response']['hasMore'] ?? false);
            $offset += $limit;
            $tentativas++;

            if (!$hasMore || $tentativas >= 10) {
                break;
            }
        } while (true);

        return null;
    }

    /**
     * Obtém email válido para cadastro do webhook no Asaas.
     */
    protected function obterEmailWebhook(): string
    {
        $email = trim((string) config('mail.from.address', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $host = (string) parse_url($this->obterBaseUrlPublica(), PHP_URL_HOST);

            if ($host !== '') {
                $email = 'webhook@' . preg_replace('/^www\./i', '', $host);
            }
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'webhook@gestornow.com';
        }

        return $email;
    }

    /**
     * Cria webhook de boletos no Asaas.
     */
    public function configurarWebhook(string $webhookUrl): bool
    {
        $authToken = trim((string) config('services.asaas.webhook_token', ''));
        $eventosBase = array_values(array_unique($this->obterEventosWebhookBoleto()));
        $sendTypes = ['SEQUENTIALLY', 'NON_SEQUENTIALLY'];
        $emailWebhook = $this->obterEmailWebhook();

        $ultimoErro = 'Falha ao configurar webhook Asaas.';
        $ultimoHttpCode = null;
        $ultimaResposta = [];

        foreach ($sendTypes as $sendType) {
            $eventosTentativa = $eventosBase;

            // Evita loop infinito: no máximo uma tentativa por quantidade de eventos + 1.
            for ($indice = 0; $indice <= count($eventosBase); $indice++) {
                if (empty($eventosTentativa)) {
                    break;
                }

                $dadosWebhook = [
                    'name' => 'GestorNow Boletos',
                    'url' => $webhookUrl,
                    'enabled' => true,
                    'sendType' => $sendType,
                    'events' => array_values($eventosTentativa),
                    'email' => $emailWebhook,
                    'interrupted' => false,
                ];

                if ($authToken !== '') {
                    $dadosWebhook['authToken'] = $authToken;
                }

                $result = $this->request('POST', '/v3/webhooks', $dadosWebhook);

                if (in_array($result['http_code'], [200, 201], true) && isset($result['response']['id'])) {
                    $this->config->update(['webhook_ativo' => true]);

                    Log::info('Webhook Asaas configurado com sucesso', [
                        'id_config' => $this->config->id_config,
                        'webhook_id' => $result['response']['id'],
                        'webhook_url' => $webhookUrl,
                        'send_type' => $sendType,
                        'tentativa' => $indice + 1,
                        'eventos' => $eventosTentativa,
                    ]);

                    return true;
                }

                $ultimoHttpCode = $result['http_code'];
                $ultimaResposta = $result['response'];
                $ultimoErro = $this->extrairMensagemErroAsaas($result['response'], 'Falha ao configurar webhook Asaas.');

                Log::warning('Tentativa de configuração do webhook Asaas falhou', [
                    'id_config' => $this->config->id_config ?? null,
                    'http_code' => $result['http_code'],
                    'erro' => $ultimoErro,
                    'tentativa' => $indice + 1,
                    'send_type' => $sendType,
                    'webhook_url' => $webhookUrl,
                    'eventos' => $eventosTentativa,
                ]);

                $eventosInvalidos = $this->extrairEventosInvalidosAsaas($result['response']);
                if (!empty($eventosInvalidos)) {
                    $eventosTentativa = array_values(array_diff($eventosTentativa, $eventosInvalidos));

                    Log::warning('Eventos inválidos removidos para nova tentativa de webhook Asaas', [
                        'id_config' => $this->config->id_config ?? null,
                        'send_type' => $sendType,
                        'eventos_invalidos' => $eventosInvalidos,
                        'eventos_restantes' => $eventosTentativa,
                    ]);

                    continue;
                }

                // Se não houver erro de evento inválido, troca sendType ou encerra.
                if ($this->respostaAsaasContemMensagem($result['response'], 'tipo de envio')) {
                    break;
                }

                break;
            }
        }

        // Fallback: se o Asaas recusou criação por conflito, ainda assim tratar URL existente como ativa.
        try {
            $webhookExistente = $this->buscarWebhookBoletoExistente($webhookUrl, false);
            if ($webhookExistente !== null) {
                $this->config->update(['webhook_ativo' => true]);

                Log::info('Webhook Asaas já existia e foi marcado como ativo na configuração', [
                    'id_config' => $this->config->id_config,
                    'webhook_id' => $webhookExistente['id'] ?? null,
                    'webhook_url' => $webhookUrl,
                ]);

                return true;
            }
        } catch (Exception $e) {
            Log::warning('Falha ao validar existência de webhook Asaas após tentativas de criação', [
                'id_config' => $this->config->id_config ?? null,
                'erro' => $e->getMessage(),
                'webhook_url' => $webhookUrl,
            ]);
        }

        Log::error('Erro ao configurar webhook Asaas', [
            'id_config' => $this->config->id_config ?? null,
            'http_code' => $ultimoHttpCode,
            'erro' => $ultimoErro,
            'response' => $ultimaResposta,
            'webhook_url' => $webhookUrl,
        ]);

        return false;
    }

    /**
     * Garante que exista webhook de boletos no Asaas sem bloquear a emissão.
     */
    protected function garantirWebhookAtivo(): void
    {
        try {
            $webhookUrl = $this->obterWebhookUrl();
            $webhookExistente = $this->buscarWebhookBoletoExistente($webhookUrl, false);

            if ($webhookExistente !== null) {
                if (!$this->config->webhook_ativo) {
                    $this->config->update(['webhook_ativo' => true]);
                    $this->config->refresh();
                }

                return;
            }

            if ($this->configurarWebhook($webhookUrl)) {
                $this->config->refresh();
                return;
            }

            if ($this->config->webhook_ativo) {
                $this->config->update(['webhook_ativo' => false]);
                $this->config->refresh();
            }

            Log::warning('Webhook de boletos Asaas não foi configurado automaticamente. A baixa automática ficará indisponível até a ativação.', [
                'id_config' => $this->config->id_config ?? null,
                'webhook_url' => $webhookUrl,
                'base_url_asaas' => $this->baseUrl,
            ]);
        } catch (Exception $e) {
            Log::warning('Falha ao verificar/configurar webhook de boletos no Asaas. Emissão seguirá normalmente.', [
                'id_config' => $this->config->id_config ?? null,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Busca ou cria um cliente no Asaas.
     */
    protected function buscarOuCriarCliente(Cliente $cliente, Empresa $empresa): string
    {
        $cpfCnpj = preg_replace('/[^\d]/', '', $cliente->cpf_cnpj);

        // Buscar cliente existente pelo CPF/CNPJ
        $result = $this->request('GET', '/v3/customers?cpfCnpj=' . $cpfCnpj);

        if ($result['http_code'] === 200 && !empty($result['response']['data'])) {
            // Cliente já existe, retornar ID
            return $result['response']['data'][0]['id'];
        }

        // Criar novo cliente
        $nome = trim((string) ($cliente->nome ?: $cliente->razao_social));
        if ($nome === '') {
            throw new Exception('Cliente sem nome/razão social.');
        }

        $dadosCliente = [
            'name' => $this->limitarTexto($nome, 200),
            'cpfCnpj' => $cpfCnpj,
        ];

        // Adicionar email se existir
        if ($cliente->email) {
            $dadosCliente['email'] = $this->limitarTexto($cliente->email, 255);
        }

        // Adicionar telefone se existir
        if ($cliente->telefone) {
            $telefone = preg_replace('/[^\d]/', '', $cliente->telefone);
            if (strlen($telefone) >= 10) {
                $dadosCliente['phone'] = $telefone;
            }
        }

        // Adicionar endereço se existir
        if ($cliente->endereco) {
            $dadosCliente['address'] = $this->limitarTexto($cliente->endereco, 255);
            $dadosCliente['addressNumber'] = $this->limitarTexto($cliente->numero ?: 'S/N', 10);
            
            if ($cliente->complemento) {
                $dadosCliente['complement'] = $this->limitarTexto($cliente->complemento, 50);
            }
            if ($cliente->bairro) {
                $dadosCliente['province'] = $this->limitarTexto($cliente->bairro, 60);
            }
            if ($cliente->cep) {
                $dadosCliente['postalCode'] = preg_replace('/[^\d]/', '', $cliente->cep);
            }
        }

        Log::info('Criando cliente no Asaas', ['dados' => $dadosCliente]);

        $result = $this->request('POST', '/v3/customers', $dadosCliente);

        if ($result['http_code'] !== 200 || !isset($result['response']['id'])) {
            Log::error('Erro ao criar cliente no Asaas', [
                'http_code' => $result['http_code'],
                'response' => $result['response'],
                'cliente_id' => $cliente->id_clientes,
            ]);

            $errorMsg = $result['response']['errors'][0]['description'] ?? 
                        $result['response']['message'] ?? 
                        'Erro ao criar cliente no Asaas';

            throw new Exception($errorMsg);
        }

        return $result['response']['id'];
    }

    /**
     * Gera um boleto para uma conta a receber.
     */
    public function gerarBoleto(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): Boleto
    {
        // Garante o webhook para baixa automática sem bloquear a geração do boleto.
        $this->garantirWebhookAtivo();

        // Buscar ou criar cliente no Asaas
        $customerId = $this->buscarOuCriarCliente($cliente, $empresa);

        // Preparar dados da cobrança
        $dataVencimento = $conta->data_vencimento->format('Y-m-d');
        $valorNominal = number_format((float) $conta->valor_total, 2, '.', '');

        $dadosCobranca = [
            'customer' => $customerId,
            'billingType' => 'BOLETO',
            'value' => (float) $valorNominal,
            'dueDate' => $dataVencimento,
            'description' => $this->limitarTexto($conta->descricao ?: 'Cobrança #' . $conta->id_contas, 500),
            'externalReference' => (string) $conta->id_contas,
        ];

        // Adicionar juros se configurado
        if ($this->config->juros_mora > 0) {
            $dadosCobranca['interest'] = [
                'value' => (float) $this->config->juros_mora,
            ];
        }

        // Adicionar multa se configurada
        if ($this->config->multa_atraso > 0) {
            $dadosCobranca['fine'] = [
                'value' => (float) $this->config->multa_atraso,
            ];
        }

        // Registrar histórico da requisição
        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $dadosCobranca, 'etapa' => 'iniciando']
        );

        $result = $this->request('POST', '/v3/payments', $dadosCobranca);

        // Registrar histórico da resposta
        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $dadosCobranca, 'response' => $result['response'], 'http_code' => $result['http_code']]
        );

        if ($result['http_code'] !== 200 || !isset($result['response']['id'])) {
            Log::error('Erro ao gerar boleto Asaas', [
                'http_code' => $result['http_code'],
                'response' => $result['response'],
                'conta_id' => $conta->id_contas,
                'request' => $dadosCobranca,
            ]);

            $errorMsg = $result['response']['errors'][0]['description'] ?? 
                        $result['response']['message'] ?? 
                        'Erro ao gerar boleto no Asaas';

            throw new Exception($errorMsg);
        }

        $data = $result['response'];

        // Buscar linha digitável
        $linhaDigitavel = null;
        $nossoNumero = null;
        $codigoBarras = null;

        try {
            $identResult = $this->request('GET', '/v3/payments/' . $data['id'] . '/identificationField');
            if ($identResult['http_code'] === 200 && isset($identResult['response']['identificationField'])) {
                $linhaDigitavel = $identResult['response']['identificationField'];
                $nossoNumero = $identResult['response']['nossoNumero'] ?? null;
                $codigoBarras = $identResult['response']['barCode'] ?? null;
            }
        } catch (Exception $e) {
            Log::warning('Não foi possível obter linha digitável do Asaas', [
                'payment_id' => $data['id'],
                'erro' => $e->getMessage(),
            ]);
        }

        // Criar registro do boleto
        $boleto = Boleto::create([
            'id_empresa' => $empresa->id_empresa,
            'id_conta_receber' => $conta->id_contas,
            'id_bancos' => $this->config->id_bancos,
            'id_banco_boleto' => $this->config->id_banco_boleto,
            'codigo_solicitacao' => $data['id'],
            'nosso_numero' => $nossoNumero,
            'linha_digitavel' => $linhaDigitavel,
            'codigo_barras' => $codigoBarras,
            'valor_nominal' => $conta->valor_total,
            'data_emissao' => now()->toDateString(),
            'data_vencimento' => $conta->data_vencimento,
            'status' => Boleto::STATUS_GERADO,
            'situacao_banco' => $data['status'] ?? 'PENDING',
            'url_pdf' => $data['bankSlipUrl'] ?? null,
            'json_resposta' => json_encode($data),
        ]);

        // Atualizar histórico com ID do boleto
        BoletoHistorico::where('id_empresa', $empresa->id_empresa)
            ->whereNull('id_boleto')
            ->latest('id_historico')
            ->limit(2)
            ->update(['id_boleto' => $boleto->id_boleto]);

        Log::info('Boleto Asaas gerado com sucesso', [
            'boleto_id' => $boleto->id_boleto,
            'asaas_id' => $data['id'],
            'conta_id' => $conta->id_contas,
        ]);

        return $boleto;
    }

    /**
     * Obtém o PDF de um boleto.
     */
    public function obterPdf(Boleto $boleto): string
    {
        // O Asaas retorna uma URL direta para o PDF (bankSlipUrl)
        // Vamos buscar essa URL e fazer download do conteúdo

        $urlBoleto = null;

        // Tentar usar URL salva no boleto
        if ($boleto->url_pdf) {
            $urlBoleto = $boleto->url_pdf;
        } else {
            // Buscar na API
            $result = $this->request('GET', '/v3/payments/' . $boleto->codigo_solicitacao);

            if ($result['http_code'] === 200 && isset($result['response']['bankSlipUrl'])) {
                $urlBoleto = $result['response']['bankSlipUrl'];

                // Salvar URL para uso futuro
                $boleto->update(['url_pdf' => $urlBoleto]);
            }
        }

        if (!$urlBoleto) {
            throw new Exception('URL do boleto não encontrada.');
        }

        // Download do PDF
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $urlBoleto);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $pdfContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($pdfContent === false || $httpCode !== 200) {
            throw new Exception('Erro ao obter PDF do boleto. HTTP Code: ' . $httpCode);
        }

        return $pdfContent;
    }

    /**
     * Consulta a situação de um boleto.
     */
    public function consultarBoleto(Boleto $boleto): array
    {
        $result = $this->request('GET', '/v3/payments/' . $boleto->codigo_solicitacao);

        // Registrar histórico da consulta
        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            ['response' => $result['response'], 'http_code' => $result['http_code']]
        );

        if ($result['http_code'] !== 200) {
            throw new Exception('Erro ao consultar boleto no Asaas. HTTP: ' . $result['http_code']);
        }

        $data = $result['response'];

        // Mapear status do Asaas para o sistema
        $statusMap = [
            'PENDING' => Boleto::STATUS_GERADO,
            'RECEIVED' => Boleto::STATUS_PAGO,
            'CONFIRMED' => Boleto::STATUS_PAGO,
            'OVERDUE' => Boleto::STATUS_GERADO, // Vencido mas ainda não cancelado
            'REFUNDED' => Boleto::STATUS_CANCELADO,
            'RECEIVED_IN_CASH' => Boleto::STATUS_PAGO,
            'REFUND_REQUESTED' => Boleto::STATUS_CANCELADO,
            'CHARGEBACK_REQUESTED' => Boleto::STATUS_CANCELADO,
            'CHARGEBACK_DISPUTE' => Boleto::STATUS_GERADO,
            'AWAITING_CHARGEBACK_REVERSAL' => Boleto::STATUS_GERADO,
            'DUNNING_REQUESTED' => Boleto::STATUS_GERADO,
            'DUNNING_RECEIVED' => Boleto::STATUS_PAGO,
            'AWAITING_RISK_ANALYSIS' => Boleto::STATUS_GERADO,
        ];

        $novoStatus = $statusMap[$data['status']] ?? $boleto->status;

        // Atualizar boleto
        $updateData = [
            'situacao_banco' => $data['status'],
            'status' => $novoStatus,
        ];

        // Se foi pago, atualizar dados de pagamento
        if (in_array($data['status'], ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED'])) {
            $updateData['valor_pago'] = $data['value'] ?? $boleto->valor_nominal;
            $updateData['data_pagamento'] = $data['paymentDate'] ?? $data['confirmedDate'] ?? now()->toDateString();
        }

        $boleto->update($updateData);

        return [
            'status' => $novoStatus,
            'status_banco' => $data['status'],
            'valor' => $data['value'] ?? null,
            'valor_pago' => $data['netValue'] ?? null,
            'data_pagamento' => $data['paymentDate'] ?? null,
            'dados_completos' => $data,
        ];
    }

    /**
     * Processa o webhook do Asaas.
     */
    public function processarWebhook(array $dados): void
    {
        Log::info('Processando webhook Asaas', ['dados' => $dados]);

        // O Asaas envia o evento no campo 'event' e os dados do pagamento em 'payment'
        $evento = $dados['event'] ?? null;
        $payment = $dados['payment'] ?? null;

        if (!$evento || !$payment) {
            Log::warning('Webhook Asaas sem evento ou payment', ['dados' => $dados]);
            return;
        }

        $paymentId = $payment['id'] ?? null;
        if (!$paymentId) {
            Log::warning('Webhook Asaas sem payment ID', ['dados' => $dados]);
            return;
        }

        // Buscar boleto pelo código de solicitação (ID do Asaas)
        $boleto = Boleto::where('codigo_solicitacao', $paymentId)->first();

        if (!$boleto) {
            Log::warning('Boleto não encontrado para webhook Asaas', [
                'payment_id' => $paymentId,
                'evento' => $evento,
            ]);
            return;
        }

        // Registrar histórico do webhook
        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_WEBHOOK,
            $dados
        );

        // Persistir último payload de webhook para auditoria/debug.
        $boleto->update([
            'json_webhook' => json_encode($dados),
            'situacao_banco' => $payment['status'] ?? $boleto->situacao_banco,
        ]);

        // Eventos de pagamento confirmado
        $eventosPagamento = [
            'PAYMENT_CONFIRMED',
            'PAYMENT_RECEIVED',
            'PAYMENT_RECEIVED_IN_CASH_UNDONE', // Quando desfazem recebimento em dinheiro
        ];

        // Eventos de cancelamento/estorno
        $eventosCancelamento = [
            'PAYMENT_DELETED',
            'PAYMENT_REFUNDED',
            'PAYMENT_CHARGEBACK_REQUESTED',
        ];

        if (in_array($evento, $eventosPagamento)) {
            $this->darBaixaContaReceber($boleto, $payment);
        } elseif (in_array($evento, $eventosCancelamento)) {
            $this->cancelarBoletoInterno($boleto, $evento);
        } elseif ($evento === 'PAYMENT_OVERDUE') {
            // Boleto vencido - atualiza dados locais com payload do webhook
            $this->atualizarBoletoComDadosWebhook($boleto, $payment);
        } elseif ($evento === 'PAYMENT_UPDATED') {
            // Atualização do pagamento - usar payload do webhook para evitar consulta externa sem config.
            $this->atualizarBoletoComDadosWebhook($boleto, $payment);
        }

        Log::info('Webhook Asaas processado', [
            'boleto_id' => $boleto->id_boleto,
            'evento' => $evento,
        ]);
    }

    /**
     * Atualiza boleto e conta a receber com dados vindos do webhook do Asaas.
     */
    protected function atualizarBoletoComDadosWebhook(Boleto $boleto, array $payment): void
    {
        $statusAsaas = strtoupper((string) ($payment['status'] ?? ''));

        $statusMap = [
            'PENDING' => Boleto::STATUS_GERADO,
            'RECEIVED' => Boleto::STATUS_PAGO,
            'CONFIRMED' => Boleto::STATUS_PAGO,
            'OVERDUE' => Boleto::STATUS_GERADO,
            'REFUNDED' => Boleto::STATUS_CANCELADO,
            'RECEIVED_IN_CASH' => Boleto::STATUS_PAGO,
            'REFUND_REQUESTED' => Boleto::STATUS_CANCELADO,
            'CHARGEBACK_REQUESTED' => Boleto::STATUS_CANCELADO,
            'CHARGEBACK_DISPUTE' => Boleto::STATUS_GERADO,
            'AWAITING_CHARGEBACK_REVERSAL' => Boleto::STATUS_GERADO,
            'DUNNING_REQUESTED' => Boleto::STATUS_GERADO,
            'DUNNING_RECEIVED' => Boleto::STATUS_PAGO,
            'AWAITING_RISK_ANALYSIS' => Boleto::STATUS_GERADO,
        ];

        $updateBoleto = [
            'situacao_banco' => $statusAsaas !== '' ? $statusAsaas : $boleto->situacao_banco,
            'status' => $statusMap[$statusAsaas] ?? $boleto->status,
            'url_pdf' => $payment['bankSlipUrl'] ?? $boleto->url_pdf,
            'json_resposta' => json_encode($payment),
        ];

        if (!empty($payment['dueDate'])) {
            $updateBoleto['data_vencimento'] = (string) $payment['dueDate'];
        }

        if (isset($payment['value']) && is_numeric($payment['value'])) {
            $updateBoleto['valor_nominal'] = (float) $payment['value'];
        }

        if (in_array($statusAsaas, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED'], true)) {
            $updateBoleto['valor_pago'] = isset($payment['value']) && is_numeric($payment['value'])
                ? (float) $payment['value']
                : $boleto->valor_nominal;

            $updateBoleto['data_pagamento'] = (string) ($payment['paymentDate'] ?? $payment['confirmedDate'] ?? now()->toDateString());
        }

        $boleto->update($updateBoleto);

        $conta = $boleto->contaAReceber;
        if (!$conta || $conta->status === 'pago') {
            return;
        }

        $updateConta = [];

        if (!empty($payment['dueDate'])) {
            $updateConta['data_vencimento'] = (string) $payment['dueDate'];
        }

        if (isset($payment['value']) && is_numeric($payment['value'])) {
            $updateConta['valor_total'] = (float) $payment['value'];
        }

        if (!empty($updateConta)) {
            $conta->update($updateConta);
        }
    }

    /**
     * Dá baixa na conta a receber quando o boleto é pago.
     */
    protected function darBaixaContaReceber(Boleto $boleto, array $payment): void
    {
        $conta = $boleto->contaAReceber;

        if (!$conta) {
            Log::warning('Conta a receber não encontrada para dar baixa via Asaas', [
                'boleto_id' => $boleto->id_boleto,
            ]);
            return;
        }

        // Se a conta já está paga, não fazer nada
        if ($conta->status === 'pago') {
            Log::info('Conta já está paga, ignorando baixa do webhook Asaas', [
                'conta_id' => $conta->id_contas,
                'boleto_id' => $boleto->id_boleto,
            ]);
            return;
        }

        $valorPago = $payment['value'] ?? $boleto->valor_nominal;
        $dataPagamento = $payment['paymentDate'] ?? $payment['confirmedDate'] ?? now()->toDateString();

        // Atualizar boleto
        $boleto->update([
            'status' => Boleto::STATUS_PAGO,
            'situacao_banco' => $payment['status'] ?? 'CONFIRMED',
            'valor_pago' => $valorPago,
            'data_pagamento' => $dataPagamento,
        ]);

        // Atualizar conta a receber
        $conta->update([
            'status' => 'pago',
            'valor_pago' => $valorPago,
            'data_pagamento' => $dataPagamento,
        ]);

        // Registrar log de atividade
        try {
            ActionLogger::logDireto(
                'conta_receber.baixa_boleto',
                ContasAReceber::class,
                $conta->id_contas,
                $conta->id_empresa,
                null, // user_id = null pois é automático
                'Baixa automática via boleto Asaas',
                [
                    'valor_pago' => $valorPago,
                    'data_pagamento' => $dataPagamento,
                    'boleto_id' => $boleto->id_boleto,
                    'asaas_payment_id' => $payment['id'] ?? null,
                    'asaas_status' => $payment['status'] ?? null,
                ],
                ['recebimento', 'boleto', 'automatico', 'asaas']
            );
        } catch (Exception $e) {
            Log::warning('Erro ao registrar log de baixa Asaas: ' . $e->getMessage());
        }

        Log::info('Baixa automática realizada via Asaas', [
            'conta_id' => $conta->id_contas,
            'boleto_id' => $boleto->id_boleto,
            'valor_pago' => $valorPago,
        ]);
    }

    /**
     * Cancela o boleto internamente.
     */
    protected function cancelarBoletoInterno(Boleto $boleto, string $motivo): void
    {
        $boleto->update([
            'status' => Boleto::STATUS_CANCELADO,
            'situacao_banco' => 'CANCELLED',
        ]);

        Log::info('Boleto Asaas cancelado via webhook', [
            'boleto_id' => $boleto->id_boleto,
            'motivo' => $motivo,
        ]);
    }

    /**
     * Cancela um boleto no Asaas.
     */
    public function cancelarBoleto(Boleto $boleto, string $motivo = 'Solicitado pelo cliente'): bool
    {
        $result = $this->request('DELETE', '/v3/payments/' . $boleto->codigo_solicitacao);

        // Registrar histórico
        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            [
                'acao' => 'cancelamento',
                'motivo' => $motivo,
                'http_code' => $result['http_code'],
                'response' => $result['response'],
            ]
        );

        // HTTP 200 = sucesso no cancelamento
        if ($result['http_code'] === 200) {
            $boleto->update([
                'status' => Boleto::STATUS_CANCELADO,
                'situacao_banco' => 'CANCELLED',
            ]);

            Log::info('Boleto Asaas cancelado com sucesso', [
                'boleto_id' => $boleto->id_boleto,
                'asaas_id' => $boleto->codigo_solicitacao,
                'motivo' => $motivo,
            ]);

            return true;
        }

        Log::warning('Falha ao cancelar boleto no Asaas', [
            'boleto_id' => $boleto->id_boleto,
            'http_code' => $result['http_code'],
            'response' => $result['response'],
        ]);

        return false;
    }

    /**
     * Altera o vencimento de um boleto.
     * Atualiza a cobrança no Asaas com nova data.
     */
    public function alterarVencimento(Boleto $boleto, string $novaDataVencimento, float $novoValor): Boleto
    {
        // O Asaas permite atualizar a cobrança diretamente
        $dadosAtualizacao = [
            'dueDate' => $novaDataVencimento,
            'value' => $novoValor,
        ];

        $result = $this->request('PUT', '/v3/payments/' . $boleto->codigo_solicitacao, $dadosAtualizacao);

        // Registrar histórico
        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            [
                'acao' => 'alteracao_vencimento',
                'nova_data' => $novaDataVencimento,
                'novo_valor' => $novoValor,
                'http_code' => $result['http_code'],
                'response' => $result['response'],
            ]
        );

        if ($result['http_code'] !== 200) {
            $errorMsg = $result['response']['errors'][0]['description'] ?? 
                        $result['response']['message'] ?? 
                        'Erro ao alterar vencimento no Asaas';

            throw new Exception($errorMsg);
        }

        // Buscar nova linha digitável (muda quando altera o boleto)
        $linhaDigitavel = null;
        $nossoNumero = null;
        $codigoBarras = null;

        try {
            $identResult = $this->request('GET', '/v3/payments/' . $boleto->codigo_solicitacao . '/identificationField');
            if ($identResult['http_code'] === 200 && isset($identResult['response']['identificationField'])) {
                $linhaDigitavel = $identResult['response']['identificationField'];
                $nossoNumero = $identResult['response']['nossoNumero'] ?? $boleto->nosso_numero;
                $codigoBarras = $identResult['response']['barCode'] ?? $boleto->codigo_barras;
            }
        } catch (Exception $e) {
            Log::warning('Não foi possível obter nova linha digitável após alteração', [
                'boleto_id' => $boleto->id_boleto,
                'erro' => $e->getMessage(),
            ]);
        }

        // Atualizar boleto
        $boleto->update([
            'data_vencimento' => $novaDataVencimento,
            'valor_nominal' => $novoValor,
            'linha_digitavel' => $linhaDigitavel ?? $boleto->linha_digitavel,
            'nosso_numero' => $nossoNumero ?? $boleto->nosso_numero,
            'codigo_barras' => $codigoBarras ?? $boleto->codigo_barras,
            'url_pdf' => $result['response']['bankSlipUrl'] ?? $boleto->url_pdf,
            'json_resposta' => json_encode($result['response']),
        ]);

        // Atualizar conta a receber
        $conta = $boleto->contaAReceber;
        if ($conta) {
            $conta->update([
                'data_vencimento' => $novaDataVencimento,
                'valor_total' => $novoValor,
            ]);
        }

        Log::info('Vencimento do boleto Asaas alterado', [
            'boleto_id' => $boleto->id_boleto,
            'nova_data' => $novaDataVencimento,
            'novo_valor' => $novoValor,
        ]);

        return $boleto->fresh();
    }

    /**
     * Limita o tamanho de um texto.
     */
    protected function limitarTexto(?string $texto, int $limite): string
    {
        if ($texto === null) {
            return '';
        }
        return mb_substr(trim($texto), 0, $limite);
    }
}
