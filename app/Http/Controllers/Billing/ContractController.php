<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\ClientContract;
use App\Models\Plano;
use App\Models\PlanoContratado;
use App\Services\Billing\ContractPdfService;
use App\Domain\Auth\Models\Empresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ContractController extends Controller
{
    private const VERSAO_CONTRATO_ATUAL = '1.0';

    public function __construct(
        private readonly ContractPdfService $pdfService
    ) {
    }

    /**
     * Processa o aceite do contrato de licenciamento SaaS
     * 
     * Garante validade jurídica através de:
     * - Captura de IP real do cliente
     * - Captura de User-Agent
     * - Hash SHA-256 do escopo completo
     * - Snapshot de todos os dados no momento do aceite
     */
    public function accept(Request $request): JsonResponse
    {
        $request->validate([
            'id_empresa' => 'required|integer',
            'id_plano' => 'nullable|integer|exists:planos,id_plano',
            'id_plano_contratado' => 'nullable|integer',
            'assinado_por_nome' => 'required|string|max:255',
            'assinado_por_documento' => 'required|string|max:20',
            'assinado_por_email' => 'nullable|email|max:255',
            'assinatura_base64' => 'nullable|string',
            'gerar_recibo' => 'nullable|boolean',
        ]);

        try {
            $usuarioAutenticado = Auth::user();
            $idEmpresaSessao = (int) (session('id_empresa') ?: ($usuarioAutenticado ? $usuarioAutenticado->id_empresa : 0));
            $idEmpresaRequest = (int) $request->id_empresa;

            if ($idEmpresaSessao > 0 && $idEmpresaRequest !== $idEmpresaSessao) {
                return response()->json([
                    'success' => false,
                    'message' => 'Você não tem permissão para aceitar contrato para outra empresa.',
                ], 403); // Segurança: bloqueia aceite de contrato fora da empresa da sessão (IDOR).
            }

            $idEmpresaAlvo = $idEmpresaSessao > 0 ? $idEmpresaSessao : $idEmpresaRequest;

            $contrato = DB::transaction(function () use ($request, $idEmpresaAlvo) {
                $empresa = Empresa::where('id_empresa', $idEmpresaAlvo) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
                    ->findOrFail($idEmpresaAlvo);
                
                // Buscar plano contratado ou plano base
                if ($request->id_plano_contratado) {
                    $planoContratado = PlanoContratado::where('id_empresa', $empresa->id_empresa)
                        ->find($request->id_plano_contratado); // Segurança: impede carregar plano contratado de outra empresa.

                    if (!$planoContratado) {
                        throw new Exception('Plano contratado não encontrado para a empresa informada.');
                    }
                } else {
                    $planoContratado = PlanoContratado::planoAtivoDaEmpresa($empresa->id_empresa);
                }
                
                $plano = $request->id_plano
                    ? Plano::find($request->id_plano)
                    : ($planoContratado ? Plano::where('nome', 'LIKE', '%' . $planoContratado->nome . '%')->first() : null);

                // Marcar contratos anteriores como substituídos
                ClientContract::where('id_empresa', $empresa->id_empresa)
                    ->where('status', ClientContract::STATUS_ATIVO)
                    ->update([
                        'status' => ClientContract::STATUS_SUBSTITUIDO,
                        'motivo_revogacao' => 'Substituído por novo aceite em ' . now()->format('d/m/Y H:i:s'),
                        'revogado_em' => now(),
                    ]);

                // Montar snapshot dos limites do plano
                $limites = $this->montarLimitesPlano($planoContratado, $plano);

                // Montar endereço completo
                $endereco = $this->montarEnderecoCompleto($empresa);

                // Obter valores
                $valorAdesao = (float) ($planoContratado->adesao ?? $plano->adesao ?? 0);
                $valorMensalidade = (float) ($planoContratado->valor ?? $plano->valor ?? 0);

                // Gerar corpo do contrato
                $corpoContrato = $this->gerarCorpoContrato($empresa, $planoContratado, $plano, $limites, $valorAdesao, $valorMensalidade);

                // Capturar dados de rastreabilidade
                $ipAceite = $this->capturarIpReal($request);
                $userAgent = $request->userAgent();
                $aceitoEm = now();

                // Preparar dados para hash
                $dadosContrato = [
                    'id_empresa' => $empresa->id_empresa,
                    'cliente_razao_social' => $empresa->razao_social ?? $empresa->nome_empresa,
                    'cliente_cnpj_cpf' => $empresa->cnpj ?? $empresa->cpf,
                    'valor_adesao' => $valorAdesao,
                    'valor_mensalidade' => $valorMensalidade,
                    'limites_contratados' => $limites,
                    'versao_contrato' => self::VERSAO_CONTRATO_ATUAL,
                    'corpo_contrato' => $corpoContrato,
                    'aceito_em' => $aceitoEm->toIso8601String(),
                ];

                // Calcular hash SHA-256 do documento
                $hashDocumento = ClientContract::calcularHash($dadosContrato);

                // Criar registro do contrato
                $contrato = ClientContract::create([
                    'id_empresa' => $empresa->id_empresa,
                    'id_plano' => $plano?->id_plano,
                    'id_plano_contratado' => $planoContratado?->id,
                    
                    // Snapshot do cliente
                    'cliente_razao_social' => $empresa->razao_social ?? $empresa->nome_empresa,
                    'cliente_cnpj_cpf' => $empresa->cnpj ?? $empresa->cpf,
                    'cliente_email' => $empresa->email,
                    'cliente_endereco' => $endereco,
                    
                    // Valores
                    'valor_adesao' => $valorAdesao,
                    'valor_mensalidade' => $valorMensalidade,
                    
                    // Limites
                    'limites_contratados' => $limites,
                    
                    // Contrato
                    'versao_contrato' => self::VERSAO_CONTRATO_ATUAL,
                    'titulo_contrato' => 'Contrato de Licenciamento de Software SaaS - Gestor Now',
                    'corpo_contrato' => $corpoContrato,
                    'hash_documento' => $hashDocumento,
                    
                    // Assinatura
                    'assinatura_base64' => $request->assinatura_base64,
                    'assinado_por_nome' => $request->assinado_por_nome,
                    'assinado_por_documento' => preg_replace('/\D/', '', $request->assinado_por_documento),
                    'assinado_por_email' => $request->assinado_por_email ?? $empresa->email,
                    
                    // Rastreabilidade (não-repúdio)
                    'ip_aceite' => $ipAceite,
                    'user_agent' => $userAgent,
                    'aceito_em' => $aceitoEm,
                    
                    // Status
                    'status' => ClientContract::STATUS_ATIVO,
                ]);

                return $contrato;
            });

            // Gerar recibo se solicitado
            $reciboPath = null;
            if ($request->boolean('gerar_recibo', true) && $contrato->valor_adesao > 0) {
                try {
                    $reciboPath = $this->pdfService->gerarReciboAdesao($contrato);
                    $contrato->registrarReciboGerado($reciboPath);
                } catch (Exception $e) {
                    Log::warning('Falha ao gerar recibo de adesão', [
                        'contrato_id' => $contrato->id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Contrato de licenciamento aceito com sucesso', [
                'contrato_id' => $contrato->id,
                'id_empresa' => $contrato->id_empresa,
                'hash' => $contrato->hash_documento,
                'ip' => $contrato->ip_aceite,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contrato aceito com sucesso.',
                'data' => [
                    'contrato_id' => $contrato->id,
                    'hash_documento' => $contrato->hash_documento,
                    'aceito_em' => $contrato->aceito_em_formatado,
                    'recibo_path' => $reciboPath,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Erro ao processar aceite de contrato', [
                'id_empresa' => $idEmpresaAlvo,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao processar aceite do contrato: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Captura o IP real do cliente, considerando proxies/load balancers
     */
    private function capturarIpReal(Request $request): string
    {
        // Ordem de prioridade para IP real
        $headers = [
            'CF-Connecting-IP',      // Cloudflare
            'True-Client-IP',        // Akamai/Cloudflare Enterprise
            'X-Forwarded-For',       // Proxies padrão
            'X-Real-IP',             // Nginx
        ];

        foreach ($headers as $header) {
            $ip = $request->header($header);
            if ($ip) {
                // X-Forwarded-For pode ter múltiplos IPs, pegar o primeiro
                $ips = explode(',', $ip);
                $ipReal = trim($ips[0]);
                
                if (filter_var($ipReal, FILTER_VALIDATE_IP)) {
                    return $ipReal;
                }
            }
        }

        // Fallback para método nativo do Laravel
        return (string) $request->ip();
    }

    /**
     * Monta os limites do plano para snapshot
     */
    private function montarLimitesPlano(?PlanoContratado $planoContratado, ?Plano $plano): array
    {
        $limites = [];

        // Se tem plano contratado, buscar módulos contratados
        if ($planoContratado) {
            $modulos = $planoContratado->modulos()->with('modulo')->get();
            
            foreach ($modulos as $moduloContratado) {
                $nomeModulo = $moduloContratado->modulo?->nome 
                    ?? $moduloContratado->nome_modulo 
                    ?? 'modulo_' . $moduloContratado->id_modulo;
                
                $chave = $this->normalizarChaveModulo($nomeModulo);
                $limites[$chave] = $moduloContratado->limite ?? 0; // 0 = ilimitado
            }
        }

        // Complementar com dados do plano base se disponível
        if ($plano) {
            $limites['relatorios'] = $plano->relatorios === 'S';
            $limites['bancos_boleto'] = $plano->bancos === 'S';
            $limites['assinatura_digital'] = $plano->assinatura_digital === 'S';
            $limites['modelos_contrato'] = $plano->contratos === 'S' ? 'Ilimitado' : '1';
            $limites['faturas'] = $plano->faturas === 'S';
        }

        // Garantir limites padrão se não definidos
        $limitesDefault = [
            'clientes' => 0,
            'produtos' => 0,
            'usuarios' => 1,
            'locacoes' => 'completo',
            'financeiro' => 'completo',
        ];

        foreach ($limitesDefault as $chave => $valorDefault) {
            if (!isset($limites[$chave])) {
                $limites[$chave] = $valorDefault;
            }
        }

        return $limites;
    }

    /**
     * Normaliza nome do módulo para chave do array
     */
    private function normalizarChaveModulo(string $nome): string
    {
        $nome = mb_strtolower($nome);
        $nome = preg_replace('/[^a-z0-9]+/', '_', $nome);
        return trim($nome, '_');
    }

    /**
     * Monta endereço completo formatado
     */
    private function montarEnderecoCompleto(Empresa $empresa): string
    {
        $partes = array_filter([
            $empresa->endereco,
            $empresa->numero ? 'Nº ' . $empresa->numero : null,
            $empresa->complemento,
            $empresa->bairro,
            $empresa->cidade,
            $empresa->uf,
            $empresa->cep ? 'CEP: ' . $empresa->cep : null,
        ]);

        return implode(', ', $partes);
    }

    /**
     * Gera o corpo do contrato com todos os dados
     */
    private function gerarCorpoContrato(
        Empresa $empresa,
        ?PlanoContratado $planoContratado,
        ?Plano $plano,
        array $limites,
        float $valorAdesao,
        float $valorMensalidade
    ): string {
        $nomePlano = $planoContratado?->nome ?? $plano?->nome ?? 'Plano Contratado';
        $dataAtual = now()->format('d/m/Y');
        $razaoSocial = $empresa->razao_social ?? $empresa->nome_empresa;
        $documento = $empresa->cnpj ?? $empresa->cpf;
        $endereco = $this->montarEnderecoCompleto($empresa);

        // Formatar limites para exibição no contrato
        $limitesTexto = $this->formatarLimitesParaContrato($limites);

        return implode("\n", [
            'CONTRATO DE LICENCIAMENTO DE SOFTWARE SAAS - GESTOR NOW',
            '',
            'IDENTIFICACAO DAS PARTES',
            '',
            'CONTRATANTE:',
            'Razao Social/Nome: ' . $razaoSocial,
            'CNPJ/CPF: ' . $documento,
            'Endereco: ' . $endereco,
            'E-mail: ' . $empresa->email,
            '',
            'CONTRATADA:',
            '[DADOS DA EMPRESA CONTRATADA - GESTOR NOW]',
            '',
            'DATA DO ACEITE: ' . $dataAtual,
            '',
            '1. OBJETO DO CONTRATO',
            '',
            '1.1. O presente contrato tem por objeto a licenca de uso do software GESTOR NOW, na modalidade SaaS (Software as a Service), conforme plano "' . $nomePlano . '".',
            '',
            '2. PLANO CONTRATADO E LIMITES',
            '',
            '2.1. Plano: ' . $nomePlano,
            '2.2. Valor de Adesao: R$ ' . $this->formatarValor($valorAdesao),
            '2.3. Valor da Mensalidade: R$ ' . $this->formatarValor($valorMensalidade),
            '',
            '2.4. Recursos e Limites do Plano:',
            $limitesTexto,
            '',
            '3. CONDICOES DE PAGAMENTO',
            '',
            '3.1. O CONTRATANTE pagara a CONTRATADA:',
            '   a) Taxa de adesao no valor de R$ ' . $this->formatarValor($valorAdesao) . ', no ato da contratacao;',
            '   b) Mensalidade no valor de R$ ' . $this->formatarValor($valorMensalidade) . ', com vencimento mensal.',
            '',
            '4. VIGENCIA',
            '',
            '4.1. O presente contrato vigorara por prazo indeterminado, podendo ser rescindido por qualquer das partes mediante aviso previo de 30 (trinta) dias.',
            '',
            '5. DISPOSICOES GERAIS',
            '',
            '5.1. Este contrato foi aceito eletronicamente, tendo plena validade juridica conforme Lei n 14.063/2020 e Medida Provisoria n 2.200-2/2001.',
            '',
            '5.2. O aceite eletronico deste contrato constitui prova de que o CONTRATANTE leu, compreendeu e concorda com todos os termos aqui estabelecidos.',
            '',
            '5.3. Este documento possui hash de integridade SHA-256 que garante sua autenticidade e nao alteracao.',
        ]);
    }

    /**
     * Formata os limites para exibição no corpo do contrato
     */
    private function formatarLimitesParaContrato(array $limites): string
    {
        $labels = [
            'clientes' => 'Limite de Clientes',
            'produtos' => 'Limite de Produtos',
            'usuarios' => 'Limite de Usuários',
            'modelos_contrato' => 'Modelos de Contrato',
            'bancos_boleto' => 'Bancos para Boleto',
            'locacoes' => 'Módulo de Locações',
            'financeiro' => 'Módulo Financeiro',
            'relatorios' => 'Relatórios',
            'assinatura_digital' => 'Assinatura Digital',
            'faturas' => 'Faturas',
        ];

        $linhas = [];
        foreach ($limites as $chave => $valor) {
            $label = $labels[$chave] ?? ucfirst(str_replace('_', ' ', $chave));
            
            if (is_bool($valor)) {
                $valorFormatado = $valor ? 'Disponível' : 'Não disponível';
            } elseif (is_numeric($valor) && $valor == 0) {
                $valorFormatado = 'Ilimitado';
            } elseif (is_numeric($valor)) {
                $valorFormatado = number_format($valor, 0, ',', '.');
            } else {
                $valorFormatado = ucfirst((string) $valor);
            }
            
            $linhas[] = "   - {$label}: {$valorFormatado}";
        }

        return implode("\n", $linhas);
    }

    /**
     * Formata valor monetário
     */
    private function formatarValor(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    /**
     * Exibe contrato para visualização
     */
    public function show(int $id): JsonResponse
    {
        $usuarioAutenticado = Auth::user();
        $idEmpresa = (int) (session('id_empresa') ?: ($usuarioAutenticado ? $usuarioAutenticado->id_empresa : 0));

        $contrato = ClientContract::with(['empresa', 'plano', 'planoContratado'])
            ->where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'contrato' => $contrato,
                'limites_formatados' => $contrato->getLimitesFormatados(),
                'integridade_valida' => $contrato->verificarIntegridade(
                    ClientContract::calcularHash([
                        'id_empresa' => $contrato->id_empresa,
                        'cliente_razao_social' => $contrato->cliente_razao_social,
                        'cliente_cnpj_cpf' => $contrato->cliente_cnpj_cpf,
                        'valor_adesao' => $contrato->valor_adesao,
                        'valor_mensalidade' => $contrato->valor_mensalidade,
                        'limites_contratados' => $contrato->limites_contratados,
                        'versao_contrato' => $contrato->versao_contrato,
                        'corpo_contrato' => $contrato->corpo_contrato,
                        'aceito_em' => $contrato->aceito_em->toIso8601String(),
                    ])
                ),
            ],
        ]);
    }

    /**
     * Download do recibo em PDF
     */
    public function downloadRecibo(int $id)
    {
        $usuarioAutenticado = Auth::user();
        $idEmpresa = (int) (session('id_empresa') ?: ($usuarioAutenticado ? $usuarioAutenticado->id_empresa : 0));
        $contrato = ClientContract::where('id_empresa', $idEmpresa) // Segurança: restringe a consulta à empresa da sessão para bloquear IDOR.
            ->findOrFail($id);

        try {
            // Regenera sempre para refletir layout/logo mais recentes.
            $reciboPath = $this->pdfService->gerarReciboAdesao($contrato);
            $contrato->registrarReciboGerado($reciboPath);
            $contrato->refresh();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Nao foi possivel gerar o recibo no momento: ' . $e->getMessage(),
            ], 500);
        }

        $fullPath = storage_path('app/' . $contrato->recibo_path);

        if (!file_exists($fullPath)) {
            return response()->json([
                'success' => false,
                'message' => 'Arquivo do recibo não encontrado.',
            ], 404);
        }

        return response()->download($fullPath, 'recibo-adesao-' . $contrato->id . '.pdf');
    }
}
