<?php

namespace App\Services\Billing;

use App\Domain\Auth\Models\Empresa;
use App\Helpers\PdfAssetHelper;
use App\Models\AssinaturaPlanoPagamento;
use App\Models\ClientContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ContractPdfService
{
    private const RECIBOS_PATH = 'recibos/adesao';
    private const LOGO_PRESTADORA_URL = 'https://gestornow.com/assets/logos-empresa/logo_empresa_1_1772650952.jpeg';

    /**
     * Gera o PDF do recibo de adesão
     */
    public function gerarReciboAdesao(ClientContract $contrato): string
    {
        try {
            // Garantir que o diretório existe
            $this->garantirDiretorioRecibos();

            // Montar dados para a view
            $dados = $this->montarDadosRecibo($contrato);

            // Gerar PDF usando dompdf
            $pdf = Pdf::loadView('billing.recibos.adesao', $dados);

            // Configurações do PDF
            $pdf->setPaper('A4', 'portrait');
            $pdf->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);

            // Definir nome do arquivo
            $nomeArquivo = $this->gerarNomeArquivoRecibo($contrato);
            $caminhoRelativo = self::RECIBOS_PATH . '/' . $nomeArquivo;

            // Salvar PDF
            Storage::put($caminhoRelativo, $pdf->output());

            Log::info('Recibo de adesão gerado com sucesso', [
                'contrato_id' => $contrato->id,
                'arquivo' => $caminhoRelativo,
            ]);

            return $caminhoRelativo;

        } catch (Exception $e) {
            Log::error('Erro ao gerar recibo de adesão', [
                'contrato_id' => $contrato->id,
                'erro' => $e->getMessage(),
            ]);

            try {
                $caminhoFallback = $this->gerarReciboAdesaoSimplificado($contrato);

                Log::warning('Recibo completo falhou; recibo simplificado gerado com sucesso.', [
                    'contrato_id' => $contrato->id,
                    'arquivo' => $caminhoFallback,
                ]);

                return $caminhoFallback;
            } catch (\Throwable $fallbackException) {
                Log::error('Falha ao gerar recibo simplificado de contingencia.', [
                    'contrato_id' => $contrato->id,
                    'erro_original' => $e->getMessage(),
                    'erro_fallback' => $fallbackException->getMessage(),
                ]);

                throw new Exception('Falha ao gerar recibo: ' . $e->getMessage());
            }
        }
    }

    private function gerarReciboAdesaoSimplificado(ClientContract $contrato): string
    {
        $this->garantirDiretorioRecibos();
        $contrato->loadMissing(['plano', 'planoContratado']);

        $valorAdesaoPago = $this->resolverValorAdesaoPagoCliente($contrato);
        $valorAdesao = !is_null($valorAdesaoPago)
            ? $valorAdesaoPago
            : (float) ($contrato->valor_adesao ?? 0);

        $dados = [
            'contrato' => $contrato,
            'numero_recibo' => $this->gerarNumeroRecibo($contrato),
            'data_emissao' => now()->format('d/m/Y H:i:s'),
            'nome_plano' => $contrato->planoContratado?->nome ?? $contrato->plano?->nome ?? 'Plano Contratado',
            'valor_adesao_formatado' => $this->formatarValorMonetario($valorAdesao),
            'valor_mensalidade_formatado' => $contrato->valor_mensalidade_formatado,
            'logo_gestornow' => $this->resolverLogoPrestadoraParaPdf((int) $contrato->id),
        ];

        $pdf = Pdf::loadView('billing.recibos.adesao-simples', $dados);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $nomeArquivo = 'recibo-simples-' . $contrato->id . '-' . now()->format('Ymd-His') . '.pdf';
        $caminhoRelativo = self::RECIBOS_PATH . '/' . $nomeArquivo;

        Storage::put($caminhoRelativo, $pdf->output());

        return $caminhoRelativo;
    }

    /**
     * Gera PDF do contrato completo
     */
    public function gerarContratoCompleto(ClientContract $contrato): string
    {
        try {
            $this->garantirDiretorioContratos();

            $dados = [
                'contrato' => $contrato,
                'limites_formatados' => $contrato->getLimitesFormatados(),
                'data_geracao' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = Pdf::loadView('billing.contratos.completo', $dados);
            $pdf->setPaper('A4', 'portrait');

            $nomeArquivo = 'contrato-' . $contrato->id . '-' . now()->format('Ymd-His') . '.pdf';
            $caminhoRelativo = 'contratos/' . $nomeArquivo;

            Storage::put($caminhoRelativo, $pdf->output());

            return $caminhoRelativo;

        } catch (Exception $e) {
            Log::error('Erro ao gerar contrato PDF', [
                'contrato_id' => $contrato->id,
                'erro' => $e->getMessage(),
            ]);

            throw new Exception('Falha ao gerar contrato: ' . $e->getMessage());
        }
    }

    /**
     * Monta os dados para o recibo de adesão
     */
    private function montarDadosRecibo(ClientContract $contrato): array
    {
        $contrato->loadMissing(['empresa', 'plano', 'planoContratado']);

        $nomePlano = $contrato->planoContratado?->nome ?? $contrato->plano?->nome ?? 'Plano Contratado';
        $dadosPlanoCatalogo = $this->obterDadosCatalogoPlano($nomePlano);

        // Regra de negócio: a taxa de adesão do recibo deve refletir o valor efetivamente pago.
        $valorAdesaoPago = $this->resolverValorAdesaoPagoCliente($contrato);
        $valorAdesao = !is_null($valorAdesaoPago)
            ? $valorAdesaoPago
            : (float) ($contrato->valor_adesao ?? 0);
        $valorMensalidade = (float) ($contrato->valor_mensalidade ?? 0);

        if ($dadosPlanoCatalogo) {
            $nomePlano = $dadosPlanoCatalogo['nome'];
            $recursosPlano = $dadosPlanoCatalogo['recursos'];

            if ($valorMensalidade <= 0) {
                $valorMensalidade = (float) ($dadosPlanoCatalogo['valor_mensalidade'] ?? 0);
            }

            if (is_null($valorAdesaoPago) && $valorAdesao <= 0) {
                $valorAdesao = (float) ($dadosPlanoCatalogo['valor_adesao'] ?? 0);
            }
        } else {
            $recursosPlano = $this->obterRecursosFixosPlano($nomePlano);

            if (empty($recursosPlano)) {
                $recursosPlano = $this->normalizarLimitesFallback($contrato->getLimitesFormatados());
            }
        }

        $valorAdesaoFormatado = $this->formatarValorMonetario($valorAdesao);
        $valorMensalidadeFormatado = $this->formatarValorMonetario($valorMensalidade);

        $logoGestorNow = $this->resolverLogoPrestadoraParaPdf($contrato->id);

        $assinatura = $this->normalizarAssinaturaRecibo($contrato->assinatura_base64, $contrato->assinado_por_nome);
        $clausulasAceitas = $this->normalizarClausulasAceitasRecibo((string) ($contrato->corpo_contrato ?? ''));

        return [
            // Dados do cliente
            'cliente' => [
                'razao_social' => $contrato->cliente_razao_social,
                'cnpj_cpf' => $contrato->cnpj_cpf_formatado,
                'email' => $contrato->cliente_email,
                'endereco' => $contrato->cliente_endereco,
            ],
            
            // Dados do plano
            'plano' => [
                'nome' => $nomePlano,
            ],
            
            // Valores
            'valor_adesao' => $valorAdesao,
            'valor_adesao_formatado' => $valorAdesaoFormatado,
            'valor_mensalidade' => $valorMensalidade,
            'valor_mensalidade_formatado' => $valorMensalidadeFormatado,
            
            // Limites
            'limites' => $contrato->getLimitesFormatados(),
            'limites_raw' => $contrato->limites_contratados,
            'recursos_plano' => $recursosPlano,

            // Marca e assinatura
            'logo_gestornow' => $logoGestorNow,
            'assinatura' => $assinatura,

            // Clausulas aceitas
            'clausulas_aceitas' => $clausulasAceitas,
            'titulo_contrato' => (string) ($contrato->titulo_contrato ?? 'Contrato de Licenciamento de Software SaaS'),
            'versao_contrato' => (string) ($contrato->versao_contrato ?? '1.0'),
            
            // Metadados
            'contrato' => $contrato,
            'numero_recibo' => $this->gerarNumeroRecibo($contrato),
            'data_emissao' => now()->format('d/m/Y'),
            'data_aceite' => $contrato->aceito_em_formatado,
            
            // Dados de validade jurídica
            'hash_documento' => $contrato->hash_documento,
            'ip_aceite' => $contrato->ip_aceite,
            'assinado_por' => $contrato->assinado_por_nome,
            
            // Valor por extenso
            'valor_extenso' => $this->valorPorExtenso($valorAdesao),
            
            // Identificação de troca de plano
            'eh_troca_plano' => $this->identificarTrocaPlano($contrato),
            'tipo_troca' => $this->obterTipoTroca($contrato),
            'valor_mensal_anterior' => $this->obterValorAnterior($contrato),
        ];
    }

    private function resolverValorAdesaoPagoCliente(ClientContract $contrato): ?float
    {
        $consultaBase = AssinaturaPlanoPagamento::query()
            ->where('id_empresa', (int) $contrato->id_empresa)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_ADESAO)
            ->whereNotIn('status', [
                AssinaturaPlanoPagamento::STATUS_CANCELADO,
                AssinaturaPlanoPagamento::STATUS_FALHOU,
            ])
            ->whereNotNull('valor');

        $idPlanoContrato = (int) ($contrato->id_plano ?? 0);

        // Prioriza pagamentos do mesmo plano do contrato (snapshot histórico do recibo).
        if ($idPlanoContrato > 0) {
            $consultaComPlano = (clone $consultaBase)->where('id_plano', $idPlanoContrato);
            if ($consultaComPlano->exists()) {
                $consultaBase = $consultaComPlano;
            }
        }

        $pagamentos = $consultaBase
            ->orderByDesc('created_at')
            ->limit(120)
            ->get();

        if ($pagamentos->isEmpty()) {
            return null;
        }

        $referenciaTimestamp = $contrato->aceito_em?->getTimestamp() ?? $contrato->created_at?->getTimestamp();
        $valorAdesaoContrato = round((float) ($contrato->valor_adesao ?? 0), 2);
        $proximoContratoTimestamp = $this->obterTimestampProximoContratoEmpresa($contrato);

        $pagamentoSelecionado = $pagamentos
            ->sort(function (AssinaturaPlanoPagamento $a, AssinaturaPlanoPagamento $b) use ($idPlanoContrato, $valorAdesaoContrato, $referenciaTimestamp, $proximoContratoTimestamp) {
                $pesoA = $this->montarPesoPagamentoAdesao($a, $idPlanoContrato, $valorAdesaoContrato, $referenciaTimestamp, $proximoContratoTimestamp);
                $pesoB = $this->montarPesoPagamentoAdesao($b, $idPlanoContrato, $valorAdesaoContrato, $referenciaTimestamp, $proximoContratoTimestamp);

                return $pesoA <=> $pesoB;
            })
            ->first();

        if (!$pagamentoSelecionado) {
            return null;
        }

        return round((float) $pagamentoSelecionado->valor, 2);
    }

    private function montarPesoPagamentoAdesao(
        AssinaturaPlanoPagamento $pagamento,
        int $idPlanoContrato,
        float $valorAdesaoContrato,
        ?int $referenciaTimestamp,
        ?int $proximoContratoTimestamp
    ): array {
        $status = strtolower((string) $pagamento->status);
        $pesoStatus = $status === AssinaturaPlanoPagamento::STATUS_PAGO ? 0 : 1;

        // Nao usa id_plano_contratado como chave principal porque ele pode ser alterado em upgrades.
        $pesoPlano = ($idPlanoContrato > 0 && (int) $pagamento->id_plano === $idPlanoContrato) ? 0 : 1;

        $timestampPagamento = $pagamento->data_pagamento?->getTimestamp() ?? $pagamento->created_at?->getTimestamp();

        // Penaliza pagamentos que aconteceram apos o aceite do proximo contrato da empresa.
        $pesoJanelaContrato = (!is_null($proximoContratoTimestamp) && !is_null($timestampPagamento) && $timestampPagamento >= $proximoContratoTimestamp)
            ? 1
            : 0;

        $pesoValor = abs(round((float) $pagamento->valor, 2) - $valorAdesaoContrato);

        $pesoDistanciaTempo = (!is_null($referenciaTimestamp) && !is_null($timestampPagamento))
            ? abs($timestampPagamento - $referenciaTimestamp)
            : PHP_INT_MAX;

        $pesoRecencia = !is_null($timestampPagamento) ? -$timestampPagamento : PHP_INT_MAX;

        return [$pesoStatus, $pesoPlano, $pesoJanelaContrato, $pesoValor, $pesoDistanciaTempo, $pesoRecencia];
    }

    private function obterTimestampProximoContratoEmpresa(ClientContract $contrato): ?int
    {
        if (!$contrato->aceito_em) {
            return null;
        }

        $proximoAceite = ClientContract::query()
            ->where('id_empresa', (int) $contrato->id_empresa)
            ->whereNotNull('aceito_em')
            ->where('aceito_em', '>', $contrato->aceito_em)
            ->orderBy('aceito_em', 'asc')
            ->value('aceito_em');

        if (empty($proximoAceite)) {
            return null;
        }

        return strtotime((string) $proximoAceite) ?: null;
    }

    private function normalizarClausulasAceitasRecibo(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        $texto = preg_replace('/\r\n?/', "\n", $texto) ?? $texto;

        // Remove bloco redundante de resumo do plano, já exibido em seção própria do recibo.
        $texto = preg_replace(
            '/DADOS\s+DO\s+PLANO\s+CONTRATADO.*?(?=DATA\s+E\s+HORA\s+DO\s+ACEITE|$)/isu',
            '',
            $texto
        ) ?? $texto;

        $texto = preg_replace('/\n{3,}/', "\n\n", $texto) ?? $texto;

        return trim($texto);
    }

    private function resolverLogoPrestadoraParaPdf(int $contratoId): ?string
    {
        $logoPreferencialDataUri = $this->converterImagemUrlParaDataUri(self::LOGO_PRESTADORA_URL);
        if (!empty($logoPreferencialDataUri)) {
            return $logoPreferencialDataUri;
        }

        $empresaPrestadora = Empresa::withTrashed()->where('id_empresa', 1)->first();

        if (!$empresaPrestadora) {
            Log::warning('Nao foi possivel localizar a empresa prestadora (id_empresa=1) para logo do recibo.', [
                'contrato_id' => $contratoId,
            ]);

            return $this->fallbackLogoSistema();
        }

        $logoHelper = PdfAssetHelper::resolveCompanyConfigImage(
            $empresaPrestadora,
            ['logo_url', 'logo', 'logo_empresa', 'logo_principal', 'logoUrl', 'logo_path'],
            true
        );

        if (!empty($logoHelper)) {
            return $logoHelper;
        }

        $configuracoes = $empresaPrestadora->configuracoes ?? [];
        if (is_string($configuracoes)) {
            $configuracoes = json_decode($configuracoes, true);
        }
        $configuracoes = is_array($configuracoes) ? $configuracoes : [];

        $logoUrl = trim((string) (
            $configuracoes['logo_url']
            ?? $configuracoes['logoUrl']
            ?? $configuracoes['logo']
            ?? $empresaPrestadora->logo_url
            ?? ''
        ));

        if ($logoUrl === '') {
            Log::warning('Logo da prestadora nao configurada para recibo.', [
                'contrato_id' => $contratoId,
                'id_empresa_prestadora' => 1,
            ]);

            return $this->fallbackLogoSistema();
        }

        $logoUrl = str_replace(['https//', 'http//'], ['https://', 'http://'], $logoUrl);

        $arquivoLocal = $this->resolverArquivoLocalParaPdf($logoUrl, [
            'uploads/logos/imagens',
            'assets/logos-empresa',
            'storage/logos-empresa',
            'uploads/logos',
        ]);

        if ($arquivoLocal !== null) {
            return $arquivoLocal;
        }

        if (!str_starts_with($logoUrl, 'http://') && !str_starts_with($logoUrl, 'https://')) {
            $logoUrl = rtrim($this->getApiFilesBaseUrl(), '/') . '/' . ltrim($logoUrl, '/');
        }

        try {
            $response = Http::timeout(20)->get($logoUrl);
            if ($response->successful()) {
                $mime = (string) ($response->header('Content-Type') ?: 'image/png');
                return 'data:' . $mime . ';base64,' . base64_encode((string) $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao converter logo da prestadora para base64 no recibo.', [
                'contrato_id' => $contratoId,
                'url_logo' => $logoUrl,
                'erro' => $e->getMessage(),
            ]);
        }

        return $this->fallbackLogoSistema() ?: $logoUrl;
    }

    private function converterImagemUrlParaDataUri(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        try {
            $response = Http::withOptions(['verify' => false])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'image/*,*/*;q=0.8',
                ])
                ->timeout(20)
                ->get($url);

            if ($response->successful()) {
                $conteudo = (string) $response->body();
                if ($conteudo !== '') {
                    $mime = (string) ($response->header('Content-Type') ?: 'image/jpeg');
                    if (!str_starts_with(strtolower($mime), 'image/')) {
                        $mime = 'image/jpeg';
                    }

                    return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao baixar logo preferencial para PDF via Http.', [
                'url' => $url,
                'erro' => $e->getMessage(),
            ]);
        }

        try {
            $contexto = stream_context_create([
                'http' => ['timeout' => 20],
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
            ]);

            $conteudo = @file_get_contents($url, false, $contexto);
            if ($conteudo !== false && $conteudo !== '') {
                $mime = 'image/jpeg';
                $imageInfo = @getimagesizefromstring($conteudo);
                if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
                    $mime = (string) $imageInfo['mime'];
                }

                return 'data:' . $mime . ';base64,' . base64_encode($conteudo);
            }
        } catch (\Throwable $e) {
            Log::warning('Falha ao baixar logo preferencial para PDF via file_get_contents.', [
                'url' => $url,
                'erro' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function resolverArquivoLocalParaPdf(string $url, array $pathsConhecidos = []): ?string
    {
        $caminhosTentar = [];

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $caminhosTentar[] = public_path(ltrim($path, '/'));
            $caminhosTentar[] = storage_path('app/public/' . ltrim($path, '/'));
        }

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $caminhosTentar[] = public_path(ltrim($url, '/'));
            $caminhosTentar[] = storage_path('app/public/' . ltrim($url, '/'));
        }

        $basename = basename((string) ($path ?: $url));
        if ($basename !== '') {
            foreach ($pathsConhecidos as $pathConhecido) {
                $baseConhecida = trim($pathConhecido, '/');
                $caminhosTentar[] = public_path($baseConhecida . '/' . $basename);
                $caminhosTentar[] = storage_path('app/public/' . $baseConhecida . '/' . $basename);
            }
        }

        $caminhosTentar = array_unique($caminhosTentar);
        foreach ($caminhosTentar as $caminho) {
            if (is_string($caminho) && $caminho !== '' && file_exists($caminho)) {
                return $caminho;
            }
        }

        return null;
    }

    private function getApiFilesBaseUrl(): string
    {
        $baseUrl = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com')), '/');
        return str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);
    }

    private function fallbackLogoSistema(): ?string
    {
        $logoPadrao = public_path('assets/img/gestor_now_transparent.png');
        return file_exists($logoPadrao) ? $logoPadrao : null;
    }

    private function normalizarAssinaturaRecibo(?string $assinaturaRaw, ?string $assinadoPorNome): array
    {
        $assinaturaRaw = trim((string) $assinaturaRaw);

        if ($assinaturaRaw !== '' && str_starts_with($assinaturaRaw, 'data:image/')) {
            return [
                'tipo' => 'imagem',
                'valor' => $assinaturaRaw,
            ];
        }

        if ($assinaturaRaw !== '' && str_starts_with($assinaturaRaw, 'texto:')) {
            return [
                'tipo' => 'texto',
                'valor' => trim(substr($assinaturaRaw, 6)),
            ];
        }

        if ($assinaturaRaw !== '') {
            $textoDecodificado = base64_decode($assinaturaRaw, true);

            if ($textoDecodificado !== false && trim($textoDecodificado) !== '') {
                return [
                    'tipo' => 'texto',
                    'valor' => trim($textoDecodificado),
                ];
            }

            return [
                'tipo' => 'texto',
                'valor' => $assinaturaRaw,
            ];
        }

        return [
            'tipo' => 'texto',
            'valor' => trim((string) $assinadoPorNome),
        ];
    }

    private function obterRecursosFixosPlano(string $nomePlano): array
    {
        return $this->obterDadosCatalogoPlano($nomePlano)['recursos'] ?? [];
    }

    private function obterDadosCatalogoPlano(string $nomePlano): ?array
    {
        $chavePlano = $this->normalizarChavePlanoRecibo($nomePlano);

        $catalogo = [
            'start' => [
                'nome' => 'Plano Start',
                'valor_mensalidade' => 99.90,
                'valor_adesao' => 389.90,
                'recursos' => [
                    'Clientes - Limite: 500',
                    'Produtos - Limite: 500',
                    'Locações Completas',
                    '1 Modelo de contrato',
                    'Financeiro Completo',
                    'Sem emissão de Boleto',
                    'Usuários - Limite: 1',
                ],
            ],
            'pro' => [
                'nome' => 'Plano Pro',
                'valor_mensalidade' => 159.90,
                'valor_adesao' => 629.90,
                'recursos' => [
                    'Clientes - Limite: 1.500',
                    'Produtos - Limite: 1.500',
                    'Locações Completas',
                    'Modelos de contratos ilimitados',
                    'Financeiro Completo',
                    '1 banco pra boleto',
                    'Usuários - Limite: 3',
                ],
            ],
            'plus' => [
                'nome' => 'Plano Plus',
                'valor_mensalidade' => 199.90,
                'valor_adesao' => 779.90,
                'recursos' => [
                    'Clientes - Limite: 3.000',
                    'Produtos - Limite: 3.000',
                    'Locações Completas',
                    'Modelos de contratos ilimitados',
                    'Financeiro Completo',
                    'Bancos pra Boletos Ilimitados',
                    'Usuários - Limite: 10',
                ],
            ],
            'premium' => [
                'nome' => 'Plano Premium',
                'valor_mensalidade' => 259.90,
                'valor_adesao' => 1019.90,
                'recursos' => [
                    'Clientes - Ilimitado',
                    'Produtos - Ilimitado',
                    'Locações Completas',
                    'Modelos de contratos ilimitados',
                    'Financeiro Completo',
                    'Bancos pra Boletos Ilimitados',
                    'Usuários - Ilimitado',
                ],
            ],
        ];

        return $catalogo[$chavePlano] ?? null;
    }

    private function normalizarChavePlanoRecibo(string $nomePlano): string
    {
        $nomePlanoNormalizado = strtolower(trim($nomePlano));
        $nomePlanoNormalizado = preg_replace('/^plano\s+/i', '', $nomePlanoNormalizado);

        $aliasPlanos = [
            'gestor' => 'plus',
            'basic' => 'start',
            'basico' => 'start',
            'básico' => 'start',
            'starter' => 'start',
            'profissional' => 'pro',
            'professional' => 'pro',
            'avançado' => 'premium',
            'avancado' => 'premium',
            'enterprise' => 'premium',
        ];

        return $aliasPlanos[$nomePlanoNormalizado] ?? $nomePlanoNormalizado;
    }

    private function formatarValorMonetario(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function normalizarLimitesFallback(array $limites): array
    {
        if (empty($limites)) {
            return ['Sem módulos configurados para este plano.'];
        }

        $resultado = [];

        foreach ($limites as $chave => $valor) {
            if (is_array($valor)) {
                continue;
            }

            $chaveFormatada = ucwords(str_replace('_', ' ', (string) $chave));
            $valorFormatado = trim((string) $valor);

            if ($valorFormatado === '') {
                continue;
            }

            $resultado[] = $chaveFormatada . ' - ' . $valorFormatado;
        }

        return !empty($resultado) ? $resultado : ['Sem módulos configurados para este plano.'];
    }

    /**
     * Gera número único do recibo
     */
    private function gerarNumeroRecibo(ClientContract $contrato): string
    {
        $ano = $contrato->aceito_em->format('Y');
        $sequencial = str_pad($contrato->id, 6, '0', STR_PAD_LEFT);
        
        return "REC-{$ano}-{$sequencial}";
    }

    /**
     * Gera nome do arquivo do recibo
     */
    private function gerarNomeArquivoRecibo(ClientContract $contrato): string
    {
        $documento = preg_replace('/\D/', '', $contrato->cliente_cnpj_cpf);
        $data = $contrato->aceito_em->format('Ymd');
        
        return "recibo-{$documento}-{$data}-{$contrato->id}.pdf";
    }

    /**
     * Garante que o diretório de recibos existe
     */
    private function garantirDiretorioRecibos(): void
    {
        if (!Storage::exists(self::RECIBOS_PATH)) {
            Storage::makeDirectory(self::RECIBOS_PATH);
        }
    }

    /**
     * Garante que o diretório de contratos existe
     */
    private function garantirDiretorioContratos(): void
    {
        if (!Storage::exists('contratos')) {
            Storage::makeDirectory('contratos');
        }
    }

    /**
     * Converte valor monetário para extenso
     */
    private function valorPorExtenso(float $valor): string
    {
        if ($valor == 0) {
            return 'zero reais';
        }

        $singular = ['centavo', 'real', 'mil', 'milhão', 'bilhão', 'trilhão'];
        $plural = ['centavos', 'reais', 'mil', 'milhões', 'bilhões', 'trilhões'];

        $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
        $dezenas = ['', 'dez', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $dezenasEspeciais = ['dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
        $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        $valor = number_format($valor, 2, '.', '');
        $inteiro = (int) $valor;
        $centavos = (int) (($valor - $inteiro) * 100);

        $resultado = '';

        // Processar parte inteira
        if ($inteiro > 0) {
            if ($inteiro == 100) {
                $resultado = 'cem';
            } else {
                $resultado = $this->numeroPorExtenso($inteiro, $unidades, $dezenas, $dezenasEspeciais, $centenas);
            }
            $resultado .= ' ' . ($inteiro == 1 ? 'real' : 'reais');
        }

        // Processar centavos
        if ($centavos > 0) {
            if ($resultado !== '') {
                $resultado .= ' e ';
            }
            $resultado .= $this->numeroPorExtenso($centavos, $unidades, $dezenas, $dezenasEspeciais, $centenas);
            $resultado .= ' ' . ($centavos == 1 ? 'centavo' : 'centavos');
        }

        return $resultado;
    }

    /**
     * Converte número para extenso (auxiliar)
     */
    private function numeroPorExtenso(int $numero, array $unidades, array $dezenas, array $dezenasEspeciais, array $centenas): string
    {
        if ($numero == 0) {
            return '';
        }

        if ($numero < 10) {
            return $unidades[$numero];
        }

        if ($numero < 20) {
            return $dezenasEspeciais[$numero - 10];
        }

        if ($numero < 100) {
            $dezena = (int) ($numero / 10);
            $unidade = $numero % 10;
            $resultado = $dezenas[$dezena];
            if ($unidade > 0) {
                $resultado .= ' e ' . $unidades[$unidade];
            }
            return $resultado;
        }

        if ($numero < 1000) {
            $centena = (int) ($numero / 100);
            $resto = $numero % 100;
            
            if ($numero == 100) {
                return 'cem';
            }
            
            $resultado = $centenas[$centena];
            if ($resto > 0) {
                $resultado .= ' e ' . $this->numeroPorExtenso($resto, $unidades, $dezenas, $dezenasEspeciais, $centenas);
            }
            return $resultado;
        }

        if ($numero < 1000000) {
            $milhar = (int) ($numero / 1000);
            $resto = $numero % 1000;
            
            if ($milhar == 1) {
                $resultado = 'mil';
            } else {
                $resultado = $this->numeroPorExtenso($milhar, $unidades, $dezenas, $dezenasEspeciais, $centenas) . ' mil';
            }
            
            if ($resto > 0) {
                $resultado .= ($resto < 100 ? ' e ' : ' ') . $this->numeroPorExtenso($resto, $unidades, $dezenas, $dezenasEspeciais, $centenas);
            }
            return $resultado;
        }

        return (string) $numero;
    }

    /**
     * Preview do recibo em HTML (sem salvar)
     */
    public function previewReciboHtml(ClientContract $contrato): string
    {
        $dados = $this->montarDadosRecibo($contrato);
        return view('billing.recibos.adesao', $dados)->render();
    }

    /**
     * Retorna o PDF como stream para download direto
     */
    public function streamReciboAdesao(ClientContract $contrato)
    {
        $dados = $this->montarDadosRecibo($contrato);

        $pdf = Pdf::loadView('billing.recibos.adesao', $dados);
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ]);

        $nomeArquivo = 'recibo-adesao-' . $contrato->id . '.pdf';

        return $pdf->stream($nomeArquivo);
    }

    /**
     * Identifica se o contrato é de troca de plano
     */
    private function identificarTrocaPlano(ClientContract $contrato): bool
    {
        $limites = $contrato->limites_contratados;
        if (is_string($limites)) {
            $limites = json_decode($limites, true);
        }
        $limites = is_array($limites) ? $limites : [];

        if (!empty($limites['tipo_troca'])) {
            return true;
        }

        $titulo = strtolower((string) ($contrato->titulo_contrato ?? ''));
        if (str_contains($titulo, 'troca') || str_contains($titulo, 'aditivo') || str_contains($titulo, 'migracao') || str_contains($titulo, 'migração')) {
            return true;
        }

        $corpo = strtolower((string) ($contrato->corpo_contrato ?? ''));
        if (str_contains($corpo, 'troca de plano') || str_contains($corpo, 'migração de plano') || str_contains($corpo, 'migracao de plano')) {
            return true;
        }

        return false;
    }

    /**
     * Obtém o tipo de troca (upgrade/downgrade)
     */
    private function obterTipoTroca(ClientContract $contrato): ?string
    {
        $limites = $contrato->limites_contratados;
        if (is_string($limites)) {
            $limites = json_decode($limites, true);
        }
        $limites = is_array($limites) ? $limites : [];

        if (!empty($limites['tipo_troca'])) {
            return strtolower($limites['tipo_troca']);
        }

        $corpo = strtolower((string) ($contrato->corpo_contrato ?? ''));
        if (str_contains($corpo, 'upgrade')) {
            return 'upgrade';
        }
        if (str_contains($corpo, 'downgrade')) {
            return 'downgrade';
        }

        return 'migração';
    }

    /**
     * Obtém o valor mensal anterior formatado (para trocas de plano)
     */
    private function obterValorAnterior(ClientContract $contrato): ?string
    {
        $limites = $contrato->limites_contratados;
        if (is_string($limites)) {
            $limites = json_decode($limites, true);
        }
        $limites = is_array($limites) ? $limites : [];

        if (!empty($limites['mensalidade_anterior'])) {
            $valor = (float) $limites['mensalidade_anterior'];
            return 'R$ ' . number_format($valor, 2, ',', '.');
        }

        $corpo = (string) ($contrato->corpo_contrato ?? '');
        if (preg_match('/valor\s*mensal\s*anterior[:\s]*R?\$?\s*([\d.,]+)/i', $corpo, $matches)) {
            $valor = (float) str_replace(['.', ','], ['', '.'], $matches[1]);
            return 'R$ ' . number_format($valor, 2, ',', '.');
        }

        return null;
    }
}
