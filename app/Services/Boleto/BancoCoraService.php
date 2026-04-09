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

class BancoCoraService
{
    protected ?BancoBoletoConfig $config;
    protected ?string $accessToken = null;
    protected string $baseUrl;
    protected string $tokenUrl;
    protected array $ultimoTokenScopes = [];

    public function __construct(?BancoBoletoConfig $config = null)
    {
        $this->config = $config;
        $this->baseUrl = rtrim((string) config('services.cora.base_url', 'https://api.stage.cora.com.br'), '/');

        $tokenUrl = trim((string) config('services.cora.oauth_token_url', ''));
        $this->tokenUrl = $tokenUrl !== '' ? $tokenUrl : ($this->baseUrl . '/oauth/token');
    }

    /**
     * Define a configuração a ser usada.
     */
    public function setConfig(BancoBoletoConfig $config): self
    {
        $this->config = $config;
        $this->accessToken = null;

        return $this;
    }

    /**
     * Gera a URL de autorização da Cora (Authorization Code Flow).
     */
    public function gerarUrlAutorizacao(string $state, ?string $redirectUri = null, ?string $scopes = null): string
    {
        if (!$this->config) {
            throw new Exception('Configuração de boleto não definida.');
        }

        if (empty($this->config->client_id)) {
            throw new Exception('Client ID da Cora não configurado.');
        }

        $redirectUri = $redirectUri ?: $this->obterRedirectUriPadrao();
        $scopes = trim((string) ($scopes ?: config('services.cora.scopes', 'invoice account payment')));

        $authorizeUrl = trim((string) config('services.cora.oauth_authorize_url', ''));
        if ($authorizeUrl === '') {
            $authorizeUrl = $this->baseUrl . '/oauth/authorize';
        }

        $query = [
            'client_id' => (string) $this->config->client_id,
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes,
            'state' => $state,
        ];

        return rtrim($authorizeUrl, '?') . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Troca o authorization code por tokens e persiste na configuração do banco.
     */
    public function trocarCodePorTokenAuthorizationCode(string $code, ?string $redirectUri = null): array
    {
        if (!$this->config) {
            throw new Exception('Configuração de boleto não definida.');
        }

        $code = trim($code);
        if ($code === '') {
            throw new Exception('Código de autorização da Cora não informado.');
        }

        if (empty($this->config->client_id) || empty($this->config->client_secret)) {
            throw new Exception('Client ID e Client Secret da Cora são obrigatórios para Authorization Code.');
        }

        $redirectUri = $redirectUri ?: $this->obterRedirectUriPadrao();
        $basic = base64_encode((string) $this->config->client_id . ':' . (string) $this->config->client_secret);

        $resultado = $this->executarRequisicaoTokenOAuth(
            http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'Authorization: Basic ' . $basic,
            ]
        );

        if ((int) $resultado['http_code'] !== 200 || empty($resultado['response']['access_token'])) {
            $mensagem = $this->extrairMensagemErroApi(
                $resultado['response'],
                'Erro ao trocar authorization code por token na Cora.',
                'oauth_token',
                (string) ($resultado['raw'] ?? '')
            );

            throw new Exception('Erro OAuth Cora (HTTP ' . (int) $resultado['http_code'] . '): ' . $mensagem);
        }

        $this->salvarTokenAutorizado($resultado['response'], [
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        $this->accessToken = (string) $resultado['response']['access_token'];

        return $resultado['response'];
    }

    /**
     * Obtém token OAuth2 para chamadas de boleto.
     * Prioriza token autorizado de conta (Authorization Code) e usa client_credentials como fallback técnico.
     */
    protected function obterToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (!$this->config) {
            throw new Exception('Configuração de boleto não definida.');
        }

        if (empty($this->config->client_id) || empty($this->config->client_secret)) {
            throw new Exception('Client ID e Client Secret da Cora são obrigatórios.');
        }

        // 1) Tenta token persistido via Authorization Code.
        $tokenPersistido = $this->obterTokenAutorizadoPersistido();
        if ($tokenPersistido !== null && !empty($tokenPersistido['access_token'])) {
            if (!$this->tokenPersistidoExpirado($tokenPersistido)) {
                $this->accessToken = (string) $tokenPersistido['access_token'];
                return $this->accessToken;
            }

            $refreshToken = (string) ($tokenPersistido['refresh_token'] ?? '');
            if ($refreshToken !== '') {
                $renovado = $this->renovarTokenAuthorizationCode($refreshToken);
                if (!empty($renovado['access_token'])) {
                    $metaExistente = is_array($tokenPersistido['meta'] ?? null) ? $tokenPersistido['meta'] : [];
                    $metaExistente['grant_type'] = 'refresh_token';

                    $this->salvarTokenAutorizado($renovado, $metaExistente);
                    $this->accessToken = (string) $renovado['access_token'];
                    return $this->accessToken;
                }
            }
        }

        // 2) Fallback client_credentials (pode não ter permissão para /v2/invoices em Parceria).
        $basic = base64_encode((string) $this->config->client_id . ':' . (string) $this->config->client_secret);

        $tentativas = [
            [
                'headers' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                    'Authorization: Basic ' . $basic,
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                ]),
            ],
            [
                'headers' => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->config->client_id,
                    'client_secret' => $this->config->client_secret,
                ]),
            ],
        ];

        $ultimoHttpCode = 0;
        $ultimoRaw = null;
        $ultimoData = [];

        foreach ($tentativas as $tentativa) {
            $resultado = $this->executarRequisicaoTokenOAuth($tentativa['body'], $tentativa['headers']);
            $httpCode = (int) $resultado['http_code'];
            $data = is_array($resultado['response']) ? $resultado['response'] : [];
            $response = (string) ($resultado['raw'] ?? '');

            if ($httpCode === 200 && !empty($data['access_token'])) {
                $this->accessToken = (string) $data['access_token'];
                return $this->accessToken;
            }

            $ultimoHttpCode = $httpCode;
            $ultimoRaw = $response;
            $ultimoData = $data;
        }

        Log::error('Erro ao obter token Cora', [
            'token_url' => $this->tokenUrl,
            'http_code' => $ultimoHttpCode,
            'response' => $ultimoRaw,
            'id_empresa' => $this->config->id_empresa ?? null,
            'id_bancos' => $this->config->id_bancos ?? null,
        ]);

        $mensagem = $this->extrairMensagemErroApi(
            $ultimoData,
            'Erro ao obter token de acesso da Cora.',
            'oauth_token',
            is_string($ultimoRaw) ? $ultimoRaw : null
        );

        throw new Exception('Erro OAuth Cora (HTTP ' . $ultimoHttpCode . '): ' . $mensagem);
    }

    /**
     * Executa uma chamada ao endpoint de token OAuth da Cora.
     */
    protected function executarRequisicaoTokenOAuth(string $body, array $headers): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('services.cora.timeout', 60));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com OAuth da Cora: ' . $error);
        }

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true) ?? [],
            'raw' => $response,
        ];
    }

    /**
     * Renova token via refresh_token no endpoint OAuth.
     */
    protected function renovarTokenAuthorizationCode(string $refreshToken): array
    {
        if (!$this->config) {
            return [];
        }

        $refreshToken = trim($refreshToken);
        if ($refreshToken === '') {
            return [];
        }

        $basic = base64_encode((string) $this->config->client_id . ':' . (string) $this->config->client_secret);

        $resultado = $this->executarRequisicaoTokenOAuth(
            http_build_query([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]),
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'Authorization: Basic ' . $basic,
            ]
        );

        if ((int) $resultado['http_code'] !== 200 || empty($resultado['response']['access_token'])) {
            Log::warning('Falha ao renovar token Cora via refresh_token', [
                'http_code' => $resultado['http_code'],
                'response' => $resultado['response'],
                'id_empresa' => $this->config->id_empresa ?? null,
                'id_bancos' => $this->config->id_bancos ?? null,
            ]);

            return [];
        }

        return $resultado['response'];
    }

    /**
     * Retorna a URI de callback configurada para o Authorization Code.
     */
    protected function obterRedirectUriPadrao(): string
    {
        $redirectUri = trim((string) config('services.cora.redirect_uri', ''));
        if ($redirectUri !== '') {
            return $redirectUri;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if ($appUrl === '') {
            throw new Exception('APP_URL não configurada para callback OAuth da Cora.');
        }

        return $appUrl . '/financeiro/boletos/cora/callback';
    }

    /**
     * Lê token autorizado persistido no campo token (JSON).
     */
    protected function obterTokenAutorizadoPersistido(): ?array
    {
        if (!$this->config) {
            return null;
        }

        $raw = (string) $this->config->getRawOriginal('token');
        if (trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        if (!isset($decoded['access_token']) && !isset($decoded['refresh_token'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Verifica se o access_token persistido já expirou.
     */
    protected function tokenPersistidoExpirado(array $token): bool
    {
        try {
            $expiraEm = (string) ($token['expira_em'] ?? '');
            if ($expiraEm !== '') {
                return now()->gte(Carbon::parse($expiraEm));
            }
        } catch (\Throwable $e) {
            // Ignora e tenta outras estratégias abaixo.
        }

        $accessToken = (string) ($token['access_token'] ?? '');
        if ($accessToken === '') {
            return true;
        }

        $partes = explode('.', $accessToken);
        if (count($partes) >= 2) {
            $payloadBase64 = strtr($partes[1], '-_', '+/');
            $padding = strlen($payloadBase64) % 4;
            if ($padding > 0) {
                $payloadBase64 .= str_repeat('=', 4 - $padding);
            }

            $payloadJson = base64_decode($payloadBase64, true);
            $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;

            $exp = (int) ($payload['exp'] ?? 0);
            if ($exp > 0) {
                return now()->timestamp >= ($exp - 60);
            }
        }

        return false;
    }

    /**
     * Persiste token de Authorization Code/refresh no banco.
     */
    protected function salvarTokenAutorizado(array $tokenResponse, array $meta = []): void
    {
        if (!$this->config) {
            return;
        }

        $agora = now();
        $expiresIn = (int) ($tokenResponse['expires_in'] ?? 0);

        $payload = [
            'modo' => 'authorization_code',
            'access_token' => (string) ($tokenResponse['access_token'] ?? ''),
            'refresh_token' => (string) ($tokenResponse['refresh_token'] ?? ($this->obterTokenAutorizadoPersistido()['refresh_token'] ?? '')),
            'token_type' => (string) ($tokenResponse['token_type'] ?? 'Bearer'),
            'scope' => (string) ($tokenResponse['scope'] ?? ''),
            'expires_in' => $expiresIn,
            'obtido_em' => $agora->toIso8601String(),
            'expira_em' => $expiresIn > 0 ? $agora->copy()->addSeconds(max(1, $expiresIn - 60))->toIso8601String() : null,
            'meta' => $meta,
        ];

        $this->config->update([
            'token' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $this->config->refresh();
    }

    /**
     * Faz requisição autenticada para a API Cora.
     */
    protected function request(string $method, string $endpoint, array $data = [], array $query = [], bool $usarIdempotencia = false): array
    {
        $token = $this->obterToken();
        $tokenScopes = $this->extrairScopesDoToken($token);
        $this->ultimoTokenScopes = $tokenScopes;
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');

        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'User-Agent: GestorNow/2.0',
            'Expect:',
        ];

        $idempotencyKey = null;
        if ($usarIdempotencia) {
            $idempotencyKey = (string) Str::uuid();
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        $method = strtoupper($method);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('services.cora.timeout', 60));
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
        } elseif (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $requestHeaders = (string) curl_getinfo($ch, CURLINFO_HEADER_OUT);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com API Cora: ' . $error);
        }

        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true) ?? [],
            'raw' => $response,
            'request_headers' => $requestHeaders,
            'idempotency_key' => $idempotencyKey,
            'token_scopes' => $tokenScopes,
        ];
    }

    /**
     * Gera um boleto na Cora e salva no sistema.
     */
    public function gerarBoleto(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): Boleto
    {
        $payload = $this->montarPayloadBoleto($conta, $empresa, $cliente);

        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $payload, 'integracao' => 'cora']
        );

        $result = $this->request('POST', '/v2/invoices/', $payload, [], true);
        $data = $result['response'];

        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $payload, 'response' => $data, 'http_code' => $result['http_code'], 'integracao' => 'cora']
        );

        if ((int) $result['http_code'] !== 200 || empty($data['id'])) {
            $headersEnviados = array_filter([
                'Authorization: Bearer ***',
                'Accept: application/json',
                'Content-Type: application/json',
                $result['idempotency_key'] ? 'Idempotency-Key: ' . $result['idempotency_key'] : null,
            ]);

            Log::error('Erro ao gerar boleto Cora', [
                'http_code' => $result['http_code'],
                'response' => $data,
                'response_raw' => $result['raw'] ?? null,
                'request_headers_raw' => $result['request_headers'] ?? null,
                'headers_enviados' => $headersEnviados,
                'token_scopes' => $result['token_scopes'] ?? [],
                'conta_id' => $conta->id_contas,
                'id_empresa' => $empresa->id_empresa,
            ]);

            $mensagem = $this->extrairMensagemErroApi(
                $data,
                'Erro ao gerar boleto na Cora.',
                'emissao_boleto',
                is_string($result['raw'] ?? null) ? $result['raw'] : null
            );

            $mensagem = $this->enriquecerMensagemErroAutorizacaoEmissao(
                $mensagem,
                $data,
                (array) ($result['token_scopes'] ?? [])
            );

            throw new Exception('Erro na emissao Cora (HTTP ' . (int) $result['http_code'] . '): ' . $mensagem);
        }

        $boleto = Boleto::create([
            'id_empresa' => $empresa->id_empresa,
            'id_conta_receber' => $conta->id_contas,
            'id_bancos' => $this->config?->id_bancos,
            'id_banco_boleto' => $this->config?->id_banco_boleto,
            'codigo_solicitacao' => $data['id'],
            'nosso_numero' => $this->extrairNossoNumero($data),
            'linha_digitavel' => $this->extrairLinhaDigitavel($data),
            'codigo_barras' => $this->extrairCodigoBarras($data),
            'valor_nominal' => $conta->valor_total,
            'data_emissao' => now()->toDateString(),
            'data_vencimento' => $conta->data_vencimento,
            'status' => $this->mapearStatus((string) ($data['status'] ?? 'OPEN')),
            'situacao_banco' => $data['status'] ?? null,
            'url_pdf' => $this->extrairUrlPdf($data),
            'json_resposta' => json_encode($data),
        ]);

        BoletoHistorico::where('id_empresa', $empresa->id_empresa)
            ->whereNull('id_boleto')
            ->latest('id_historico')
            ->limit(2)
            ->update(['id_boleto' => $boleto->id_boleto]);

        return $boleto;
    }

    /**
     * Obtém PDF do boleto na Cora.
     */
    public function obterPdf(Boleto $boleto): string
    {
        $urlPdf = $boleto->url_pdf;

        if (!$urlPdf) {
            $detalhes = $this->consultarDetalhesBrutos($boleto->codigo_solicitacao);
            $urlPdf = $this->extrairUrlPdf($detalhes);

            if ($urlPdf) {
                $boleto->update(['url_pdf' => $urlPdf]);
            }
        }

        if (!$urlPdf) {
            throw new Exception('URL do PDF não foi retornada pela Cora para este boleto.');
        }

        return $this->downloadPdf($urlPdf);
    }

    /**
     * Consulta um boleto na Cora e sincroniza o status local.
     */
    public function consultarBoleto(Boleto $boleto): array
    {
        $result = $this->request('GET', '/v2/invoices/' . urlencode((string) $boleto->codigo_solicitacao));

        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            ['response' => $result['response'], 'http_code' => $result['http_code'], 'integracao' => 'cora']
        );

        if ((int) $result['http_code'] !== 200) {
            $mensagem = $this->extrairMensagemErroApi(
                $result['response'],
                'Erro ao consultar boleto na Cora.',
                'consulta_boleto',
                is_string($result['raw'] ?? null) ? $result['raw'] : null
            );

            throw new Exception('Erro na consulta Cora (HTTP ' . (int) $result['http_code'] . '): ' . $mensagem);
        }

        $data = $result['response'];
        $statusBanco = (string) ($data['status'] ?? '');
        $statusInterno = $this->mapearStatus($statusBanco);

        $updates = [
            'status' => $statusInterno,
            'situacao_banco' => $statusBanco !== '' ? $statusBanco : $boleto->situacao_banco,
            'linha_digitavel' => $this->extrairLinhaDigitavel($data) ?? $boleto->linha_digitavel,
            'codigo_barras' => $this->extrairCodigoBarras($data) ?? $boleto->codigo_barras,
            'nosso_numero' => $this->extrairNossoNumero($data) ?? $boleto->nosso_numero,
            'url_pdf' => $this->extrairUrlPdf($data) ?? $boleto->url_pdf,
            'json_resposta' => json_encode($data),
        ];

        if (isset($data['total_amount']) && is_numeric($data['total_amount'])) {
            $updates['valor_nominal'] = $this->centavosParaValor((int) $data['total_amount']);
        }

        if ($statusInterno === Boleto::STATUS_PAGO) {
            if (isset($data['total_paid']) && is_numeric($data['total_paid'])) {
                $updates['valor_pago'] = $this->centavosParaValor((int) $data['total_paid']);
            }

            $dataPagamento = $this->extrairDataPagamento($data);
            if ($dataPagamento !== null) {
                $updates['data_pagamento'] = $dataPagamento;
            }
        }

        $boleto->update($updates);

        return [
            'status' => $statusInterno,
            'status_banco' => $statusBanco,
            'valor' => $updates['valor_nominal'] ?? $boleto->valor_nominal,
            'valor_pago' => $updates['valor_pago'] ?? $boleto->valor_pago,
            'data_pagamento' => $updates['data_pagamento'] ?? $boleto->data_pagamento,
            'dados_completos' => $data,
        ];
    }

    /**
     * Cancela um boleto na Cora.
     */
    public function cancelarBoleto(Boleto $boleto, string $motivo = 'Solicitado pelo cliente'): bool
    {
        $result = $this->request('DELETE', '/v2/invoices/' . urlencode((string) $boleto->codigo_solicitacao));

        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            [
                'acao' => 'cancelamento',
                'motivo' => $motivo,
                'http_code' => $result['http_code'],
                'response' => $result['response'],
                'integracao' => 'cora',
            ]
        );

        if (in_array((int) $result['http_code'], [200, 204], true)) {
            $boleto->update([
                'status' => Boleto::STATUS_CANCELADO,
                'situacao_banco' => 'CANCELLED',
            ]);

            return true;
        }

        Log::warning('Falha ao cancelar boleto na Cora', [
            'boleto_id' => $boleto->id_boleto,
            'codigo_solicitacao' => $boleto->codigo_solicitacao,
            'http_code' => $result['http_code'],
            'response' => $result['response'],
        ]);

        return false;
    }

    /**
     * Cancela o boleto atual e gera um novo com vencimento/valor atualizados.
     */
    public function alterarVencimento(Boleto $boleto, string $novaDataVencimento, float $novoValor): Boleto
    {
        $this->cancelarBoleto($boleto, 'Troca de vencimento');

        $conta = $boleto->contaAReceber;
        if (!$conta) {
            throw new Exception('Conta a receber não encontrada.');
        }

        $empresa = Empresa::find($boleto->id_empresa);
        if (!$empresa) {
            throw new Exception('Empresa não encontrada.');
        }

        $cliente = Cliente::find($conta->id_clientes);
        if (!$cliente) {
            throw new Exception('Cliente não encontrado.');
        }

        $conta->data_vencimento = $novaDataVencimento;
        $conta->valor_total = $novoValor;
        $conta->save();

        return $this->gerarBoleto($conta, $empresa, $cliente);
    }

    /**
     * Webhook da Cora ainda não está ligado no fluxo padrão.
     */
    public function processarWebhook(array $dados): void
    {
        Log::info('Webhook Cora recebido (sem processador específico).', [
            'payload' => $dados,
        ]);
    }

    /**
     * Busca os detalhes da fatura sem alterar dados locais.
     */
    protected function consultarDetalhesBrutos(string $invoiceId): array
    {
        $result = $this->request('GET', '/v2/invoices/' . urlencode($invoiceId));

        if ((int) $result['http_code'] !== 200) {
            $mensagem = $this->extrairMensagemErroApi(
                $result['response'],
                'Erro ao consultar boleto na Cora.',
                'consulta_boleto',
                is_string($result['raw'] ?? null) ? $result['raw'] : null
            );

            throw new Exception('Erro na consulta Cora (HTTP ' . (int) $result['http_code'] . '): ' . $mensagem);
        }

        return $result['response'];
    }

    /**
     * Faz o download do PDF usando a URL retornada pela Cora.
     */
    protected function downloadPdf(string $url): string
    {
        $token = $this->obterToken();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Accept: application/pdf, application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('services.cora.timeout', 60));

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($content === false) {
            throw new Exception('Erro ao baixar PDF na Cora: ' . $error);
        }

        if ((int) $httpCode !== 200) {
            throw new Exception('Erro ao obter PDF do boleto na Cora. HTTP: ' . $httpCode);
        }

        return $content;
    }

    /**
     * Monta o payload de emissão da API Cora.
     */
    protected function montarPayloadBoleto(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): array
    {
        if (!$this->config) {
            throw new Exception('Configuração de boleto não definida.');
        }

        $nomePagador = trim((string) ($cliente->nome ?: $cliente->razao_social));
        if ($nomePagador === '') {
            throw new Exception('Não foi possível gerar boleto: cliente sem nome/razão social.');
        }

        $documento = preg_replace('/[^\d]/', '', (string) $cliente->cpf_cnpj);
        if ($documento === '') {
            throw new Exception('Não foi possível gerar boleto: cliente sem CPF/CNPJ.');
        }

        $tipoDocumento = strlen($documento) > 11 ? 'CNPJ' : 'CPF';
        $emailPagador = $this->resolverEmailPagador($cliente, $empresa);

        if ($emailPagador === '') {
            throw new Exception('Não foi possível gerar boleto: cliente sem e-mail válido.');
        }

        $cidade = $this->limitarTexto(trim((string) ($cliente->cidade ?? $empresa->cidade ?? '')), 60);
        $uf = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($cliente->uf ?? $empresa->uf ?? '')), 0, 2));

        if ($cidade === '' || $uf === '') {
            throw new Exception('Não foi possível gerar boleto: informe cidade e UF no cadastro do cliente ou da empresa.');
        }

        $cep = preg_replace('/[^\d]/', '', (string) ($cliente->cep ?: $empresa->cep));
        if ($cep === '') {
            throw new Exception('Não foi possível gerar boleto: informe CEP no cadastro do cliente ou da empresa.');
        }

        $valorCentavos = $this->valorParaCentavos((float) $conta->valor_total);
        if ($valorCentavos <= 0) {
            throw new Exception('Não foi possível gerar boleto: valor inválido.');
        }

        $payload = [
            'code' => $this->montarCodigoExterno($conta),
            'customer' => [
                'name' => $this->limitarTexto($nomePagador, 60),
                'email' => $this->limitarTexto($emailPagador, 60),
                'document' => [
                    'identity' => $documento,
                    'type' => $tipoDocumento,
                ],
                'address' => [
                    'street' => $this->limitarTexto((string) ($cliente->endereco ?: $empresa->endereco ?: 'Não informado'), 120),
                    'number' => $this->limitarTexto((string) ($cliente->numero ?: $empresa->numero ?: 'S/N'), 20),
                    'district' => $this->limitarTexto((string) ($cliente->bairro ?: $empresa->bairro ?: 'Centro'), 80),
                    'city' => $cidade,
                    'state' => $uf,
                    'complement' => $this->limitarTexto((string) ($cliente->complemento ?: $empresa->complemento ?: ''), 80),
                    'zip_code' => $cep,
                ],
            ],
            'services' => [
                [
                    'name' => $this->limitarTexto((string) ($conta->descricao ?: ('Conta #' . $conta->id_contas)), 80),
                    'description' => $this->limitarTexto((string) ($conta->descricao ?: 'Cobrança via GestorNow'), 500),
                    'amount' => $valorCentavos,
                ],
            ],
            'payment_terms' => [
                'due_date' => $conta->data_vencimento->format('Y-m-d'),
            ],
            'payment_forms' => ['BANK_SLIP'],
        ];

        if ((float) $this->config->juros_mora > 0) {
            $payload['payment_terms']['interest'] = [
                'rate' => (float) $this->config->juros_mora,
            ];
        }

        if ((float) $this->config->multa_atraso > 0) {
            $multaCentavos = (int) round($valorCentavos * ((float) $this->config->multa_atraso / 100));
            if ($multaCentavos > 0) {
                $payload['payment_terms']['fine'] = [
                    'amount' => $multaCentavos,
                ];
            }
        }

        return $payload;
    }

    protected function montarCodigoExterno(ContasAReceber $conta): string
    {
        $base = trim((string) ($conta->documento ?: ('conta_' . $conta->id_contas)));
        $base = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
        $codigo = $base . '_' . now()->format('YmdHis');

        return $this->limitarTexto($codigo, 80);
    }

    protected function mapearStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID' => Boleto::STATUS_PAGO,
            'CANCELLED' => Boleto::STATUS_CANCELADO,
            'OPEN', 'IN_PAYMENT', 'LATE', 'DRAFT', 'INITIATED', 'RECURRENCE_DRAFT' => Boleto::STATUS_GERADO,
            default => Boleto::STATUS_GERADO,
        };
    }

    protected function extrairLinhaDigitavel(array $data): ?string
    {
        return $this->extrairPrimeiroValor($data, [
            'bankslip.digitable_line',
            'bankslip.digitableLine',
            'bank_slip.digitable_line',
            'digitable_line',
        ]);
    }

    protected function extrairCodigoBarras(array $data): ?string
    {
        return $this->extrairPrimeiroValor($data, [
            'bankslip.barcode',
            'bankslip.bar_code',
            'bank_slip.barcode',
            'barcode',
        ]);
    }

    protected function extrairNossoNumero(array $data): ?string
    {
        return $this->extrairPrimeiroValor($data, [
            'bankslip.our_number',
            'bankslip.ourNumber',
            'bank_slip.our_number',
            'our_number',
        ]);
    }

    protected function extrairUrlPdf(array $data): ?string
    {
        return $this->extrairPrimeiroValor($data, [
            'document_url',
            'documentUrl',
            'bankslip.document_url',
            'bankslip.documentUrl',
        ]);
    }

    protected function extrairDataPagamento(array $data): ?string
    {
        $dataPagamento = $this->extrairPrimeiroValor($data, [
            'occurrence_date',
            'occurrenceDate',
            'payments.0.payment_date',
            'payments.0.paid_at',
            'payments.0.paidAt',
            'payments.0.created_at',
        ]);

        if (!$dataPagamento) {
            return null;
        }

        return substr((string) $dataPagamento, 0, 19);
    }

    protected function extrairPrimeiroValor(array $data, array $caminhos): ?string
    {
        foreach ($caminhos as $caminho) {
            $valor = data_get($data, $caminho);
            if ($valor !== null && $valor !== '') {
                return (string) $valor;
            }
        }

        return null;
    }

    protected function resolverEmailPagador(Cliente $cliente, Empresa $empresa): string
    {
        $email = trim((string) ($cliente->email ?: $empresa->email));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return '';
    }

    protected function valorParaCentavos(float $valor): int
    {
        return (int) round($valor * 100);
    }

    protected function centavosParaValor(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    protected function limitarTexto(string $texto, int $limite): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));

        if ($texto === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($texto, 0, $limite);
        }

        return substr($texto, 0, $limite);
    }

    protected function extrairMensagemErroApi(?array $data, string $fallback, ?string $contexto = null, ?string $raw = null): string
    {
        if (!is_array($data) || $data === []) {
            if ($raw !== null && trim($raw) !== '') {
                $resumo = trim(preg_replace('/\s+/', ' ', $raw));
                return $fallback . ' | resposta: ' . substr($resumo, 0, 300);
            }

            return $fallback;
        }

        $mensagens = [];

        foreach (['message', 'mensagem', 'error', 'erro', 'detail', 'detalhe', 'code'] as $campo) {
            if (isset($data[$campo]) && is_scalar($data[$campo])) {
                $mensagens[] = (string) $data[$campo];
            }
        }

        if (!empty($data['errors']) && is_array($data['errors'])) {
            // Quando a API retorna um único erro em objeto associativo
            if (array_key_exists('id', $data['errors']) || array_key_exists('message', $data['errors']) || array_key_exists('code', $data['errors'])) {
                $erro = $data['errors'];
                $id = (string) ($erro['id'] ?? $erro['field'] ?? $erro['code'] ?? '');
                $msg = (string) ($erro['message'] ?? $erro['mensagem'] ?? '');
                $texto = trim(($id !== '' ? $id . ': ' : '') . $msg);
                if ($texto !== '') {
                    $mensagens[] = $texto;
                }
            } else {
                foreach ($data['errors'] as $erro) {
                    if (!is_array($erro)) {
                        continue;
                    }

                    $id = (string) ($erro['id'] ?? $erro['field'] ?? $erro['code'] ?? '');
                    $msg = (string) ($erro['message'] ?? $erro['mensagem'] ?? '');

                    $texto = trim(($id !== '' ? $id . ': ' : '') . $msg);
                    if ($texto !== '') {
                        $mensagens[] = $texto;
                    }
                }
            }
        }

        $mensagens = array_values(array_unique(array_filter(array_map('trim', $mensagens))));

        if ($mensagens === []) {
            if ($raw !== null && trim($raw) !== '') {
                $resumo = trim(preg_replace('/\s+/', ' ', $raw));
                $mensagens[] = 'resposta: ' . substr($resumo, 0, 300);
            }
        }

        $mensagem = $mensagens === [] ? $fallback : implode(' | ', $mensagens);
        return $this->enriquecerMensagemErroConhecido($mensagem, $data, $contexto);
    }

    protected function enriquecerMensagemErroConhecido(string $mensagem, ?array $data, ?string $contexto): string
    {
        $mensagemLower = strtolower($mensagem);

        $redirectUriInvalido = str_contains($mensagemLower, 'redirect_uri')
            || str_contains($mensagemLower, 'invalid parameter: redirect_uri')
            || str_contains($mensagemLower, 'invalid parameter redirect_uri');

        if ($contexto === 'oauth_token' && $redirectUriInvalido) {
            return $mensagem
                . ' | dica: o redirect_uri enviado precisa ser exatamente o mesmo cadastrado no app da Cora (incluindo protocolo, domínio, path e barra final).'
                . ' Configure CORA_REDIRECT_URI com o valor exato e reutilize o mesmo URI no authorize + token exchange.';
        }

        $headerInvalido = str_contains($mensagemLower, 'header is required')
            || str_contains($mensagemLower, 'header é obrigatório')
            || str_contains($mensagemLower, 'header e obrigatorio')
            || str_contains($mensagemLower, 'idempotency-key')
            || str_contains($mensagemLower, 'authorization');

        if (!$headerInvalido) {
            return $mensagem;
        }

        if ($contexto === 'oauth_token') {
            return $mensagem
                . ' | dica: envie Authorization: Basic base64(client_id:client_secret) e Content-Type: application/x-www-form-urlencoded no POST /oauth/token.';
        }

        if ($contexto === 'emissao_boleto') {
            return $mensagem
                . ' | dica: confira os headers Authorization: Bearer <token> e Idempotency-Key (UUID) na emissão do boleto.';
        }

        if ($contexto === 'consulta_boleto') {
            return $mensagem
                . ' | dica: confira o header Authorization: Bearer <token> na consulta do boleto.';
        }

        return $mensagem;
    }

    protected function enriquecerMensagemErroAutorizacaoEmissao(string $mensagem, ?array $data, array $tokenScopes = []): string
    {
        $codigosErro = $this->extrairCodigosErro($data);
        if (!in_array('authorization', $codigosErro, true)) {
            return $mensagem;
        }

        $scopes = array_values(array_filter(array_map(static function ($s) {
            return strtolower(trim((string) $s));
        }, $tokenScopes)));

        if (!in_array('invoice', $scopes, true)) {
            $scopeTexto = $scopes === [] ? 'nenhum' : implode(' ', $scopes);
            return $mensagem
                . ' | causa provavel: o token atual nao possui escopo invoice (scope recebido: '
                . $scopeTexto
                . '). Na API /v2/invoices da Cora, isso costuma ocorrer com client_credentials de Parceria (apenas offline_access). Use token com escopo invoice (Authorization Code da conta autorizada) ou Integracao Direta com mTLS.';
        }

        return $mensagem
            . ' | causa provavel: token/credencial sem permissao para emissao na conta atual. Confira ambiente (stage/producao), conta autorizada e permissoes do app na Cora.';
    }

    protected function extrairCodigosErro(?array $data): array
    {
        if (!is_array($data) || empty($data['errors']) || !is_array($data['errors'])) {
            return [];
        }

        $erros = $data['errors'];
        $codigos = [];

        if (array_key_exists('code', $erros) || array_key_exists('id', $erros)) {
            $codigo = (string) ($erros['code'] ?? $erros['id'] ?? '');
            if ($codigo !== '') {
                $codigos[] = strtolower($codigo);
            }

            return array_values(array_unique($codigos));
        }

        foreach ($erros as $erro) {
            if (!is_array($erro)) {
                continue;
            }

            $codigo = (string) ($erro['code'] ?? $erro['id'] ?? '');
            if ($codigo !== '') {
                $codigos[] = strtolower($codigo);
            }
        }

        return array_values(array_unique($codigos));
    }

    protected function extrairScopesDoToken(string $jwt): array
    {
        $partes = explode('.', $jwt);
        if (count($partes) < 2) {
            return [];
        }

        $payloadBase64 = strtr($partes[1], '-_', '+/');
        $padding = strlen($payloadBase64) % 4;
        if ($padding > 0) {
            $payloadBase64 .= str_repeat('=', 4 - $padding);
        }

        $payloadJson = base64_decode($payloadBase64, true);
        if ($payloadJson === false) {
            return [];
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return [];
        }

        $scope = trim((string) ($payload['scope'] ?? ''));
        if ($scope === '') {
            return [];
        }

        return preg_split('/\s+/', strtolower($scope)) ?: [];
    }
}
