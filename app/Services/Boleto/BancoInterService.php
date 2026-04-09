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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class BancoInterService
{
    protected $config;
    protected $accessToken;
    protected $baseUrl = 'https://cdpj.partners.bancointer.com.br';
    
    /**
     * Construtor do serviço.
     */
    public function __construct(?BancoBoletoConfig $config = null)
    {
        $this->config = $config;
    }

    /**
     * Define a configuração a ser usada.
     */
    public function setConfig(BancoBoletoConfig $config): self
    {
        $this->config = $config;
        $this->accessToken = null; // Reset token ao trocar config
        return $this;
    }

    /**
     * Obtém o token de acesso OAuth2.
     */
    protected function obterToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        if (!$this->config) {
            throw new Exception('Configuração de boleto não definida.');
        }

        $certPath = $this->config->caminho_completo_certificado;
        $keyPath = $this->config->caminho_completo_chave;

        if (!file_exists($certPath)) {
            throw new Exception('Arquivo de certificado não encontrado: ' . $certPath);
        }

        if (!file_exists($keyPath)) {
            throw new Exception('Arquivo de chave não encontrado: ' . $keyPath);
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/oauth/v2/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id' => $this->config->client_id,
            'client_secret' => $this->config->client_secret,
            'scope' => 'boleto-cobranca.write boleto-cobranca.read',
            'grant_type' => 'client_credentials',
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com API Inter: ' . $error);
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200 || !isset($data['access_token'])) {
            Log::error('Erro ao obter token Inter', [
                'http_code' => $httpCode,
                'response' => $response,
            ]);
            throw new Exception('Erro ao obter token de acesso: ' . ($data['message'] ?? 'Erro desconhecido'));
        }

        $this->accessToken = $data['access_token'];
        return $this->accessToken;
    }

    /**
     * Configura o webhook de retorno.
     */
    public function configurarWebhook(string $webhookUrl): bool
    {
        $token = $this->obterToken();
        
        $certPath = $this->config->caminho_completo_certificado;
        $keyPath = $this->config->caminho_completo_chave;
        $contaCorrente = $this->obterContaCorrente();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/cobranca/v3/cobrancas/webhook');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'x-conta-corrente: ' . $contaCorrente,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['webhookUrl' => $webhookUrl]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204) {
            $this->config->update(['webhook_ativo' => true]);
            Log::info('Webhook Inter configurado com sucesso', [
                'webhook_url' => $webhookUrl,
                'conta_corrente' => $contaCorrente,
            ]);
            return true;
        }

        Log::error('Erro ao configurar webhook Inter', [
            'http_code' => $httpCode,
            'response' => $response,
            'webhook_url' => $webhookUrl,
            'conta_corrente' => $contaCorrente,
            'cert_exists' => file_exists($certPath),
            'key_exists' => file_exists($keyPath),
        ]);
        
        return false;
    }

    /**
     * Monta a URL pública do webhook de retorno.
     */
    protected function obterWebhookUrl(): string
    {
        $webhookUrl = trim((string) config('services.banco_inter.webhook_url', ''));

        if ($webhookUrl === '') {
            $baseUrl = rtrim((string) config('app.url'), '/');

            if ($baseUrl === '') {
                throw new Exception('APP_URL não configurada para montagem do webhook do Banco Inter.');
            }

            $webhookUrl = $baseUrl . '/api/webhooks/boletos/inter';
        }

        return rtrim($webhookUrl, '/');
    }

    /**
     * Garante que o webhook da conta esteja registrado no Inter.
     * Não bloqueia a geração se falhar - apenas loga e tenta novamente na próxima.
     */
    protected function garantirWebhookAtivo(string $webhookUrl): void
    {
        if ($this->config->webhook_ativo) {
            return;
        }

        // Tenta configurar o webhook, mas não bloqueia a geração se falhar
        // (comportamento igual ao legado: tenta na primeira geração, marca flag se ok)
        if ($this->configurarWebhook($webhookUrl)) {
            $this->config->refresh();
        } else {
            Log::warning('Webhook Inter não foi configurado. A baixa automática não funcionará até que o webhook seja ativado.', [
                'webhook_url' => $webhookUrl,
            ]);
        }
    }

    /**
     * Retorna a conta corrente no formato esperado pela API do Inter.
     */
    protected function obterContaCorrente(): string
    {
        $conta = preg_replace('/[^\d]/', '', (string) ($this->config->banco->conta ?? ''));
        $contaDigito = preg_replace('/[^\d]/', '', (string) ($this->config->banco->conta_digito ?? ''));

        if ($conta === '') {
            throw new Exception('Conta corrente do banco não configurada.');
        }

        if ($contaDigito !== '' && substr($conta, -strlen($contaDigito)) !== $contaDigito) {
            return $conta . $contaDigito;
        }

        return $conta;
    }

    /**
     * Gera um boleto bancário.
     */
    public function gerarBoleto(ContasAReceber $conta, Empresa $empresa, Cliente $cliente): Boleto
    {
        $token = $this->obterToken();
        
        $certPath = $this->config->caminho_completo_certificado;
        $keyPath = $this->config->caminho_completo_chave;
        $contaCorrente = $this->obterContaCorrente();
        $webhookUrl = $this->obterWebhookUrl();

        // Mesmo comportamento do legado: registra webhook na primeira geração.
        $this->garantirWebhookAtivo($webhookUrl);

        // Preparar dados do pagador
        $cpfCnpj = preg_replace('/[^\d]/', '', $cliente->cpf_cnpj);
        $tipoPessoa = strlen($cpfCnpj) === 14 ? 'JURIDICA' : 'FISICA';
        $cep = preg_replace('/[^\d]/', '', $cliente->cep);
        $cidadePagador = $this->resolverCidadePagador($cliente, $empresa);
        $ufPagador = $this->resolverUfPagador($cliente, $empresa);
        $telefonePagador = $this->resolverTelefonePagador($cliente, $empresa);

        if ($cidadePagador === '' || $ufPagador === '') {
            throw new Exception('Não foi possível gerar boleto: o pagador precisa ter cidade e UF válidos no cadastro do cliente ou da empresa.');
        }

        $nomePagador = trim((string) ($cliente->nome ?: $cliente->razao_social));
        if ($nomePagador === '') {
            throw new Exception('Não foi possível gerar boleto: cliente sem nome/razão social.');
        }

        $pagador = [
            'cpfCnpj' => $cpfCnpj,
            'tipoPessoa' => $tipoPessoa,
            'nome' => $this->limitarTexto($nomePagador, 100),
            'endereco' => $this->limitarTexto((string) ($cliente->endereco ?: ''), 255),
            'cidade' => $cidadePagador,
            'uf' => $ufPagador,
            'cep' => $cep,
            'email' => $this->limitarTexto((string) ($cliente->email ?: ''), 255),
            'numero' => $this->limitarTexto((string) ($cliente->numero ?: ''), 10),
            'complemento' => $this->limitarTexto((string) ($cliente->complemento ?: ''), 50),
            'bairro' => $this->limitarTexto((string) ($cliente->bairro ?: ''), 60),
        ];

        if ($telefonePagador !== null) {
            $pagador['ddd'] = $telefonePagador['ddd'];
            $pagador['telefone'] = $telefonePagador['telefone'];
        }

        // Preparar dados do boleto
        $dataVencimento = $conta->data_vencimento->format('Y-m-d');
        $valorNominal = number_format((float) $conta->valor_total, 2, '.', '');

        $dadosBoleto = [
            'seuNumero' => $conta->documento ?: (string) $conta->id_contas,
            'valorNominal' => (float) $valorNominal,
            'valorAbatimento' => 0,
            'dataVencimento' => $dataVencimento,
            'numDiasAgenda' => 30,
            'pagador' => $pagador,
        ];

        // Envia a URL do webhook também na cobrança para reforçar o callback.
        $dadosBoleto['webhookUrl'] = $webhookUrl;

        // Adicionar multa se configurada
        if ($this->config->multa_atraso > 0) {
            $dadosBoleto['multa'] = [
                'taxa' => number_format((float) $this->config->multa_atraso, 2, '.', ''),
                'codigo' => 'PERCENTUAL',
            ];
        }

        // Adicionar juros se configurado
        if ($this->config->juros_mora > 0) {
            $dadosBoleto['mora'] = [
                'taxa' => number_format((float) $this->config->juros_mora, 2, '.', ''),
                'codigo' => 'TAXAMENSAL',
            ];
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/cobranca/v3/cobrancas');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'x-conta-corrente: ' . $contaCorrente,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dadosBoleto));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('Erro ao conectar com API Inter: ' . $error);
        }

        $data = json_decode($response, true);

        // Registrar histórico da geração
        BoletoHistorico::registrar(
            null,
            $empresa->id_empresa,
            BoletoHistorico::TIPO_GERACAO,
            ['request' => $dadosBoleto, 'response' => $data, 'http_code' => $httpCode]
        );

        if ($httpCode !== 200 || !isset($data['codigoSolicitacao'])) {
            Log::error('Erro ao gerar boleto Inter', [
                'http_code' => $httpCode,
                'response' => $response,
                'conta_id' => $conta->id_contas,
                'request' => $dadosBoleto,
            ]);

            throw new Exception(
                $this->montarMensagemErroGeracaoBoleto(
                    $httpCode,
                    is_array($data) ? $data : null,
                    (string) $response
                )
            );
        }

        // Criar registro do boleto
        $boleto = Boleto::create([
            'id_empresa' => $empresa->id_empresa,
            'id_conta_receber' => $conta->id_contas,
            'id_bancos' => $this->config->id_bancos,
            'id_banco_boleto' => $this->config->id_banco_boleto,
            'codigo_solicitacao' => $data['codigoSolicitacao'],
            'valor_nominal' => $conta->valor_total,
            'data_emissao' => now()->toDateString(),
            'data_vencimento' => $conta->data_vencimento,
            'status' => Boleto::STATUS_GERADO,
            'json_resposta' => json_encode($data),
        ]);

        // Atualizar histórico com ID do boleto
        BoletoHistorico::where('id_empresa', $empresa->id_empresa)
            ->whereNull('id_boleto')
            ->latest('id_historico')
            ->first()
            ?->update(['id_boleto' => $boleto->id_boleto]);

        return $boleto;
    }

    /**
     * Resolve a cidade do pagador com fallback para a cidade da empresa.
     */
    private function resolverCidadePagador(Cliente $cliente, Empresa $empresa): string
    {
        $cidade = trim((string) ($cliente->cidade ?? ''));

        if ($cidade === '') {
            $cidade = trim((string) ($empresa->cidade ?? ''));
        }

        return $this->limitarTexto($cidade, 60);
    }

    /**
     * Resolve a UF do pagador com fallback para a UF da empresa.
     */
    private function resolverUfPagador(Cliente $cliente, Empresa $empresa): string
    {
        $uf = trim((string) ($cliente->uf ?? ''));

        if ($uf === '') {
            $uf = trim((string) ($empresa->uf ?? ''));
        }

        $uf = strtoupper(preg_replace('/[^A-Za-z]/', '', $uf));
        if (strlen($uf) > 2) {
            $uf = substr($uf, 0, 2);
        }

        return $uf;
    }

    /**
     * Resolve telefone do pagador no formato exigido pelo Inter: ddd (2) + telefone (8/9).
     */
    private function resolverTelefonePagador(Cliente $cliente, Empresa $empresa): ?array
    {
        $candidatos = [
            $cliente->telefone ?? null,
            $empresa->telefone ?? null,
        ];

        foreach ($candidatos as $telefoneBruto) {
            $digits = preg_replace('/[^\d]/', '', (string) $telefoneBruto);
            if ($digits === '') {
                continue;
            }

            // Remove código do país quando informado (55)
            if (strlen($digits) > 11 && strpos($digits, '55') === 0) {
                $digits = substr($digits, 2);
            }

            // Mantém os últimos 11 dígitos para lidar com prefixos extras
            if (strlen($digits) > 11) {
                $digits = substr($digits, -11);
            }

            if (strlen($digits) === 10 || strlen($digits) === 11) {
                $ddd = substr($digits, 0, 2);
                $telefone = substr($digits, 2);

                if (strlen($telefone) >= 8 && strlen($telefone) <= 9) {
                    return [
                        'ddd' => $ddd,
                        'telefone' => $telefone,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Limita tamanho e remove espaços extras.
     */
    private function limitarTexto(string $valor, int $max): string
    {
        $valor = trim(preg_replace('/\s+/', ' ', $valor));
        if ($valor === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $max);
        }

        return substr($valor, 0, $max);
    }

    /**
     * Obtém o PDF de um boleto.
     */
    public function obterPdf(Boleto $boleto): string
    {
        $token = $this->obterToken();
        
        $certPath = $this->config->caminho_completo_certificado;
        $keyPath = $this->config->caminho_completo_chave;
        $contaCorrente = $this->obterContaCorrente();

        // A API do Inter pode levar alguns segundos para disponibilizar o PDF
        // após a geração da cobrança, retornando 400/404 temporariamente.
        // Após troca de vencimento, pode demorar mais, então aumentamos o retry.
        $maxTentativas = 8;
        $intervaloMs = 1500;
        $ultimoHttpCode = 0;
        $ultimoResponse = null;

        for ($tentativa = 1; $tentativa <= $maxTentativas; $tentativa++) {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/cobranca/v3/cobrancas/' . $boleto->codigo_solicitacao . '/pdf');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $token,
                'x-conta-corrente: ' . $contaCorrente,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
            curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new Exception('Erro ao conectar com API Inter: ' . $error);
            }

            $ultimoHttpCode = $httpCode;
            $ultimoResponse = $response;

            if ($httpCode === 200) {
                $data = json_decode($response, true);

                if (isset($data['pdf']) && !empty($data['pdf'])) {
                    return base64_decode($data['pdf']);
                }

                Log::warning('Resposta da API Inter sem PDF no corpo.', [
                    'codigo_solicitacao' => $boleto->codigo_solicitacao,
                    'tentativa' => $tentativa,
                ]);
            }

            $statusRetry = in_array($httpCode, [400, 404, 409, 422], true);
            if ($statusRetry && $tentativa < $maxTentativas) {
                usleep($intervaloMs * 1000);
                continue;
            }

            break;
        }

        Log::warning('Erro ao obter PDF do boleto na API Inter.', [
            'codigo_solicitacao' => $boleto->codigo_solicitacao,
            'http_code' => $ultimoHttpCode,
            'response' => is_string($ultimoResponse) ? substr($ultimoResponse, 0, 600) : null,
        ]);

        throw new Exception('Erro ao obter PDF do boleto. HTTP Code: ' . $ultimoHttpCode);
    }

    /**
     * Consulta a situação de um boleto.
     */
    public function consultarBoleto(Boleto $boleto): array
    {
        $token = $this->obterToken();
        
        $certPath = $this->config->caminho_completo_certificado;
        $keyPath = $this->config->caminho_completo_chave;
        $contaCorrente = $this->obterContaCorrente();

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/cobranca/v3/cobrancas/' . $boleto->codigo_solicitacao);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'x-conta-corrente: ' . $contaCorrente,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception('Erro ao consultar boleto. HTTP Code: ' . $httpCode);
        }

        return json_decode($response, true);
    }

    /**
     * Processa o webhook de retorno do Inter.
     */
    public function processarWebhook(array $dados): void
    {
        $eventos = $this->normalizarEventosWebhook($dados);

        foreach ($eventos as $evento) {
            if (!is_array($evento)) {
                continue;
            }

            $this->processarEventoWebhook($evento, $dados);
        }
    }

    /**
     * Processa um evento individual do webhook do Inter.
     */
    protected function processarEventoWebhook(array $boleto, array $payloadOriginal): void
    {
        $codigoSolicitacao = $boleto['codigoSolicitacao'] ?? null;
        $situacao = $boleto['situacao'] ?? null;
        $dataHoraSituacao = $boleto['dataHoraSituacao'] ?? null;

        if (!$codigoSolicitacao) {
            Log::warning('Webhook Inter sem código de solicitação', ['evento' => $boleto]);
            return;
        }

        $boletoModel = Boleto::where('codigo_solicitacao', $codigoSolicitacao)->first();

        if (!$boletoModel) {
            Log::warning('Boleto não encontrado para webhook', ['codigo' => $codigoSolicitacao]);
            return;
        }

        BoletoHistorico::registrar(
            $boletoModel->id_boleto,
            $boletoModel->id_empresa,
            BoletoHistorico::TIPO_WEBHOOK,
            $payloadOriginal
        );

        $boletoModel->situacao_banco = $situacao;
        $boletoModel->json_webhook = json_encode($payloadOriginal);

        if ($situacao === 'RECEBIDO' || $situacao === 'PAGO') {
            $boletoModel->status = Boleto::STATUS_PAGO;
            $boletoModel->data_pagamento = $this->resolverDataPagamento($dataHoraSituacao);
            $boletoModel->valor_pago = $boleto['valorRecebido'] ?? $boletoModel->valor_nominal;

            $this->darBaixaContaReceber($boletoModel);
        }

        $boletoModel->save();
    }

    /**
     * Normaliza payload do webhook para uma lista de eventos.
     */
    protected function normalizarEventosWebhook(array $dados): array
    {
        if (isset($dados['codigoSolicitacao'])) {
            return [$dados];
        }

        if (isset($dados['boleto']) && is_array($dados['boleto'])) {
            return [$dados['boleto']];
        }

        foreach (['data', 'cobranca', 'cobrancas', 'boletos'] as $chave) {
            if (!isset($dados[$chave]) || !is_array($dados[$chave])) {
                continue;
            }

            return $this->isListaArray($dados[$chave]) ? $dados[$chave] : [$dados[$chave]];
        }

        if ($this->isListaArray($dados)) {
            return $dados;
        }

        return [$dados];
    }

    /**
     * Verifica se o array possui índices sequenciais iniciando em zero.
     */
    protected function isListaArray(array $dados): bool
    {
        if ($dados === []) {
            return true;
        }

        return array_keys($dados) === range(0, count($dados) - 1);
    }

    /**
     * Resolve a data de pagamento recebida no webhook.
     */
    protected function resolverDataPagamento(?string $dataHoraSituacao)
    {
        if (!$dataHoraSituacao) {
            return now();
        }

        try {
            return new \DateTime($dataHoraSituacao);
        } catch (\Throwable $e) {
            Log::warning('Data inválida recebida no webhook Inter', [
                'dataHoraSituacao' => $dataHoraSituacao,
                'erro' => $e->getMessage(),
            ]);

            return now();
        }
    }

    /**
     * Monta mensagem detalhada para falhas na geração do boleto.
     */
    protected function montarMensagemErroGeracaoBoleto(int $httpCode, ?array $data, string $response): string
    {
        $detalhes = [];

        if (is_array($data)) {
            if (!empty($data['violacoes']) && is_array($data['violacoes'])) {
                foreach ($data['violacoes'] as $violacao) {
                    if (!is_array($violacao)) {
                        continue;
                    }

                    $propriedade = (string) (
                        $violacao['propriedade']
                        ?? $violacao['campo']
                        ?? $violacao['field']
                        ?? 'campo'
                    );

                    $razao = (string) (
                        $violacao['razao']
                        ?? $violacao['mensagem']
                        ?? $violacao['message']
                        ?? 'erro de validação'
                    );

                    $propriedade = str_replace('incluirCobrancaAsync.body.', '', $propriedade);
                    $detalhes[] = trim($propriedade . ': ' . $razao);
                }
            }

            foreach (['message', 'mensagem', 'detail', 'detalhe', 'title', 'titulo', 'error', 'erro'] as $chave) {
                if (isset($data[$chave]) && is_scalar($data[$chave])) {
                    $detalhes[] = (string) $data[$chave];
                }
            }
        }

        $detalhes = array_values(array_unique(array_filter(array_map('trim', $detalhes))));

        if ($detalhes !== []) {
            return 'Erro ao gerar boleto (HTTP ' . $httpCode . '): ' . implode(' | ', $detalhes);
        }

        $resumoResposta = trim(preg_replace('/\s+/', ' ', $response));
        if ($resumoResposta === '') {
            $resumoResposta = 'sem corpo de resposta';
        }

        return 'Erro ao gerar boleto (HTTP ' . $httpCode . '): ' . substr($resumoResposta, 0, 400);
    }

    /**
     * Dá baixa na conta a receber quando boleto é pago.
     */
    protected function darBaixaContaReceber(Boleto $boleto): void
    {
        $conta = $boleto->contaAReceber;
        
        if (!$conta || $conta->status === 'pago') {
            return;
        }

        $valorPago = $boleto->valor_pago ?? $boleto->valor_nominal;

        // Silenciar evento updated automático para evitar duplicação de log
        Cache::put(
            'audit_silenciar_updated_' . class_basename($conta) . '_' . $conta->getKey(),
            true,
            now()->addSeconds(20)
        );

        $valorAnterior = (float) ($conta->valor_pago ?? 0);

        $conta->update([
            'status' => 'pago',
            'valor_pago' => $valorPago,
            'data_pagamento' => $boleto->data_pagamento,
        ]);

        $conta->refresh();

        // Registrar log de atividade
        ActionLogger::logDireto(
            model: $conta,
            evento: 'baixa_boleto',
            acao: 'conta_receber.baixa_boleto',
            descricao: sprintf(
                'Baixa automática por boleto pago na conta #%d — Valor: R$ %s — Boleto #%d',
                $conta->id_contas,
                number_format((float) $valorPago, 2, ',', '.'),
                $boleto->id_boleto
            ),
            entidadeTipo: 'conta_receber',
            entidadeLabel: sprintf('Conta #%d — %s', $conta->id_contas, $conta->descricao ?? ''),
            valor: (float) $valorPago,
            contexto: [
                'evento' => 'baixa_boleto',
                'id_boleto' => $boleto->id_boleto,
                'nosso_numero' => $boleto->nosso_numero,
                'codigo_solicitacao' => $boleto->codigo_solicitacao,
                'situacao_banco' => $boleto->situacao_banco,
            ],
            antes: ['status' => 'pendente', 'valor_pago' => $valorAnterior],
            depois: ['status' => 'pago', 'valor_pago' => (float) $valorPago],
            icone: 'credit-card',
            cor: 'azul',
            tags: ['contas_receber', 'financeiro', 'recebimento', 'boleto', 'automatico']
        );

        Log::info('Conta a receber baixada via webhook Inter', [
            'conta_id' => $conta->id_contas,
            'boleto_id' => $boleto->id_boleto,
            'valor' => $valorPago,
        ]);
    }

    /**
     * Altera o vencimento de um boleto.
     * Cancela o boleto antigo e gera um novo com a nova data.
     */
    public function alterarVencimento(Boleto $boleto, string $novaDataVencimento, float $novoValor): Boleto
    {
        // Cancelar o boleto antigo
        $this->cancelarBoleto($boleto, 'Troca de Vencimento');

        // Buscar dados necessários para gerar novo boleto
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

        // Atualizar data de vencimento e valor na conta
        $conta->data_vencimento = $novaDataVencimento;
        $conta->valor_total = $novoValor;
        $conta->save();

        // Gerar novo boleto
        return $this->gerarBoleto($conta, $empresa, $cliente);
    }

    /**
     * Cancela um boleto no Banco Inter.
     */
    public function cancelarBoleto(Boleto $boleto, string $motivo = 'Solicitado pelo cliente'): bool
    {
        $token = $this->obterToken();

        $certPath = $this->config->caminho_completo_certificado;
        $keyPath = $this->config->caminho_completo_chave;
        $contaCorrente = $this->obterContaCorrente();

        $data = ['motivoCancelamento' => $motivo];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/cobranca/v3/cobrancas/' . $boleto->codigo_solicitacao . '/cancelar');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'x-conta-corrente: ' . $contaCorrente,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSLCERT, $certPath);
        curl_setopt($ch, CURLOPT_SSLKEY, $keyPath);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Registrar histórico (usando tipo 'consulta' pois 'cancelamento' não existe na tabela)
        BoletoHistorico::registrar(
            $boleto->id_boleto,
            $boleto->id_empresa,
            BoletoHistorico::TIPO_CONSULTA,
            [
                'acao' => 'cancelamento',
                'motivo' => $motivo,
                'http_code' => $httpCode,
                'response' => json_decode($response, true) ?? $response,
            ]
        );

        // HTTP 202 = sucesso no cancelamento
        if ($httpCode === 202) {
            $boleto->update([
                'status' => Boleto::STATUS_CANCELADO,
                'situacao_banco' => 'CANCELADO',
            ]);

            Log::info('Boleto cancelado com sucesso', [
                'boleto_id' => $boleto->id_boleto,
                'codigo_solicitacao' => $boleto->codigo_solicitacao,
                'motivo' => $motivo,
            ]);

            return true;
        }

        Log::warning('Falha ao cancelar boleto no Inter', [
            'boleto_id' => $boleto->id_boleto,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
        ]);

        // Não lança exceção para não bloquear operação de troca de vencimento
        // se o boleto já estiver cancelado ou não puder ser cancelado
        return false;
    }
}
