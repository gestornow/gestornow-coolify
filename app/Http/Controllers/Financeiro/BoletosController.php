<?php

namespace App\Http\Controllers\Financeiro;

use App\ActivityLog\ActionLogger;
use App\Http\Controllers\Controller;
use App\Models\Banco;
use App\Models\BancoBoleto;
use App\Models\BancoBoletoConfig;
use App\Models\Boleto;
use App\Models\BoletoHistorico;
use App\Models\ContasAReceber;
use App\Services\Boleto\BancoCoraService;
use App\Services\Boleto\BoletoService;
use App\Services\LimiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class BoletosController extends Controller
{
    protected BoletoService $boletoService;

    public function __construct(BoletoService $boletoService)
    {
        $this->boletoService = $boletoService;
    }

    /**
     * Lista os boletos.
     */
    public function index(Request $request)
    {
        $idEmpresa = session('id_empresa');

        // Query base
        $query = Boleto::where('id_empresa', $idEmpresa)
            ->with(['contaAReceber.cliente', 'banco', 'bancoBoleto']);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('id_bancos')) {
            $query->where('id_bancos', $request->id_bancos);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        if ($request->filled('busca')) {
            $busca = $request->busca;
            $query->where(function ($q) use ($busca) {
                $q->where('nosso_numero', 'like', "%{$busca}%")
                  ->orWhere('linha_digitavel', 'like', "%{$busca}%")
                  ->orWhereHas('contaAReceber', function ($qc) use ($busca) {
                      $qc->where('descricao', 'like', "%{$busca}%");
                  })
                  ->orWhereHas('contaAReceber.cliente', function ($qcl) use ($busca) {
                      $qcl->where('nome', 'like', "%{$busca}%");
                  });
            });
        }

        $boletos = $query->orderBy('created_at', 'desc')->paginate(20);

        // Estatísticas
        $stats = [
            'total' => Boleto::where('id_empresa', $idEmpresa)->count(),
            'gerados' => Boleto::where('id_empresa', $idEmpresa)->whereIn('status', [Boleto::STATUS_GERADO, Boleto::STATUS_PENDENTE])->count(),
            'pagos' => Boleto::where('id_empresa', $idEmpresa)->where('status', Boleto::STATUS_PAGO)->count(),
            'vencidos' => Boleto::where('id_empresa', $idEmpresa)->vencidos()->count(),
            'valor_total' => Boleto::where('id_empresa', $idEmpresa)->sum('valor_nominal'),
            'valor_recebido' => Boleto::where('id_empresa', $idEmpresa)->where('status', Boleto::STATUS_PAGO)->sum('valor_pago'),
            'valor_pendente' => Boleto::where('id_empresa', $idEmpresa)->whereIn('status', [Boleto::STATUS_GERADO, Boleto::STATUS_PENDENTE])->sum('valor_nominal'),
        ];

        // Bancos para filtro - pegar bancos que têm boletos gerados
        $bancos = Banco::where('id_empresa', $idEmpresa)
            ->whereIn('id_bancos', function ($q) use ($idEmpresa) {
                $q->select('id_bancos')
                  ->from('boletos')
                  ->where('id_empresa', $idEmpresa)
                  ->whereNotNull('id_bancos')
                  ->distinct();
            })
            ->get();

        return view('financeiro.boletos.index', compact('boletos', 'stats', 'bancos'));
    }

    /**
     * Retorna os bancos disponíveis para gerar boleto.
     */
    public function bancosDisponiveis(): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');
            $bancos = $this->boletoService->getBancosDisponiveis($idEmpresa);

            return response()->json([
                'success' => true,
                'bancos' => $bancos,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar bancos disponíveis para boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'bancos' => [],
                'message' => 'Erro ao buscar bancos disponíveis.',
            ], 500);
        }
    }

    /**
     * Gera um boleto para uma conta a receber.
     */
    public function gerar(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_conta_receber' => 'required|integer',
                'id_bancos' => 'required|integer',
            ]);

            $idEmpresa = session('id_empresa');

            // Buscar conta a receber
            $conta = ContasAReceber::where('id_empresa', $idEmpresa)
                ->where('id_contas', $request->id_conta_receber)
                ->first();

            if (!$conta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conta a receber não encontrada.',
                ], 404);
            }

            if ($conta->status === 'pago') {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta conta já foi paga.',
                ], 400);
            }

            // Buscar banco
            $banco = Banco::where('id_empresa', $idEmpresa)
                ->where('id_bancos', $request->id_bancos)
                ->first();

            if (!$banco) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banco não encontrado.',
                ], 404);
            }

            // Gerar boleto
            $boleto = $this->boletoService->gerarBoleto($conta, $banco);

            return response()->json([
                'success' => true,
                'message' => 'Boleto gerado com sucesso!',
                'boleto' => $boleto->load(['banco', 'bancoBoleto']),
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao gerar boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna o PDF de um boleto.
     */
    public function pdf(int $id): Response|JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $boleto = Boleto::where('id_empresa', $idEmpresa)
                ->where('id_boleto', $id)
                ->first();

            if (!$boleto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Boleto não encontrado.',
                ], 404);
            }

            // Boletos cancelados não têm PDF disponível na API
            if ($boleto->status === Boleto::STATUS_CANCELADO) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este boleto foi cancelado. O PDF não está mais disponível.',
                ], 400);
            }

            $pdfContent = $this->boletoService->obterPdfBoleto($boleto);

            ActionLogger::log($boleto->fresh(), 'pdf_visualizado');

            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="boleto_' . $boleto->id_boleto . '.pdf"');
        } catch (Exception $e) {
            Log::error('Erro ao obter PDF do boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao obter PDF do boleto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consulta a situação de um boleto.
     */
    public function consultar(int $id): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $boleto = Boleto::where('id_empresa', $idEmpresa)
                ->where('id_boleto', $id)
                ->first();

            if (!$boleto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Boleto não encontrado.',
                ], 404);
            }

            $situacao = $this->boletoService->consultarBoleto($boleto);

            ActionLogger::log($boleto->fresh(), 'consulta');

            return response()->json([
                'success' => true,
                'boleto' => $boleto,
                'situacao' => $situacao,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao consultar boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao consultar boleto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna os boletos de uma conta a receber.
     */
    public function porContaReceber(int $idContaReceber): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            // Verificar se a conta pertence à empresa
            $conta = ContasAReceber::where('id_empresa', $idEmpresa)
                ->where('id_contas', $idContaReceber)
                ->exists();

            if (!$conta) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conta a receber não encontrada.',
                ], 404);
            }

            $boletos = Boleto::where('id_conta_receber', $idContaReceber)
                ->where('id_empresa', $idEmpresa)
                ->with(['banco', 'bancoBoleto'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'boletos' => $boletos,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar boletos da conta: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar boletos.',
            ], 500);
        }
    }

    /**
     * Lista os bancos de boleto disponíveis para integração.
     */
    public function listarBancosBoleto(): JsonResponse
    {
        try {
            $bancosBoleto = BancoBoleto::where('ativo', true)
                ->orderBy('nome')
                ->get();

            $bancosBoleto->transform(function (BancoBoleto $bancoBoleto) {
                $nome = Str::lower((string) $bancoBoleto->nome);
                $codigo = Str::lower((string) $bancoBoleto->codigo_banco);
                $ehCora = Str::contains($nome, 'cora') || Str::contains($codigo, 'cora');
                $ehMercadoPago = Str::contains($nome, 'mercado pago')
                    || Str::contains($nome, 'mercadopago')
                    || Str::contains($codigo, 'mercado_pago')
                    || Str::contains($codigo, 'mercadopago');
                $ehPagHiper = Str::contains($nome, 'paghiper')
                    || Str::contains($nome, 'pag hiper')
                    || Str::contains($codigo, 'paghiper')
                    || Str::contains($codigo, 'pag_hiper');

                if ($ehCora) {
                    $bancoBoleto->ativo = false;
                }

                if ($ehMercadoPago) {
                    $bancoBoleto->instrucoes = 'Integracao Mercado Pago (Orders API): informe o Access Token no campo API Key. '
                        . 'Para boleto, o sistema envia payment_method.id=boleto e payment_method.type=ticket.';
                }

                if ($ehPagHiper) {
                    $bancoBoleto->instrucoes = 'Integracao PagHiper: preencha API Key e Token. '
                        . 'A emissao usa /transaction/create e o valor minimo por boleto e R$ 3,00.';
                }

                return $bancoBoleto;
            })->filter(function (BancoBoleto $bancoBoleto) {
                return (bool) $bancoBoleto->ativo;
            })->values();

            return response()->json([
                'success' => true,
                'bancos_boleto' => $bancosBoleto,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao listar bancos de boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao listar bancos de boleto.',
            ], 500);
        }
    }

    /**
     * Salva a configuração de boleto de um banco.
     */
    public function salvarConfiguracao(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_bancos' => 'required|integer',
                'id_banco_boleto' => 'required|integer',
                'client_id' => 'nullable|string|max:255',
                'client_secret' => 'nullable|string|max:500',
                'api_key' => 'nullable|string|max:255',
                'token' => 'nullable|string',
                'convenio' => 'nullable|string|max:50',
                'carteira' => 'nullable|string|max:10',
                'juros_mora' => 'nullable|numeric|min:0|max:100',
                'multa_atraso' => 'nullable|numeric|min:0|max:100',
                'instrucao_1' => 'nullable|string|max:255',
                'instrucao_2' => 'nullable|string|max:255',
            ]);

            $idEmpresa = session('id_empresa');

            // Verificar se o banco pertence à empresa
            $banco = Banco::where('id_empresa', $idEmpresa)
                ->where('id_bancos', $request->id_bancos)
                ->first();

            if (!$banco) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banco não encontrado.',
                ], 404);
            }

            $configExistente = BancoBoletoConfig::where('id_bancos', $request->id_bancos)
                ->where('id_empresa', $idEmpresa)
                ->first();

            $dadosConfig = [
                'id_banco_boleto' => $request->id_banco_boleto,
                'client_id' => $request->client_id,
                'convenio' => $request->convenio,
                'carteira' => $request->carteira,
                'juros_mora' => $request->juros_mora ?? 0,
                'multa_atraso' => $request->multa_atraso ?? 0,
                'instrucao_1' => $request->instrucao_1,
                'instrucao_2' => $request->instrucao_2,
                'ativo' => true,
            ];

            // Campos sensíveis não voltam no GET (por segurança), então preservamos o valor
            // atual quando o usuário edita sem informar novamente.
            $this->preencherCampoSensivel($dadosConfig, 'client_secret', $request->client_secret, $configExistente);
            $this->preencherCampoSensivel($dadosConfig, 'api_key', $request->api_key, $configExistente);
            $this->preencherCampoSensivel($dadosConfig, 'token', $request->token, $configExistente);

            // Se o banco ainda não emite boleto, validar limite/permissão da aba Boletos.
            if (!$banco->gera_boleto) {
                $resultadoLimite = LimiteService::podeMarcarBancoGeraBoleto($idEmpresa);
                if (!$resultadoLimite['pode']) {
                    return response()->json([
                        'success' => false,
                        'message' => $resultadoLimite['mensagem'],
                    ], 422);
                }
            }

            // Buscar ou criar configuração
            $config = BancoBoletoConfig::updateOrCreate(
                [
                    'id_bancos' => $request->id_bancos,
                    'id_empresa' => $idEmpresa,
                ],
                $dadosConfig
            );

            // Atualizar flag gera_boleto no banco
            $banco->update(['gera_boleto' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Configuração salva com sucesso!',
                'config' => $config->load('bancoBoleto'),
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao salvar configuração de boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao salvar configuração: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload de arquivo de certificado ou chave.
     */
    public function uploadArquivo(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'id_bancos' => 'required|integer',
                'tipo' => 'required|in:certificado,chave',
                'arquivo' => 'required|file|max:5120', // 5MB max
            ]);

            $idEmpresa = session('id_empresa');

            // Verificar se o banco pertence à empresa
            $banco = Banco::where('id_empresa', $idEmpresa)
                ->where('id_bancos', $request->id_bancos)
                ->first();

            if (!$banco) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banco não encontrado.',
                ], 404);
            }

            // Verificar extensão do arquivo
            $file = $request->file('arquivo');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($request->tipo === 'certificado' && $extension !== 'crt') {
                return response()->json([
                    'success' => false,
                    'message' => 'O arquivo de certificado deve ter extensão .crt',
                ], 400);
            }

            if ($request->tipo === 'chave' && $extension !== 'key') {
                return response()->json([
                    'success' => false,
                    'message' => 'O arquivo de chave deve ter extensão .key',
                ], 400);
            }

            // Gerar nome único para o arquivo
            $filename = sprintf(
                'boleto_%s_%s_%s.%s',
                $idEmpresa,
                $request->id_bancos,
                uniqid(),
                $extension
            );

            // Salvar arquivo
            $path = 'boletos/certificados';
            Storage::disk('local')->makeDirectory($path);
            $file->storeAs($path, $filename, 'local');

            // Atualizar configuração
            $config = BancoBoletoConfig::where('id_bancos', $request->id_bancos)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if ($config) {
                // Remover arquivo antigo se existir
                $oldFile = $request->tipo === 'certificado' 
                    ? $config->arquivo_certificado 
                    : $config->arquivo_chave;
                
                if ($oldFile && Storage::disk('local')->exists($path . '/' . $oldFile)) {
                    Storage::disk('local')->delete($path . '/' . $oldFile);
                }

                // Atualizar caminho
                $campo = $request->tipo === 'certificado' ? 'arquivo_certificado' : 'arquivo_chave';
                $config->update([$campo => $filename]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Arquivo enviado com sucesso!',
                'filename' => $filename,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao fazer upload de arquivo de boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar arquivo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna a configuração de boleto de um banco.
     */
    public function getConfiguracao(int $idBancos): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $config = BancoBoletoConfig::where('id_bancos', $idBancos)
                ->where('id_empresa', $idEmpresa)
                ->with('bancoBoleto')
                ->first();

            if ($config) {
                $config = $this->vincularArquivosExistentes($config, $idEmpresa, $idBancos);
            }

            $configResponse = null;
            if ($config) {
                $configResponse = $config->toArray();

                // Campos sensíveis são ocultos no model, então expomos aqui para preencher o modal de edição.
                $clientSecret = $config->getRawOriginal('client_secret');
                $apiKey = $config->getRawOriginal('api_key');
                $token = $config->getRawOriginal('token');
                $ehCora = $this->isBancoCora($config);

                $configResponse['client_secret'] = $clientSecret;
                $configResponse['api_key'] = $apiKey;
                $configResponse['token'] = $ehCora ? null : $token;
                $configResponse['tem_client_secret'] = !empty($clientSecret);
                $configResponse['tem_api_key'] = !empty($apiKey);
                $configResponse['tem_token'] = !empty($token);
                $configResponse['token_modo'] = $this->obterModoTokenConfig($token);

                if ($ehCora) {
                    $configResponse['cora_authorize_url'] = url('/financeiro/boletos/cora/authorize/' . $idBancos);
                    $configResponse['cora_redirect_uri'] = $this->obterCoraRedirectUri(request());
                }
            }

            return response()->json([
                'success' => true,
                'config' => $configResponse,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar configuração de boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar configuração.',
            ], 500);
        }
    }

    /**
     * Inicia o fluxo Authorization Code da Cora.
     */
    public function coraAutorizar(Request $request, int $idBancos)
    {
        try {
            $idEmpresa = session('id_empresa');

            $config = BancoBoletoConfig::where('id_bancos', $idBancos)
                ->where('id_empresa', $idEmpresa)
                ->with('bancoBoleto')
                ->first();

            if (!$config || !$this->isBancoCora($config)) {
                return redirect('/financeiro/bancos')->with('error', 'Configuração de boleto da Cora não encontrada para este banco.');
            }

            if (empty($config->client_id) || empty($config->getRawOriginal('client_secret'))) {
                return redirect('/financeiro/bancos')->with('error', 'Configure Client ID e Client Secret da Cora antes de autorizar.');
            }

            $redirectUri = $this->obterCoraRedirectUri($request);

            $state = (string) Str::uuid();
            Cache::put('cora_oauth_state_' . $state, [
                'id_empresa' => (int) $idEmpresa,
                'id_bancos' => (int) $idBancos,
                'redirect_uri' => $redirectUri,
                'criado_em' => now()->toIso8601String(),
            ], now()->addMinutes(10));

            $service = new BancoCoraService($config);
            $url = $service->gerarUrlAutorizacao($state, $redirectUri);

            return redirect()->away($url);
        } catch (Exception $e) {
            Log::error('Erro ao iniciar autorização Cora: ' . $e->getMessage());

            return redirect('/financeiro/bancos')->with('error', 'Erro ao iniciar autorização da Cora: ' . $e->getMessage());
        }
    }

    /**
     * Callback do Authorization Code da Cora.
     */
    public function coraCallback(Request $request)
    {
        try {
            $oauthError = trim((string) $request->query('error', ''));
            if ($oauthError !== '') {
                $descricao = trim((string) $request->query('error_description', ''));
                throw new Exception('Autorização Cora não concluída: ' . $oauthError . ($descricao ? ' - ' . $descricao : ''));
            }

            $code = trim((string) $request->query('code', ''));
            $state = trim((string) $request->query('state', ''));

            if ($code === '' || $state === '') {
                throw new Exception('Callback da Cora inválido: parâmetros code/state ausentes.');
            }

            $stateData = Cache::pull('cora_oauth_state_' . $state);
            if (!is_array($stateData) || empty($stateData['id_empresa']) || empty($stateData['id_bancos'])) {
                throw new Exception('State da autorização Cora inválido ou expirado. Tente novamente.');
            }

            $idEmpresaSessao = (int) session('id_empresa');
            if ($idEmpresaSessao > 0 && (int) $stateData['id_empresa'] !== $idEmpresaSessao) {
                throw new Exception('State da autorização Cora não corresponde à empresa da sessão atual.');
            }

            $config = BancoBoletoConfig::where('id_bancos', (int) $stateData['id_bancos'])
                ->where('id_empresa', (int) $stateData['id_empresa'])
                ->with('bancoBoleto')
                ->first();

            if (!$config || !$this->isBancoCora($config)) {
                throw new Exception('Configuração de boleto da Cora não encontrada para concluir autorização.');
            }

            $redirectUri = trim((string) ($stateData['redirect_uri'] ?? ''));
            if ($redirectUri === '') {
                $redirectUri = $this->obterCoraRedirectUri($request);
            }

            $service = new BancoCoraService($config);
            $service->trocarCodePorTokenAuthorizationCode($code, $redirectUri);

            return redirect('/financeiro/bancos')->with('success', 'Conta Cora autorizada com sucesso para emissão de boletos.');
        } catch (Exception $e) {
            Log::error('Erro no callback de autorização da Cora: ' . $e->getMessage());

            return redirect('/financeiro/bancos')->with('error', 'Erro ao concluir autorização da Cora: ' . $e->getMessage());
        }
    }

    /**
     * Preserva valor sensível já salvo quando o campo chega vazio na edição.
     */
    private function preencherCampoSensivel(array &$dadosConfig, string $campo, $valorRequest, ?BancoBoletoConfig $configExistente): void
    {
        $valorNormalizado = is_string($valorRequest) ? trim($valorRequest) : $valorRequest;

        if ($valorNormalizado !== null && $valorNormalizado !== '') {
            $dadosConfig[$campo] = $valorNormalizado;
            return;
        }

        if ($configExistente && !empty($configExistente->getRawOriginal($campo))) {
            $dadosConfig[$campo] = $configExistente->getRawOriginal($campo);
        }
    }

    /**
     * Tenta vincular arquivos já presentes em disco quando a coluna está vazia.
     */
    private function vincularArquivosExistentes(BancoBoletoConfig $config, int $idEmpresa, int $idBancos): BancoBoletoConfig
    {
        $atualizacoes = [];

        if (empty($config->arquivo_certificado)) {
            $arquivoCertificado = $this->buscarArquivoMaisRecente($idEmpresa, $idBancos, 'crt');
            if ($arquivoCertificado) {
                $atualizacoes['arquivo_certificado'] = $arquivoCertificado;
            }
        }

        if (empty($config->arquivo_chave)) {
            $arquivoChave = $this->buscarArquivoMaisRecente($idEmpresa, $idBancos, 'key');
            if ($arquivoChave) {
                $atualizacoes['arquivo_chave'] = $arquivoChave;
            }
        }

        if (!empty($atualizacoes)) {
            $config->update($atualizacoes);
            $config->refresh();
            $config->load('bancoBoleto');
        }

        return $config;
    }

    /**
     * Busca o arquivo mais recente para empresa/banco/extensão no diretório de certificados.
     */
    private function buscarArquivoMaisRecente(int $idEmpresa, int $idBancos, string $extensao): ?string
    {
        $basePath = 'boletos/certificados';
        if (!Storage::disk('local')->exists($basePath)) {
            return null;
        }

        $prefixo = sprintf('boleto_%d_%d_', $idEmpresa, $idBancos);
        $arquivos = Storage::disk('local')->files($basePath);

        $candidatos = collect($arquivos)
            ->filter(function (string $arquivo) use ($prefixo, $extensao) {
                $nomeArquivo = basename($arquivo);
                return Str::startsWith($nomeArquivo, $prefixo) && Str::endsWith(Str::lower($nomeArquivo), '.' . Str::lower($extensao));
            })
            ->map(function (string $arquivo) {
                return [
                    'nome' => basename($arquivo),
                    'modificado_em' => Storage::disk('local')->lastModified($arquivo),
                ];
            })
            ->sortByDesc('modificado_em')
            ->values();

        return $candidatos->isNotEmpty() ? $candidatos->first()['nome'] : null;
    }

    /**
     * Verifica se a integração configurada é da Cora.
     */
    private function isBancoCora(BancoBoletoConfig $config): bool
    {
        $nome = Str::lower((string) optional($config->bancoBoleto)->nome);
        $codigo = Str::lower((string) optional($config->bancoBoleto)->codigo_banco);

        return Str::contains($nome, 'cora') || Str::contains($codigo, 'cora');
    }

    /**
     * Extrai modo do token salvo (authorization_code/client_credentials/manual etc).
     */
    private function obterModoTokenConfig(?string $token): ?string
    {
        if (!$token) {
            return null;
        }

        $decoded = json_decode($token, true);
        if (is_array($decoded) && !empty($decoded['modo'])) {
            return (string) $decoded['modo'];
        }

        return 'manual';
    }

    /**
     * URI de callback da Cora conforme configuração.
     */
    private function obterCoraRedirectUri(?Request $request = null): string
    {
        $redirectUri = trim((string) config('services.cora.redirect_uri', ''));
        if ($redirectUri !== '') {
            return $redirectUri;
        }

        if ($request) {
            $forwardedProto = (string) $request->headers->get('x-forwarded-proto', '');
            $forwardedProto = trim(explode(',', $forwardedProto)[0] ?? '');

            $scheme = in_array($forwardedProto, ['http', 'https'], true)
                ? $forwardedProto
                : $request->getScheme();

            return $scheme . '://' . $request->getHttpHost() . '/financeiro/boletos/cora/callback';
        }

        return url('/financeiro/boletos/cora/callback');
    }

    /**
     * Desativa a geração de boleto de um banco.
     */
    public function desativar(int $idBancos): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $banco = Banco::where('id_empresa', $idEmpresa)
                ->where('id_bancos', $idBancos)
                ->first();

            if (!$banco) {
                return response()->json([
                    'success' => false,
                    'message' => 'Banco não encontrado.',
                ], 404);
            }

            $banco->update(['gera_boleto' => false]);

            $config = BancoBoletoConfig::where('id_bancos', $idBancos)
                ->where('id_empresa', $idEmpresa)
                ->first();

            if ($config) {
                $config->update(['ativo' => false]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Geração de boletos desativada para este banco.',
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao desativar boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao desativar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retorna o histórico de um boleto.
     */
    public function historico(int $id): JsonResponse
    {
        try {
            $idEmpresa = session('id_empresa');

            $boleto = Boleto::where('id_empresa', $idEmpresa)
                ->where('id_boleto', $id)
                ->first();

            if (!$boleto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Boleto não encontrado.',
                ], 404);
            }

            $historicos = BoletoHistorico::where('id_boleto', $id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($h) {
                    return [
                        'id' => $h->id_historico,
                        'tipo' => $h->tipo,
                        'conteudo' => $h->conteudo,
                        'conteudo_decodificado' => $h->conteudo_decodificado,
                        'created_at' => $h->created_at ? $h->created_at->format('d/m/Y H:i:s') : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'historicos' => $historicos,
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao buscar histórico do boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar histórico.',
            ], 500);
        }
    }

    /**
     * Altera o vencimento de um boleto.
     * Cancela o boleto anterior e gera um novo com a nova data.
     */
    public function alterarVencimento(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'data_vencimento' => 'required|date|after:today',
                'valor' => 'nullable|numeric|min:0.01',
            ]);

            $idEmpresa = session('id_empresa');

            $boleto = Boleto::where('id_empresa', $idEmpresa)
                ->where('id_boleto', $id)
                ->with(['contaAReceber', 'banco'])
                ->first();

            if (!$boleto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Boleto não encontrado.',
                ], 404);
            }

            if ($boleto->status === Boleto::STATUS_PAGO) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível alterar vencimento de boleto já pago.',
                ], 400);
            }

            if ($boleto->status === Boleto::STATUS_CANCELADO) {
                return response()->json([
                    'success' => false,
                    'message' => 'Não é possível alterar vencimento de boleto cancelado.',
                ], 400);
            }

            $novaDataVencimento = $request->data_vencimento;
            $novoValor = $request->valor ?? $boleto->valor_nominal;

            // Cancelar boleto antigo e gerar novo
            $novoBoleto = $this->boletoService->alterarVencimentoBoleto(
                $boleto,
                $novaDataVencimento,
                (float) $novoValor
            );

            ActionLogger::log($boleto->fresh(), 'vencimento_alterado');

            if ((int) ($novoBoleto->id_boleto ?? 0) > 0) {
                ActionLogger::log($novoBoleto->fresh(), 'vencimento_alterado');
            }

            // Atualizar data de vencimento na conta a receber
            if ($boleto->contaAReceber) {
                $boleto->contaAReceber->update([
                    'data_vencimento' => $novaDataVencimento,
                    'valor_total' => $novoValor,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Vencimento alterado com sucesso! Novo boleto gerado.',
                'boleto' => $novoBoleto->load(['banco', 'bancoBoleto', 'contaAReceber']),
            ]);
        } catch (Exception $e) {
            Log::error('Erro ao alterar vencimento do boleto: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erro ao alterar vencimento: ' . $e->getMessage(),
            ], 500);
        }
    }
}
