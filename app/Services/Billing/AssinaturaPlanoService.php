<?php

namespace App\Services\Billing;

use App\Domain\Auth\Models\Empresa;
use App\Models\AssinaturaPlano;
use App\Models\AssinaturaPlanoPagamento;
use App\Models\ClientContract;
use App\Models\EmpresaContratoSoftware;
use App\Models\Plano;
use App\Models\PlanoContratado;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AssinaturaPlanoService
{
    private const CARD_META_PREFIX = '[ASAAS_CARD_META]';
    private const ADESAO_PROVISIONAMENTO_PREFIX = '[ADESAO_PROVISIONAMENTO]';
    private const ADESAO_UPGRADE_PREFIX = '[ADESAO_UPGRADE]';
    private ?bool $cancelamentoColsDisponiveis = null;
    private ?int $limiteObservacoesPagamento = null;

    public function __construct(
        private readonly AsaasGatewayService $asaasGateway,
        private readonly PlanoProvisioningService $planoProvisioningService,
        private readonly PlanoPromocaoService $planoPromocaoService
    ) {
    }

    /**
     * Inicia um fluxo de assinatura para dashboard (autoatendimento) ou comercial.
     */
    public function iniciarAssinatura(Empresa $empresa, Plano $plano, array $opcoes = []): array
    {
        if (!$this->tabelasBillingDisponiveis()) {
            throw new Exception('Estrutura de assinaturas não instalada. Execute o SQL de billing antes de contratar.');
        }

        $origem = (string) ($opcoes['origem'] ?? AssinaturaPlano::ORIGEM_DASHBOARD);
        $gerarCobrancas = (bool) ($opcoes['gerar_cobrancas'] ?? true);

        $metodoAdesao = $this->asaasGateway->normalizeBillingType((string) ($opcoes['metodo_adesao'] ?? AssinaturaPlano::METODO_PIX));
        $metodoMensal = $this->asaasGateway->normalizeBillingType((string) ($opcoes['metodo_mensal'] ?? AssinaturaPlano::METODO_BOLETO));

        if ($metodoMensal === AssinaturaPlano::METODO_PIX) {
            throw new Exception('PIX está disponível apenas para pagamento de adesão.');
        }

        $valorMensalCustomizado = array_key_exists('valor_mensal', $opcoes)
            && $opcoes['valor_mensal'] !== null
            && $opcoes['valor_mensal'] !== '';

        $valorAdesaoCustomizado = array_key_exists('valor_adesao', $opcoes)
            && $opcoes['valor_adesao'] !== null
            && $opcoes['valor_adesao'] !== '';

        $promocaoAplicada = null;
        $precosPromocionais = $this->planoPromocaoService->calcularValoresPromocionais($plano, $empresa);

        $valorMensal = $valorMensalCustomizado
            ? (float) $opcoes['valor_mensal']
            : (float) ($precosPromocionais['valor_mensal_final'] ?? $plano->valor);

        $valorAdesao = $valorAdesaoCustomizado
            ? (float) $opcoes['valor_adesao']
            : (float) ($precosPromocionais['valor_adesao_final'] ?? $plano->adesao);

        $valorAdesaoProvisionamento = array_key_exists('valor_adesao_provisionamento', $opcoes)
            && $opcoes['valor_adesao_provisionamento'] !== null
            && $opcoes['valor_adesao_provisionamento'] !== ''
            ? (float) $opcoes['valor_adesao_provisionamento']
            : $valorAdesao;

        $primeiraMensalidadeVencimento = array_key_exists('primeira_mensalidade_vencimento', $opcoes)
            ? (string) ($opcoes['primeira_mensalidade_vencimento'] ?? '')
            : '';

        $cancelarMensalidadesDesde = array_key_exists('cancelar_mensalidades_desde', $opcoes)
            ? (string) ($opcoes['cancelar_mensalidades_desde'] ?? '')
            : '';

        $mensalidadePonte = is_array($opcoes['mensalidade_ponte'] ?? null)
            ? $opcoes['mensalidade_ponte']
            : null;

        $gerarMensalidadeAjusteMigracao = (bool) ($opcoes['gerar_mensalidade_ajuste_migracao'] ?? false);
        $substituirAdesaoPendente = (bool) ($opcoes['substituir_adesao_pendente'] ?? false);
        $descricaoAdesao = trim((string) ($opcoes['descricao_adesao'] ?? ''));
        $adesaoUpgradePlanoNome = trim((string) ($opcoes['adesao_upgrade_plano_nome'] ?? ''));

        if (!$valorMensalCustomizado || !$valorAdesaoCustomizado) {
            $promocaoAplicada = $precosPromocionais['promocao_aplicada'] ?? null;
        }

        $observacoes = (string) ($opcoes['observacoes'] ?? '');
        $cardData = $opcoes['card_data'] ?? null;

        return DB::transaction(function () use (
            $empresa,
            $plano,
            $origem,
            $gerarCobrancas,
            $metodoAdesao,
            $metodoMensal,
            $valorMensal,
            $valorAdesao,
            $valorAdesaoProvisionamento,
            $promocaoAplicada,
            $observacoes,
            $cardData,
            $primeiraMensalidadeVencimento,
            $cancelarMensalidadesDesde,
            $mensalidadePonte,
            $gerarMensalidadeAjusteMigracao,
            $substituirAdesaoPendente,
            $descricaoAdesao,
            $adesaoUpgradePlanoNome
        ) {
            $assinatura = AssinaturaPlano::query()
                ->where('id_empresa', $empresa->id_empresa)
                ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
                ->latest('id')
                ->first();

            if (!$assinatura) {
                $assinatura = new AssinaturaPlano();
                $assinatura->id_empresa = $empresa->id_empresa;
            }

            $assinatura->id_plano = $plano->id_plano;
            $assinatura->origem = $origem;
            $assinatura->metodo_adesao = $metodoAdesao;
            $assinatura->metodo_mensal = $metodoMensal;
            $assinatura->observacoes = $observacoes ?: $assinatura->observacoes;
            $assinatura->status = $gerarCobrancas
                ? AssinaturaPlano::STATUS_PENDENTE_PAGAMENTO
                : $this->resolverStatusOnboarding($empresa);

            $this->limparAgendamentoCancelamento($assinatura);

            $assinatura->save();

            if (!$gerarCobrancas) {
                $contrato = $this->planoProvisioningService->ativarPlano(
                    $empresa,
                    $plano,
                    $valorMensal,
                    $valorAdesaoProvisionamento,
                    'Contratação manual sem cobrança automática (' . $origem . ')'
                );

                $assinatura->id_plano_contratado = $contrato->id;
                $assinatura->status = $this->resolverStatusOnboarding($empresa);
                $assinatura->save();

                return [
                    'assinatura' => $assinatura,
                    'plano_contratado' => $contrato,
                    'adesao_pagamento' => null,
                    'mensal_pagamento' => null,
                    'promocao_aplicada' => $promocaoAplicada,
                    'mensagem' => 'Plano contratado com sucesso sem geração de cobrança automática.',
                ];
            }

            $customerId = (string) ($assinatura->asaas_customer_id ?: $this->asaasGateway->createOrGetCustomer($empresa));
            $assinatura->asaas_customer_id = $customerId;

            $subscriptionAnteriorId = $this->obterSubscriptionId($assinatura);
            $tokenCartaoAssinaturaAnterior = null;

            if (
                $subscriptionAnteriorId
                && $metodoMensal === AssinaturaPlano::METODO_CREDIT_CARD
                && empty($cardData)
            ) {
                $tokenCartaoAssinaturaAnterior = $this->obterTokenCartaoAssinaturaAsaas($subscriptionAnteriorId);
            }

            if ($subscriptionAnteriorId) {
                $this->cancelarAssinaturaRecorrenteAsaas($subscriptionAnteriorId);
                $this->definirSubscriptionId($assinatura, null);
            }

            if ($cancelarMensalidadesDesde !== '') {
                $this->cancelarPagamentosFuturos(
                    $assinatura,
                    Carbon::parse($cancelarMensalidadesDesde)->startOfDay()
                );
            }

            if ($substituirAdesaoPendente) {
                $this->cancelarAdesoesPendentes(
                    $assinatura,
                    'Cancelada por nova troca de plano'
                );
            }

            $observacaoAdesaoProvisionamento = $this->montarObservacaoAdesaoProvisionamento(
                $valorAdesaoProvisionamento,
                $valorAdesao
            );

            $observacaoAdesaoUpgrade = $this->montarObservacaoAdesaoUpgrade($adesaoUpgradePlanoNome);
            $observacaoAdesaoFinal = $this->anexarObservacao(
                (string) $observacaoAdesaoProvisionamento,
                (string) $observacaoAdesaoUpgrade
            );
            $observacaoAdesaoFinal = trim($observacaoAdesaoFinal) !== '' ? $observacaoAdesaoFinal : null;
            $descricaoAdesaoPagamento = $descricaoAdesao !== ''
                ? $descricaoAdesao
                : 'Adesão do plano ' . $plano->nome;

            $adesaoPagamento = null;
            $erroCartaoAdesao = null;

            if ($valorAdesao > 0) {
                // Tenta cobrar adesão com cartão
                try {
                    $adesaoPagamento = $this->criarPagamentoAdesaoComMetodo(
                        assinatura: $assinatura,
                        empresa: $empresa,
                        billingType: $metodoAdesao,
                        valor: $valorAdesao,
                        descricao: $descricaoAdesaoPagamento,
                        cardData: ($metodoAdesao === AssinaturaPlano::METODO_CREDIT_CARD) ? $cardData : null,
                        observacoes: $observacaoAdesaoFinal
                    );
                } catch (Exception $e) {
                    // Se foi erro de cartão, cria cobrança para pagamento manual via link
                    $mensagemErro = strtolower($e->getMessage());
                    $errosCartao = [
                        'não autorizada',
                        'transação não autorizada',
                        'cartão recusado',
                        'cartão inválido',
                        'cartão expirado',
                        'saldo insuficiente',
                        'limite insuficiente',
                        'credit_card',
                        'creditcard',
                    ];

                    $isErroCartao = $metodoAdesao === AssinaturaPlano::METODO_CREDIT_CARD;
                    foreach ($errosCartao as $termo) {
                        if (str_contains($mensagemErro, $termo)) {
                            $isErroCartao = true;
                            break;
                        }
                    }

                    if ($isErroCartao && $metodoAdesao === AssinaturaPlano::METODO_CREDIT_CARD) {
                        $erroCartaoAdesao = $e->getMessage();

                        Log::warning('Cartão recusado na adesão, criando cobrança para pagamento manual', [
                            'id_empresa' => $empresa->id_empresa,
                            'erro_original' => $erroCartaoAdesao,
                        ]);

                        // Cria cobrança de cartão sem dados do cartão (pagamento manual via link)
                        $adesaoPagamento = $this->criarPagamentoAdesaoComMetodo(
                            assinatura: $assinatura,
                            empresa: $empresa,
                            billingType: AssinaturaPlano::METODO_CREDIT_CARD,
                            valor: $valorAdesao,
                            descricao: $descricaoAdesaoPagamento,
                            cardData: null, // Sem dados do cartão = pagamento manual
                            observacoes: $observacaoAdesaoFinal
                        );
                    } else {
                        throw $e;
                    }
                }
            } else {
                $adesaoPagamento = AssinaturaPlanoPagamento::create([
                    'id_assinatura_plano' => $assinatura->id,
                    'id_empresa' => $empresa->id_empresa,
                    'id_plano' => $plano->id_plano,
                    'tipo_cobranca' => AssinaturaPlanoPagamento::TIPO_ADESAO,
                    'competencia' => now()->startOfMonth()->toDateString(),
                    'metodo_pagamento' => $metodoAdesao,
                    'valor' => 0,
                    'data_vencimento' => now()->toDateString(),
                    'data_pagamento' => now(),
                    'status' => AssinaturaPlanoPagamento::STATUS_PAGO,
                    'observacoes' => $this->normalizarObservacaoPagamento(
                        $this->anexarObservacao(
                            $this->anexarObservacao('Adesão gratuita (R$ 0,00).', (string) $observacaoAdesaoProvisionamento),
                            (string) $observacaoAdesaoUpgrade
                        )
                    ),
                ]);
            }

            $vencimentoMensal = $primeiraMensalidadeVencimento !== ''
                ? Carbon::parse($primeiraMensalidadeVencimento)->toDateString()
                : now()->addDays(30)->toDateString();
            $erroCartaoMensal = null;

            // Tenta criar assinatura com o método escolhido
            try {
                $mensalAssinatura = $this->criarAssinaturaMensalAsaas(
                    assinatura: $assinatura,
                    empresa: $empresa,
                    billingType: $metodoMensal,
                    valor: $valorMensal,
                    dataPrimeiroVencimento: $vencimentoMensal,
                    descricao: 'Assinatura mensal do plano ' . $plano->nome,
                    cardData: $cardData,
                    creditCardToken: $tokenCartaoAssinaturaAnterior
                );
            } catch (Exception $e) {
                // Se foi erro de cartão, cria como cartão mas para pagamento manual (sem dados do cartão)
                $mensagemErro = strtolower($e->getMessage());
                $errosCartao = [
                    'não autorizada',
                    'transação não autorizada',
                    'cartão recusado',
                    'cartão inválido',
                    'cartão expirado',
                    'saldo insuficiente',
                    'limite insuficiente',
                    'credit_card',
                    'creditcard',
                ];

                $isErroCartao = $metodoMensal === AssinaturaPlano::METODO_CREDIT_CARD;
                foreach ($errosCartao as $termo) {
                    if (str_contains($mensagemErro, $termo)) {
                        $isErroCartao = true;
                        break;
                    }
                }

                if ($isErroCartao && $metodoMensal === AssinaturaPlano::METODO_CREDIT_CARD) {
                    $erroCartaoMensal = $e->getMessage();

                    Log::warning('Cartão recusado, criando assinatura como cartão para pagamento manual', [
                        'id_empresa' => $empresa->id_empresa,
                        'erro_original' => $erroCartaoMensal,
                    ]);

                    // Cria como cartão mas sem dados do cartão (pagamento manual via link)
                    $mensalAssinatura = $this->criarAssinaturaMensalAsaas(
                        assinatura: $assinatura,
                        empresa: $empresa,
                        billingType: AssinaturaPlano::METODO_CREDIT_CARD,
                        valor: $valorMensal,
                        dataPrimeiroVencimento: $vencimentoMensal,
                        descricao: 'Assinatura mensal do plano ' . $plano->nome,
                        cardData: null, // Sem dados do cartão = pagamento manual
                        creditCardToken: null
                    );
                } else {
                    // Se não foi erro de cartão, relança a exceção
                    throw $e;
                }
            }

            $mensalidadePontePagamento = null;
            $mensalidadeAjusteGapPagamento = null;

            if (is_array($mensalidadePonte)) {
                $valorPonte = round((float) ($mensalidadePonte['valor'] ?? 0), 2);
                $dataVencimentoPonte = trim((string) ($mensalidadePonte['data_vencimento'] ?? ''));

                if ($valorPonte > 0 && $dataVencimentoPonte !== '') {
                    $metodoPonte = $this->asaasGateway->normalizeBillingType(
                        (string) ($mensalidadePonte['metodo_pagamento'] ?? $metodoMensal)
                    );

                    $descricaoPonte = trim((string) ($mensalidadePonte['descricao'] ?? ''));
                    if ($descricaoPonte === '') {
                        $descricaoPonte = 'Mensalidade de transição do plano ' . $plano->nome;
                    }

                    $mensalidadePontePagamento = $this->criarPagamentoAsaas(
                        assinatura: $assinatura,
                        empresa: $empresa,
                        tipoCobranca: AssinaturaPlanoPagamento::TIPO_MENSALIDADE,
                        billingType: $metodoPonte,
                        valor: $valorPonte,
                        dataVencimento: Carbon::parse($dataVencimentoPonte)->toDateString(),
                        descricao: $descricaoPonte,
                        competencia: Carbon::parse($dataVencimentoPonte)->startOfMonth()
                    );
                }
            }

            $proximaCobranca = Carbon::parse((string) ($mensalAssinatura['next_due_date'] ?? $vencimentoMensal))
                ->toDateString();

            $primeiroVencimentoSolicitado = Carbon::parse($vencimentoMensal)->toDateString();
            $primeiroPaymentSubscription = $this->obterPrimeiroPaymentSubscription(
                (string) ($mensalAssinatura['id'] ?? ''),
                5,
                300
            );
            $dataPrimeiroPaymentSubscription = null;

            if (!empty($primeiroPaymentSubscription['dueDate'])) {
                $dataPrimeiroPaymentSubscription = Carbon::parse((string) $primeiroPaymentSubscription['dueDate'])
                    ->toDateString();
            }

            $mensalidadeFoiJogadaParaFrente = Carbon::parse($proximaCobranca)->gt(Carbon::parse($primeiroVencimentoSolicitado))
                && (
                    is_null($dataPrimeiroPaymentSubscription)
                    || Carbon::parse($dataPrimeiroPaymentSubscription)->gt(Carbon::parse($primeiroVencimentoSolicitado))
                );

            if ($gerarMensalidadeAjusteMigracao && $mensalidadeFoiJogadaParaFrente) {
                $jaExisteMensalidadeNoVencimentoSolicitado = AssinaturaPlanoPagamento::query()
                    ->where('id_assinatura_plano', $assinatura->id)
                    ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
                    ->whereDate('data_vencimento', $primeiroVencimentoSolicitado)
                    ->whereNotIn('status', [AssinaturaPlanoPagamento::STATUS_CANCELADO])
                    ->exists();

                if (!$jaExisteMensalidadeNoVencimentoSolicitado) {
                    $mensalidadeAjusteGapPagamento = $this->criarPagamentoAsaas(
                        assinatura: $assinatura,
                        empresa: $empresa,
                        tipoCobranca: AssinaturaPlanoPagamento::TIPO_MENSALIDADE,
                        billingType: $metodoMensal,
                        valor: $valorMensal,
                        dataVencimento: $primeiroVencimentoSolicitado,
                        descricao: 'Mensalidade de ajuste da troca de plano ' . $plano->nome,
                        competencia: Carbon::parse($primeiroVencimentoSolicitado)->startOfMonth()
                    );

                    Log::warning('Primeiro vencimento da subscription foi postergado pelo Asaas; gerada mensalidade de ajuste para evitar mês sem cobrança.', [
                        'id_empresa' => $empresa->id_empresa,
                        'id_assinatura' => $assinatura->id,
                        'vencimento_solicitado' => $primeiroVencimentoSolicitado,
                        'vencimento_retornado' => $proximaCobranca,
                        'pagamento_ajuste_id' => $mensalidadeAjusteGapPagamento->id,
                    ]);
                }
            }

            $vencimentoMensalRecorrente = $dataPrimeiroPaymentSubscription ?: $proximaCobranca;

            $mensalPagamento = $this->registrarMensalidadeRecorrenteInicial(
                assinatura: $assinatura,
                empresa: $empresa,
                billingType: $metodoMensal,
                valor: $valorMensal,
                dataVencimento: $vencimentoMensalRecorrente
            );

            // Buscar o primeiro payment gerado pelo Asaas para ter URLs de pagamento
            $this->sincronizarPrimeiroPaymentSubscription(
                $mensalAssinatura['id'],
                $mensalPagamento,
                $primeiroPaymentSubscription
            );

            $assinatura->proxima_cobranca_em = $proximaCobranca;
            $assinatura->save();

            // Ativa imediatamente quando não há adesão financeira ou quando ela já retorna paga no ato.
            $adesaoConfirmadaNoAto = $adesaoPagamento
                && $adesaoPagamento->status === AssinaturaPlanoPagamento::STATUS_PAGO;

            if ($valorAdesao <= 0 || $adesaoConfirmadaNoAto) {
                $this->ativarAssinaturaAposPagamentoAdesao(
                    $assinatura,
                    $empresa,
                    $plano,
                    $valorMensal,
                    $valorAdesaoProvisionamento
                );
            }

            // Monta mensagem final
            $houveFalhaCartao = $erroCartaoAdesao || $erroCartaoMensal;
            $mensagemFinal = 'Cobrança de adesão e assinatura mensal recorrente geradas com sucesso.';
            if ($houveFalhaCartao) {
                $mensagemFinal = 'O cartão foi recusado. As cobranças foram geradas para pagamento manual via link. Acesse "Meu Financeiro" para efetuar o pagamento.';
            }

            return [
                'assinatura' => $assinatura->fresh(),
                'plano_contratado' => $assinatura->planoContratado,
                'adesao_pagamento' => $adesaoPagamento,
                'mensal_pagamento' => $mensalPagamento,
                'mensalidade_ponte_pagamento' => $mensalidadePontePagamento,
                'mensalidade_ajuste_gap_pagamento' => $mensalidadeAjusteGapPagamento,
                'mensal_assinatura' => $mensalAssinatura,
                'promocao_aplicada' => $promocaoAplicada,
                'mensagem' => $mensagemFinal,
                'erro_cartao' => $erroCartaoAdesao ?: $erroCartaoMensal,
                'valor_adesao_provisionamento' => round($valorAdesaoProvisionamento, 2),
            ];
        });
    }

    /**
     * Executa troca de plano (upgrade ou downgrade) com regras de adesao e periodo.
     */
    public function realizarUpgradePlano(Empresa $empresa, Plano $novoPlano, array $opcoes = []): array
    {
        $assinaturaAtual = $this->obterAssinaturaEmpresa((int) $empresa->id_empresa);

        if (!$assinaturaAtual) {
            throw new Exception('Nenhuma assinatura ativa encontrada para realizar troca de plano.');
        }

        if ((int) $assinaturaAtual->id_plano === (int) $novoPlano->id_plano) {
            throw new Exception('A empresa ja esta no plano selecionado.');
        }

        $metodoAdesao = $this->asaasGateway->normalizeBillingType(
            (string) ($opcoes['metodo_adesao'] ?? $assinaturaAtual->metodo_adesao ?? AssinaturaPlano::METODO_PIX)
        );
        $metodoMensal = $this->asaasGateway->normalizeBillingType(
            (string) ($opcoes['metodo_mensal'] ?? $assinaturaAtual->metodo_mensal ?? AssinaturaPlano::METODO_BOLETO)
        );

        if ($metodoMensal === AssinaturaPlano::METODO_PIX) {
            throw new Exception('PIX está disponível apenas para pagamento de adesão.');
        }

        $precosPromocionais = $this->planoPromocaoService->calcularValoresPromocionais($novoPlano, $empresa);

        $valorMensalNovo = array_key_exists('valor_mensal', $opcoes)
            && $opcoes['valor_mensal'] !== null
            && $opcoes['valor_mensal'] !== ''
                ? (float) $opcoes['valor_mensal']
                : (float) ($precosPromocionais['valor_mensal_final'] ?? $novoPlano->valor);

        $valorAdesaoPlanoNovo = array_key_exists('valor_adesao_plano', $opcoes)
            && $opcoes['valor_adesao_plano'] !== null
            && $opcoes['valor_adesao_plano'] !== ''
                ? (float) $opcoes['valor_adesao_plano']
                : (float) ($precosPromocionais['valor_adesao_final'] ?? $novoPlano->adesao);

        $valorMensalAtual = (float) (
            $assinaturaAtual->planoContratado?->valor
            ?? $assinaturaAtual->plano?->valor
            ?? 0
        );

        $valorAdesaoAtualPaga = $this->obterValorAdesaoPagoAtual($assinaturaAtual);
        $isDowngrade = round($valorMensalNovo, 2) < round($valorMensalAtual, 2);

        $valorAdesaoCobrar = $isDowngrade
            ? 0.0
            : max(0.0, round($valorAdesaoPlanoNovo - $valorAdesaoAtualPaga, 2));

        $estrategiaMensalidade = $this->calcularEstrategiaTrocaMensalidade($assinaturaAtual, $valorMensalAtual);

        $payloadUpgrade = [
            'origem' => $opcoes['origem'] ?? AssinaturaPlano::ORIGEM_DASHBOARD,
            'metodo_adesao' => $metodoAdesao,
            'metodo_mensal' => $metodoMensal,
            'valor_adesao' => $valorAdesaoCobrar,
            'valor_adesao_provisionamento' => $valorAdesaoPlanoNovo,
            'substituir_adesao_pendente' => true,
            'gerar_cobrancas' => $opcoes['gerar_cobrancas'] ?? true,
            'observacoes' => $opcoes['observacoes'] ?? sprintf(
                'Troca de plano: %s -> %s em %s',
                (string) ($assinaturaAtual->plano?->nome ?? 'Plano anterior'),
                (string) $novoPlano->nome,
                now()->format('d/m/Y H:i')
            ),
            'card_data' => $opcoes['card_data'] ?? null,
            'primeira_mensalidade_vencimento' => (string) ($estrategiaMensalidade['primeira_mensalidade_vencimento'] ?? ''),
            'cancelar_mensalidades_desde' => (string) ($estrategiaMensalidade['cancelar_mensalidades_desde'] ?? ''),
            'mensalidade_ponte' => $estrategiaMensalidade['mensalidade_ponte'] ?? null,
            'gerar_mensalidade_ajuste_migracao' => true,
        ];

        if (!$isDowngrade && $valorAdesaoCobrar > 0) {
            $payloadUpgrade['descricao_adesao'] = 'Adesão - Upgrade Plano ' . (string) $novoPlano->nome;
            $payloadUpgrade['adesao_upgrade_plano_nome'] = (string) $novoPlano->nome;
        }

        if (array_key_exists('valor_mensal', $opcoes) && $opcoes['valor_mensal'] !== null && $opcoes['valor_mensal'] !== '') {
            $payloadUpgrade['valor_mensal'] = $opcoes['valor_mensal'];
        }

        $resultado = $this->iniciarAssinatura($empresa, $novoPlano, $payloadUpgrade);

        $mensagem = $isDowngrade
            ? 'Plano alterado com sucesso. Downgrade aplicado sem cobranca e sem devolucao de adesao.'
            : 'Plano alterado com sucesso.';

        if ($valorAdesaoCobrar > 0) {
            $mensagem .= ' Valor de adesao da troca: R$ ' . number_format($valorAdesaoCobrar, 2, ',', '.') . '.';
        } elseif (!$isDowngrade) {
            $mensagem .= ' Nao houve cobranca adicional de adesao.';
        }

        if (($estrategiaMensalidade['codigo'] ?? '') === 'cancelar_proxima_e_iniciar_novo_valor') {
            $mensagem .= ' A proxima mensalidade foi substituida pelo novo valor.';
        }

        if (($estrategiaMensalidade['codigo'] ?? '') === 'manter_proxima_antiga_e_iniciar_apos_proxima') {
            $mensagem .= ' A proxima mensalidade foi mantida no valor atual e o novo valor passa a valer 30 dias depois.';
        }

        $resultado['tipo_troca'] = $isDowngrade ? 'downgrade' : 'upgrade';
        $resultado['valor_mensal_atual'] = round($valorMensalAtual, 2);
        $resultado['valor_mensal_novo'] = round($valorMensalNovo, 2);
        $resultado['valor_adesao_plano_novo'] = round($valorAdesaoPlanoNovo, 2);
        $resultado['valor_adesao_atual_paga'] = round($valorAdesaoAtualPaga, 2);
        $resultado['valor_adesao_cobranca'] = round($valorAdesaoCobrar, 2);
        $resultado['estrategia_mensalidade'] = $estrategiaMensalidade;
        $resultado['mensagem'] = $mensagem;

        return $resultado;
    }

    private function obterValorAdesaoPagoAtual(AssinaturaPlano $assinatura): float
    {
        $valorAdesaoPago = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_ADESAO)
            ->where('status', AssinaturaPlanoPagamento::STATUS_PAGO)
            ->orderByDesc('id')
            ->value('valor');

        if (!is_null($valorAdesaoPago)) {
            return round((float) $valorAdesaoPago, 2);
        }

        return round((float) (
            $assinatura->planoContratado?->adesao
            ?? $assinatura->plano?->adesao
            ?? 0
        ), 2);
    }

    private function calcularEstrategiaTrocaMensalidade(AssinaturaPlano $assinaturaAtual, float $valorMensalAtual): array
    {
        $hoje = now()->startOfDay();
        $ultimaMensalidadePaga = $this->obterDataUltimaMensalidadePaga($assinaturaAtual);
        $proximaMensalidade = $this->obterDataProximaMensalidade($assinaturaAtual, $ultimaMensalidadePaga);

        if (!$ultimaMensalidadePaga) {
            $ultimaMensalidadePaga = $proximaMensalidade->copy()->subDays(30);
        }

        $distanciaUltima = abs($hoje->diffInDays($ultimaMensalidadePaga, false));
        $distanciaProxima = abs($hoje->diffInDays($proximaMensalidade, false));

        if ($distanciaUltima < $distanciaProxima) {
            return [
                'codigo' => 'cancelar_proxima_e_iniciar_novo_valor',
                'ultima_mensalidade_paga' => $ultimaMensalidadePaga->toDateString(),
                'proxima_mensalidade' => $proximaMensalidade->toDateString(),
                'cancelar_mensalidades_desde' => $proximaMensalidade->toDateString(),
                'primeira_mensalidade_vencimento' => $proximaMensalidade->toDateString(),
                'mensalidade_ponte' => null,
            ];
        }

        return [
            'codigo' => 'manter_proxima_antiga_e_iniciar_apos_proxima',
            'ultima_mensalidade_paga' => $ultimaMensalidadePaga->toDateString(),
            'proxima_mensalidade' => $proximaMensalidade->toDateString(),
            'cancelar_mensalidades_desde' => $proximaMensalidade->toDateString(),
            'primeira_mensalidade_vencimento' => $proximaMensalidade->copy()->addDays(30)->toDateString(),
            'mensalidade_ponte' => [
                'valor' => round($valorMensalAtual, 2),
                'metodo_pagamento' => (string) $assinaturaAtual->metodo_mensal,
                'data_vencimento' => $proximaMensalidade->toDateString(),
                'descricao' => 'Mensalidade de transicao mantendo valor do plano anterior.',
            ],
        ];
    }

    private function obterDataUltimaMensalidadePaga(AssinaturaPlano $assinatura): ?Carbon
    {
        $dataPagamento = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->where('status', AssinaturaPlanoPagamento::STATUS_PAGO)
            ->whereNotNull('data_pagamento')
            ->orderByDesc('data_pagamento')
            ->value('data_pagamento');

        if ($dataPagamento) {
            return Carbon::parse((string) $dataPagamento)->startOfDay();
        }

        $dataVencimento = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->where('status', AssinaturaPlanoPagamento::STATUS_PAGO)
            ->whereNotNull('data_vencimento')
            ->orderByDesc('data_vencimento')
            ->value('data_vencimento');

        if ($dataVencimento) {
            return Carbon::parse((string) $dataVencimento)->startOfDay();
        }

        if ($assinatura->ultimo_pagamento_em) {
            return Carbon::parse((string) $assinatura->ultimo_pagamento_em)->startOfDay();
        }

        return null;
    }

    private function obterDataProximaMensalidade(AssinaturaPlano $assinatura, ?Carbon $ultimaMensalidadePaga = null): Carbon
    {
        $proximaPagamentoAberto = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereNotIn('status', [
                AssinaturaPlanoPagamento::STATUS_PAGO,
                AssinaturaPlanoPagamento::STATUS_CANCELADO,
            ])
            ->whereNotNull('data_vencimento')
            ->orderBy('data_vencimento')
            ->value('data_vencimento');

        if ($proximaPagamentoAberto) {
            return Carbon::parse((string) $proximaPagamentoAberto)->startOfDay();
        }

        if ($assinatura->proxima_cobranca_em) {
            return Carbon::parse((string) $assinatura->proxima_cobranca_em)->startOfDay();
        }

        if ($ultimaMensalidadePaga) {
            return $ultimaMensalidadePaga->copy()->addDays(30)->startOfDay();
        }

        return now()->startOfDay()->addDays(30);
    }

    /**
     * Solicita cancelamento ao fim do periodo pago (mantem acesso ate a data cobrada).
     */
    public function solicitarCancelamentoFimDoPeriodo(Empresa $empresa, ?string $motivo = null): array
    {
        if (!$this->tabelasBillingDisponiveis()) {
            throw new Exception('Estrutura de assinaturas nao instalada.');
        }

        if (!$this->colunasCancelamentoDisponiveis()) {
            throw new Exception('Banco desatualizado: execute as migrations de cancelamento para habilitar esta opcao.');
        }

        $assinaturaAtual = $this->obterAssinaturaEmpresa((int) $empresa->id_empresa);

        if (!$assinaturaAtual) {
            throw new Exception('Nenhuma assinatura encontrada para esta filial.');
        }

        if ($assinaturaAtual->status === AssinaturaPlano::STATUS_CANCELADA) {
            throw new Exception('Esta assinatura ja esta cancelada.');
        }

        if ($assinaturaAtual->status === AssinaturaPlano::STATUS_CANCELAMENTO_AGENDADO) {
            return [
                'assinatura' => $assinaturaAtual,
                'cancelamento_efetivo_em' => $assinaturaAtual->cancelamento_efetivo_em,
                'mensagem' => 'Cancelamento ja esta agendado para o fim do periodo pago.',
            ];
        }

        $dataEfetiva = $this->calcularDataEfetivaCancelamento($assinaturaAtual);

        DB::transaction(function () use ($assinaturaAtual, $dataEfetiva, $motivo) {
            $assinatura = AssinaturaPlano::query()->lockForUpdate()->find($assinaturaAtual->id);

            if (!$assinatura) {
                throw new Exception('Assinatura nao encontrada para agendar cancelamento.');
            }

            $subscriptionId = $this->obterSubscriptionId($assinatura);
            if ($subscriptionId) {
                $this->cancelarAssinaturaRecorrenteAsaas($subscriptionId);
                $this->definirSubscriptionId($assinatura, null);
            }

            // Remove cobrancas futuras apos o ultimo dia de vigencia.
            $this->cancelarPagamentosFuturos($assinatura, $dataEfetiva->copy()->addDay());

            $assinatura->status = AssinaturaPlano::STATUS_CANCELAMENTO_AGENDADO;

            if ($this->colunasCancelamentoDisponiveis()) {
                $assinatura->motivo_cancelamento = $motivo ? mb_substr($motivo, 0, 255) : null;
            }

            $assinatura->observacoes = $this->anexarObservacao(
                (string) $assinatura->observacoes,
                'Cancelamento solicitado em ' . now()->format('d/m/Y H:i')
                    . '. Vigencia ate ' . $dataEfetiva->format('d/m/Y') . '.'
            );

            $this->definirAgendamentoCancelamento($assinatura, $dataEfetiva);
            $assinatura->save();
        });

        return [
            'assinatura' => $assinaturaAtual->fresh(),
            'cancelamento_efetivo_em' => $dataEfetiva->toDateString(),
            'mensagem' => 'Cancelamento agendado. O sistema permanecera ativo ate a data ja cobrada.',
        ];
    }

    /**
     * Finaliza cancelamentos cujo periodo pago ja terminou.
     */
    public function processarCancelamentosAgendados(): array
    {
        if (!$this->tabelasBillingDisponiveis() || !$this->colunasCancelamentoDisponiveis()) {
            return [
                'cancelamentos_finalizados' => 0,
            ];
        }

        $finalizados = 0;

        $assinaturas = AssinaturaPlano::query()
            ->where('status', AssinaturaPlano::STATUS_CANCELAMENTO_AGENDADO)
            ->whereNotNull('cancelamento_efetivo_em')
            ->whereDate('cancelamento_efetivo_em', '<', now()->toDateString())
            ->get();

        foreach ($assinaturas as $assinatura) {
            $this->finalizarCancelamentoAssinatura($assinatura);
            $finalizados++;
        }

        return [
            'cancelamentos_finalizados' => $finalizados,
        ];
    }

    /**
     * Processa webhook do Asaas para cobranças de assinatura.
     */
    public function processarWebhookAsaas(array $payload): void
    {
        if (!$this->tabelasBillingDisponiveis()) {
            Log::warning('Webhook de assinatura ignorado: tabelas de billing não instaladas.');
            return;
        }

        $evento = (string) ($payload['event'] ?? '');
        $payment = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];
        $paymentId = (string) ($payment['id'] ?? '');

        // Alguns webhooks de assinatura não trazem payment.id.
        // Ainda assim sincronizamos o metodo mensal com base no billingType do payload.
        $this->sincronizarMetadadosCartaoWebhookAsaas($payload, $payment);

        if ($paymentId === '') {
            if ($evento !== '') {
                Log::info('Webhook Asaas de assinatura sem payment_id: metodo mensal sincronizado quando disponivel.', [
                    'evento' => $evento,
                    'subscription_id' => $this->extrairSubscriptionIdWebhookAsaas($payload, $payment),
                ]);
                return;
            }

            Log::warning('Webhook Asaas de assinatura inválido', ['payload' => $payload]);
            return;
        }

        if ($evento === '') {
            Log::warning('Webhook Asaas de assinatura inválido', ['payload' => $payload]);
            return;
        }

        $pagamento = AssinaturaPlanoPagamento::query()
            ->where('asaas_payment_id', $paymentId)
            ->latest('id')
            ->first();

        if (!$pagamento) {
            $externalReference = (string) ($payment['externalReference'] ?? '');
            $pagamento = $this->buscarPagamentoPorReferenciaExterna($externalReference, $payment);
        }

        if (!$pagamento) {
            $subscriptionId = trim((string) ($payment['subscription'] ?? ''));

            if ($subscriptionId !== '' && $this->colunaSubscriptionDisponivel()) {
                $assinatura = AssinaturaPlano::query()
                    ->where('asaas_subscription_id', $subscriptionId)
                    ->latest('id')
                    ->first();

                if ($assinatura) {
                    $pagamento = $this->buscarPagamentoPorReferenciaExterna(
                        $this->montarExternalReferenceRecorrencia($assinatura),
                        $payment
                    );
                }
            }
        }

        if (!$pagamento) {
            Log::warning('Pagamento de assinatura não encontrado para webhook Asaas', [
                'evento' => $evento,
                'payment_id' => $paymentId,
                'external_reference' => $payment['externalReference'] ?? null,
            ]);
            return;
        }

        $statusMapeado = $this->mapearStatusAsaas($payment['status'] ?? null);

        $pagamento->json_webhook = json_encode($payload);
        $pagamento->status = $statusMapeado;

        if ($statusMapeado === AssinaturaPlanoPagamento::STATUS_PAGO) {
            $pagamento->data_pagamento = $this->parseAsaasDateTime(
                $payment['paymentDate'] ?? $payment['confirmedDate'] ?? null
            );
        }

        $pagamento->save();

        if (in_array($evento, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_RECEIVED_IN_CASH'], true)) {
            $this->registrarPagamentoConfirmado($pagamento, $payment);
            return;
        }

        if ($evento === 'PAYMENT_OVERDUE') {
            $this->registrarPagamentoVencido($pagamento);
            return;
        }

        if (in_array($evento, ['PAYMENT_DELETED', 'PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED'], true)) {
            $pagamento->status = AssinaturaPlanoPagamento::STATUS_CANCELADO;
            $pagamento->save();
        }
    }

    /**
     * Gera mensalidades pendentes (principalmente para boleto mensal).
     */
    public function gerarMensalidadesPendentes(): array
    {
        if (!$this->tabelasBillingDisponiveis()) {
            return [
                'assinaturas_analisadas' => 0,
                'mensalidades_geradas' => 0,
            ];
        }

        $totalAnalisadas = 0;
        $totalGeradas = 0;

        $assinaturas = AssinaturaPlano::query()
            ->whereIn('status', [
                AssinaturaPlano::STATUS_ATIVA,
                AssinaturaPlano::STATUS_ONBOARDING_DADOS,
                AssinaturaPlano::STATUS_ONBOARDING_CONTRATO,
                AssinaturaPlano::STATUS_SUSPENSA,
            ])
            ->whereDate('proxima_cobranca_em', '<=', now()->toDateString())
            ->with(['empresa', 'plano', 'planoContratado'])
            ->get();

        foreach ($assinaturas as $assinatura) {
            $totalAnalisadas++;

            try {
                $empresa = $assinatura->empresa;
                $plano = $assinatura->plano;

                if (!$empresa || !$plano) {
                    continue;
                }

                if ($this->obterSubscriptionId($assinatura)) {
                    continue;
                }

                if ($assinatura->metodo_mensal === AssinaturaPlano::METODO_PIX) {
                    // Regra de negócio: PIX apenas para adesão.
                    continue;
                }

                $competencia = Carbon::parse((string) $assinatura->proxima_cobranca_em)->startOfMonth();

                $jaExiste = AssinaturaPlanoPagamento::query()
                    ->where('id_assinatura_plano', $assinatura->id)
                    ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
                    ->whereDate('competencia', $competencia->toDateString())
                    ->whereNotIn('status', [AssinaturaPlanoPagamento::STATUS_CANCELADO])
                    ->exists();

                if ($jaExiste) {
                    $assinatura->proxima_cobranca_em = $competencia->copy()->addMonthNoOverflow()->toDateString();
                    $assinatura->save();
                    continue;
                }

                $valorMensal = (float) ($assinatura->planoContratado->valor ?? $plano->valor ?? 0);
                if ($valorMensal <= 0) {
                    continue;
                }

                $this->criarPagamentoAsaas(
                    assinatura: $assinatura,
                    empresa: $empresa,
                    tipoCobranca: AssinaturaPlanoPagamento::TIPO_MENSALIDADE,
                    billingType: (string) $assinatura->metodo_mensal,
                    valor: $valorMensal,
                    dataVencimento: Carbon::parse((string) $assinatura->proxima_cobranca_em)->toDateString(),
                    descricao: 'Mensalidade do plano ' . $plano->nome,
                    competencia: $competencia
                );

                $assinatura->proxima_cobranca_em = $competencia->copy()->addMonthNoOverflow()->toDateString();
                $assinatura->save();

                $totalGeradas++;
            } catch (Exception $e) {
                Log::error('Erro ao gerar mensalidade de assinatura', [
                    'assinatura_id' => $assinatura->id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return [
            'assinaturas_analisadas' => $totalAnalisadas,
            'mensalidades_geradas' => $totalGeradas,
        ];
    }

    /**
     * Bloqueia empresas com mensalidade vencida há X dias e desbloqueia quando regularizar.
     */
    public function processarInadimplencia(int $diasAtraso = 5): array
    {
        if (!$this->tabelasBillingDisponiveis()) {
            return [
                'empresas_bloqueadas' => 0,
                'empresas_desbloqueadas' => 0,
                'cancelamentos_finalizados' => 0,
                'dias_atraso' => $diasAtraso,
            ];
        }

        $resultadoCancelamentos = $this->processarCancelamentosAgendados();

        $bloqueadas = 0;
        $desbloqueadas = 0;

        $limite = now()->subDays($diasAtraso)->toDateString();

        $empresasInadimplentes = AssinaturaPlanoPagamento::query()
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereIn('status', [
                AssinaturaPlanoPagamento::STATUS_GERADO,
                AssinaturaPlanoPagamento::STATUS_PENDENTE,
                AssinaturaPlanoPagamento::STATUS_VENCIDO,
                AssinaturaPlanoPagamento::STATUS_FALHOU,
            ])
            ->whereDate('data_vencimento', '<=', $limite)
            ->groupBy('id_empresa')
            ->pluck('id_empresa')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (!empty($empresasInadimplentes)) {
            $assinaturasParaBloquear = AssinaturaPlano::query()
                ->whereIn('id_empresa', $empresasInadimplentes)
                ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
                ->get()
                ->groupBy('id_empresa')
                ->map(fn ($itens) => $itens->sortByDesc('id')->first())
                ->values();

            foreach ($assinaturasParaBloquear as $assinatura) {
                $empresa = $assinatura->empresa;
                if (!$empresa) {
                    continue;
                }

                if ($empresa->status === 'ativo') {
                    $empresa->update([
                        'status' => 'bloqueado',
                        'data_bloqueio' => now(),
                    ]);
                    $bloqueadas++;
                }

                $primeiraInadimplencia = AssinaturaPlanoPagamento::query()
                    ->where('id_empresa', $empresa->id_empresa)
                    ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
                    ->whereIn('status', [
                        AssinaturaPlanoPagamento::STATUS_GERADO,
                        AssinaturaPlanoPagamento::STATUS_PENDENTE,
                        AssinaturaPlanoPagamento::STATUS_VENCIDO,
                        AssinaturaPlanoPagamento::STATUS_FALHOU,
                    ])
                    ->orderBy('data_vencimento')
                    ->value('data_vencimento');

                $assinatura->update([
                    'status' => AssinaturaPlano::STATUS_SUSPENSA,
                    'bloqueada_por_inadimplencia' => 1,
                    'inadimplente_desde' => $primeiraInadimplencia,
                ]);
            }
        }

        $assinaturasBloqueadas = AssinaturaPlano::query()
            ->where('bloqueada_por_inadimplencia', 1)
            ->get();

        foreach ($assinaturasBloqueadas as $assinatura) {
            $empresa = $assinatura->empresa;
            if (!$empresa) {
                continue;
            }

            $temPendencia = AssinaturaPlanoPagamento::query()
                ->where('id_assinatura_plano', $assinatura->id)
                ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
                ->whereIn('status', [
                    AssinaturaPlanoPagamento::STATUS_GERADO,
                    AssinaturaPlanoPagamento::STATUS_PENDENTE,
                    AssinaturaPlanoPagamento::STATUS_VENCIDO,
                    AssinaturaPlanoPagamento::STATUS_FALHOU,
                ])
                ->whereDate('data_vencimento', '<=', $limite)
                ->exists();

            if ($temPendencia) {
                continue;
            }

            if ($empresa->status === 'bloqueado') {
                $empresa->update([
                    'status' => 'ativo',
                    'data_bloqueio' => null,
                ]);
                $desbloqueadas++;
            }

            $assinatura->update([
                'status' => $this->resolverStatusOnboarding($empresa),
                'bloqueada_por_inadimplencia' => 0,
                'inadimplente_desde' => null,
            ]);
        }

        return [
            'empresas_bloqueadas' => $bloqueadas,
            'empresas_desbloqueadas' => $desbloqueadas,
            'cancelamentos_finalizados' => (int) ($resultadoCancelamentos['cancelamentos_finalizados'] ?? 0),
            'dias_atraso' => $diasAtraso,
        ];
    }

    public function obterResumoFinanceiroEmpresa(int $idEmpresa): array
    {
        if (!$this->tabelasBillingDisponiveis()) {
            return [
                'assinatura' => null,
                'pagamentos_abertos' => collect(),
                'historico_pagamentos' => collect(),
            ];
        }

        $assinatura = AssinaturaPlano::query()
            ->with(['plano', 'planoContratado'])
            ->where('id_empresa', $idEmpresa)
            ->latest('id')
            ->first();

        $abertos = collect();
        $historico = collect();

        if ($assinatura) {
            $assinatura = $this->garantirPlanoContratadoSincronizado($assinatura);

            $abertos = $assinatura->pagamentos()
                ->whereIn('status', [
                    AssinaturaPlanoPagamento::STATUS_GERADO,
                    AssinaturaPlanoPagamento::STATUS_PENDENTE,
                    AssinaturaPlanoPagamento::STATUS_VENCIDO,
                    AssinaturaPlanoPagamento::STATUS_FALHOU,
                ])
                ->orderBy('data_vencimento')
                ->get();

            $historico = $assinatura->pagamentos()
                ->whereIn('status', [
                    AssinaturaPlanoPagamento::STATUS_PAGO,
                    AssinaturaPlanoPagamento::STATUS_CANCELADO,
                ])
                ->orderByDesc('id')
                ->limit(20)
                ->get();
        }

        return [
            'assinatura' => $assinatura,
            'pagamentos_abertos' => $abertos,
            'historico_pagamentos' => $historico,
        ];
    }

    private function garantirPlanoContratadoSincronizado(AssinaturaPlano $assinatura): AssinaturaPlano
    {
        $assinatura->loadMissing(['empresa', 'plano', 'planoContratado']);

        if ($assinatura->status === AssinaturaPlano::STATUS_CANCELADA || !$assinatura->empresa || !$assinatura->plano) {
            return $assinatura;
        }

        $pagamentoAdesaoPagoAtual = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('id_plano', (int) $assinatura->id_plano)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_ADESAO)
            ->where('status', AssinaturaPlanoPagamento::STATUS_PAGO)
            ->orderByDesc('id')
            ->first();

        if (!$pagamentoAdesaoPagoAtual) {
            return $assinatura;
        }

        $valorMensalProvisionamento = $this->obterValorMensalProvisionamentoAtual($assinatura);
        $valorAdesaoProvisionamento = $this->resolverValorAdesaoProvisionamento($pagamentoAdesaoPagoAtual);

        if (!$this->deveProvisionarPlanoContratado(
            $assinatura->planoContratado,
            $assinatura->plano,
            $valorMensalProvisionamento,
            $valorAdesaoProvisionamento
        )) {
            return $assinatura;
        }

        $contratoAnteriorId = $assinatura->id_plano_contratado;
        $contratoAnteriorNome = $assinatura->planoContratado?->nome;

        $this->ativarAssinaturaAposPagamentoAdesao(
            $assinatura,
            $assinatura->empresa,
            $assinatura->plano,
            $valorMensalProvisionamento,
            $valorAdesaoProvisionamento
        );

        Log::warning('Plano contratado sincronizado automaticamente ao detectar divergencia com a assinatura.', [
            'id_empresa' => $assinatura->id_empresa,
            'id_assinatura' => $assinatura->id,
            'id_plano' => $assinatura->id_plano,
            'contrato_anterior_id' => $contratoAnteriorId,
            'contrato_anterior_nome' => $contratoAnteriorNome,
            'contrato_novo_id' => $assinatura->id_plano_contratado,
            'plano_assinatura_nome' => $assinatura->plano->nome,
        ]);

        return AssinaturaPlano::query()
            ->with(['plano', 'planoContratado'])
            ->find($assinatura->id)
            ?? $assinatura->fresh(['plano', 'planoContratado']);
    }

    private function obterValorMensalProvisionamentoAtual(AssinaturaPlano $assinatura): float
    {
        $valorMensal = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('id_plano', (int) $assinatura->id_plano)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereNotIn('status', [AssinaturaPlanoPagamento::STATUS_CANCELADO])
            ->orderByDesc('id')
            ->value('valor');

        if (!is_null($valorMensal)) {
            return round((float) $valorMensal, 2);
        }

        return round((float) ($assinatura->plano?->valor ?? 0), 2);
    }

    /**
     * Retorna informações de cartão associado à assinatura recorrente no Asaas.
     */
    public function obterInfoCartaoDebito(?AssinaturaPlano $assinatura): array
    {
        $info = [
            'confirmado' => false,
            'final' => null,
            'brand' => null,
            'subscription_id' => null,
            'source' => null,
            'error' => null,
        ];

        if (!$assinatura) {
            return $info;
        }

        $subscriptionId = $this->obterSubscriptionId($assinatura);
        if (!$subscriptionId) {
            return $info;
        }

        $info['subscription_id'] = $subscriptionId;

        if (!$this->asaasGateway->isConfigured()) {
            $info['error'] = 'Integracao Asaas nao configurada.';
            return $info;
        }

        try {
            $subscription = $this->asaasGateway->getSubscription($subscriptionId);

            $finalCartao = $this->extrairUltimos4CartaoAssinatura($subscription);
            if ($finalCartao !== null) {
                $info['final'] = $finalCartao;
            }

            $bandeira = $this->extrairBandeiraCartaoAssinatura($subscription);
            if ($bandeira !== null) {
                $info['brand'] = $bandeira;
            }

            $info['source'] = 'asaas';
        } catch (Exception $e) {
            Log::warning('Não foi possível consultar assinatura no Asaas para obter dados do cartão.', [
                'id_assinatura' => $assinatura->id,
                'subscription_id' => $subscriptionId,
                'erro' => $e->getMessage(),
            ]);

            $info['error'] = $e->getMessage();
        }

        $info['confirmado'] = !empty($info['final']) || !empty($info['brand']);

        return $info;
    }

    public function atualizarMetodoMensal(Empresa $empresa, string $metodoMensal): AssinaturaPlano
    {
        if (!$this->tabelasBillingDisponiveis()) {
            throw new Exception('Estrutura de assinaturas não instalada.');
        }

        $billingType = $this->asaasGateway->normalizeBillingType($metodoMensal);

        if ($billingType === AssinaturaPlano::METODO_PIX) {
            throw new Exception('PIX não é permitido para mensalidade.');
        }

        $assinatura = AssinaturaPlano::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
            ->latest('id')
            ->first();

        if (!$assinatura) {
            throw new Exception('Assinatura não encontrada para a empresa.');
        }

        $assinatura->metodo_mensal = $billingType;
        $assinatura->save();

        $subscriptionId = $this->obterSubscriptionId($assinatura);
        if ($subscriptionId) {
            $this->asaasGateway->updateSubscription($subscriptionId, [
                'billingType' => $billingType,
            ]);
        }

        return $assinatura;
    }

    /**
     * Antecipa a próxima cobrança da assinatura recorrente para uma data específica (padrão: hoje).
     */
    public function anteciparProximaCobrancaAsaas(Empresa $empresa, ?string $dataVencimento = null): array
    {
        if (!$this->tabelasBillingDisponiveis()) {
            throw new Exception('Estrutura de assinaturas não instalada.');
        }

        $assinatura = AssinaturaPlano::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
            ->latest('id')
            ->first();

        if (!$assinatura) {
            throw new Exception('Assinatura não encontrada para a empresa.');
        }

        $subscriptionId = $this->obterSubscriptionId($assinatura);
        if (!$subscriptionId) {
            throw new Exception('Assinatura recorrente no Asaas não encontrada.');
        }

        $dueDate = $dataVencimento
            ? Carbon::parse($dataVencimento)->toDateString()
            : now()->toDateString();

        $subscriptionAtualizada = $this->asaasGateway->updateSubscription($subscriptionId, [
            'nextDueDate' => $dueDate,
        ]);

        $payments = $this->asaasGateway->getSubscriptionPayments($subscriptionId);

        $paymentSelecionado = collect($payments)
            ->sortBy('dueDate')
            ->first(fn ($payment) => (string) ($payment['dueDate'] ?? '') === $dueDate);

        if (!$paymentSelecionado) {
            $paymentSelecionado = collect($payments)
                ->sortBy('dueDate')
                ->first();
        }

        $pagamentoLocal = null;
        if (is_array($paymentSelecionado) && !empty($paymentSelecionado)) {
            $externalReference = (string) (
                $paymentSelecionado['externalReference']
                ?? $this->montarExternalReferenceRecorrencia($assinatura)
            );

            $pagamentoLocal = $this->buscarPagamentoPorReferenciaExterna($externalReference, $paymentSelecionado);
        }

        if (!empty($subscriptionAtualizada['nextDueDate'])) {
            $assinatura->proxima_cobranca_em = Carbon::parse((string) $subscriptionAtualizada['nextDueDate'])->toDateString();
            $assinatura->save();
        }

        return [
            'assinatura_id' => $assinatura->id,
            'subscription_id' => $subscriptionId,
            'next_due_date' => (string) ($subscriptionAtualizada['nextDueDate'] ?? $dueDate),
            'billing_type' => (string) ($subscriptionAtualizada['billingType'] ?? $assinatura->metodo_mensal),
            'payment_id' => (string) ($paymentSelecionado['id'] ?? ''),
            'payment_status' => (string) ($paymentSelecionado['status'] ?? ''),
            'payment_due_date' => (string) ($paymentSelecionado['dueDate'] ?? ''),
            'invoice_url' => (string) ($paymentSelecionado['invoiceUrl'] ?? ''),
            'bank_slip_url' => (string) ($paymentSelecionado['bankSlipUrl'] ?? ''),
            'pagamento_local_id' => $pagamentoLocal?->id,
        ];
    }

    /**
     * Atualiza metodo mensal para cartao de credito com dados do cartão.
     */
    public function atualizarMetodoMensalCartao(Empresa $empresa, array $cardData): AssinaturaPlano
    {
        if (!$this->tabelasBillingDisponiveis()) {
            throw new Exception('Estrutura de assinaturas não instalada.');
        }

        $assinatura = AssinaturaPlano::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
            ->latest('id')
            ->first();

        if (!$assinatura) {
            throw new Exception('Assinatura não encontrada para a empresa.');
        }

        $subscriptionId = $this->obterSubscriptionId($assinatura);
        if (!$subscriptionId) {
            throw new Exception('Assinatura recorrente no Asaas não encontrada. Crie uma nova assinatura.');
        }

        $creditCard = [
            'holderName' => $cardData['holderName'] ?? '',
            'number' => preg_replace('/\D/', '', $cardData['number'] ?? ''),
            'expiryMonth' => $cardData['expiryMonth'] ?? '',
            'expiryYear' => $cardData['expiryYear'] ?? '',
            'ccv' => $cardData['ccv'] ?? '',
        ];
        $creditCardHolderInfo = $this->montarDadosTitularCartao($empresa, $cardData);

        $remoteIp = $this->obterRemoteIpCompra();

        $subscriptionAtualizada = $this->asaasGateway->updateSubscriptionCreditCard(
            $subscriptionId,
            $creditCard,
            $creditCardHolderInfo,
            $remoteIp
        );

        if (($subscriptionAtualizada['billingType'] ?? '') !== AssinaturaPlano::METODO_CREDIT_CARD) {
            $this->asaasGateway->updateSubscription($subscriptionId, [
                'billingType' => AssinaturaPlano::METODO_CREDIT_CARD,
                'updatePendingPayments' => true,
            ]);
        }

        $assinatura->metodo_mensal = AssinaturaPlano::METODO_CREDIT_CARD;
        $assinatura->save();

        return $assinatura;
    }

    /**
     * Altera o método de pagamento da adesão pendente.
     */
    public function alterarMetodoAdesao(Empresa $empresa, string $novoMetodo, ?array $cardData = null): AssinaturaPlanoPagamento
    {
        if (!$this->tabelasBillingDisponiveis()) {
            throw new Exception('Estrutura de assinaturas não instalada.');
        }

        $assinatura = AssinaturaPlano::query()
            ->where('id_empresa', $empresa->id_empresa)
            ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
            ->latest('id')
            ->first();

        if (!$assinatura) {
            throw new Exception('Assinatura não encontrada para a empresa.');
        }

        // Buscar adesão pendente
        $adesaoPendente = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_ADESAO)
            ->whereNotIn('status', [AssinaturaPlanoPagamento::STATUS_PAGO, AssinaturaPlanoPagamento::STATUS_CANCELADO])
            ->first();

        if (!$adesaoPendente) {
            throw new Exception('Nenhuma cobrança de adesão pendente encontrada.');
        }

        $novoMetodo = $this->asaasGateway->normalizeBillingType($novoMetodo);

        // Se for cartão, validar se os dados foram fornecidos
        if ($novoMetodo === AssinaturaPlano::METODO_CREDIT_CARD && !$cardData) {
            throw new Exception('Dados do cartão são obrigatórios para pagamento com cartão de crédito.');
        }

        $observacoesOriginaisAdesao = (string) ($adesaoPendente->observacoes ?? '');
        $nomePlanoAdesaoUpgrade = $this->extrairNomePlanoAdesaoUpgrade($observacoesOriginaisAdesao);

        // Cancelar cobrança atual no Asaas se existir
        if ($adesaoPendente->asaas_payment_id) {
            try {
                $this->asaasGateway->deletePayment($adesaoPendente->asaas_payment_id);
            } catch (Exception $e) {
                Log::warning('Não foi possível cancelar cobrança antiga no Asaas', [
                    'payment_id' => $adesaoPendente->asaas_payment_id,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        // Marcar registro antigo como cancelado
        $adesaoPendente->status = AssinaturaPlanoPagamento::STATUS_CANCELADO;
        $adesaoPendente->observacoes = $this->anexarObservacaoPagamento(
            (string) $adesaoPendente->observacoes,
            'Cancelada para alteração de método em ' . now()->format('d/m/Y H:i')
        );
        $adesaoPendente->save();

        // Criar nova cobrança com o novo método
        $plano = Plano::find($assinatura->id_plano);
        $descricaoAdesao = $nomePlanoAdesaoUpgrade !== null
            ? 'Adesão - Upgrade Plano ' . $nomePlanoAdesaoUpgrade
            : 'Adesão do plano ' . ($plano->nome ?? 'contratado');

        $novoPagamento = $this->criarPagamentoAdesaoComMetodo(
            assinatura: $assinatura,
            empresa: $empresa,
            billingType: $novoMetodo,
            valor: (float) $adesaoPendente->valor,
            descricao: $descricaoAdesao,
            cardData: $cardData,
            observacoes: $this->recomporObservacoesAdesao($observacoesOriginaisAdesao)
        );

        // Atualizar método da adesão na assinatura
        $assinatura->metodo_adesao = $novoMetodo;
        $assinatura->save();

        return $novoPagamento;
    }

    /**
     * Cria pagamento de adesão com suporte a cartão de crédito (cobrança imediata).
     */
    private function criarPagamentoAdesaoComMetodo(
        AssinaturaPlano $assinatura,
        Empresa $empresa,
        string $billingType,
        float $valor,
        string $descricao,
        ?array $cardData = null,
        ?string $observacoes = null
    ): AssinaturaPlanoPagamento {
        if (!$this->asaasGateway->isConfigured()) {
            throw new Exception('Integração Asaas não configurada.');
        }

        $competencia = now()->startOfMonth();

        $payload = [
            'customer' => (string) $assinatura->asaas_customer_id,
            'billingType' => $billingType,
            'value' => round($valor, 2),
            'dueDate' => now()->toDateString(),
            'description' => mb_substr($descricao, 0, 500),
            'externalReference' => $this->montarExternalReference($assinatura, AssinaturaPlanoPagamento::TIPO_ADESAO, $competencia),
        ];

        // Se for cartão de crédito, incluir dados para cobrança imediata
        if ($billingType === AssinaturaPlano::METODO_CREDIT_CARD && $cardData) {
            $payload['creditCard'] = [
                'holderName' => $cardData['holderName'] ?? '',
                'number' => preg_replace('/\D/', '', $cardData['number'] ?? ''),
                'expiryMonth' => $cardData['expiryMonth'] ?? '',
                'expiryYear' => $cardData['expiryYear'] ?? '',
                'ccv' => $cardData['ccv'] ?? '',
            ];

            $payload['creditCardHolderInfo'] = $this->montarDadosTitularCartao($empresa, $cardData);

            // remoteIp é obrigatório para cobrança com cartão de crédito
            $payload['remoteIp'] = $this->obterRemoteIpCompra();
        }

        $asaasPagamento = $this->asaasGateway->createPayment($payload);
        $paymentId = (string) ($asaasPagamento['id'] ?? '');

        if ($paymentId === '') {
            throw new Exception('Asaas não retornou ID da cobrança.');
        }

        $pixQr = null;
        if ($billingType === AssinaturaPlano::METODO_PIX) {
            $pixQr = $this->asaasGateway->getPixQrCode($paymentId);
        }

        return AssinaturaPlanoPagamento::create([
            'id_assinatura_plano' => $assinatura->id,
            'id_empresa' => $empresa->id_empresa,
            'id_plano' => $assinatura->id_plano,
            'id_plano_contratado' => $assinatura->id_plano_contratado,
            'tipo_cobranca' => AssinaturaPlanoPagamento::TIPO_ADESAO,
            'competencia' => $competencia->toDateString(),
            'metodo_pagamento' => $billingType,
            'asaas_payment_id' => $paymentId,
            'asaas_invoice_url' => $asaasPagamento['invoiceUrl'] ?? null,
            'asaas_bank_slip_url' => $asaasPagamento['bankSlipUrl'] ?? null,
            'asaas_pix_qr_code' => $pixQr['encodedImage'] ?? null,
            'asaas_pix_copy_paste' => $pixQr['payload'] ?? null,
            'valor' => round($valor, 2),
            'data_vencimento' => now()->toDateString(),
            'status' => $this->mapearStatusAsaas($asaasPagamento['status'] ?? null),
            'json_resposta' => json_encode($asaasPagamento),
            'tentativas' => 0,
            'observacoes' => $this->normalizarObservacaoPagamento($observacoes),
        ]);
    }

    private function montarObservacaoAdesaoProvisionamento(float $valorProvisionamento, float $valorCobranca): ?string
    {
        if (round($valorProvisionamento, 2) === round($valorCobranca, 2)) {
            return null;
        }

        return self::ADESAO_PROVISIONAMENTO_PREFIX . number_format(max(0, $valorProvisionamento), 2, '.', '');
    }

    private function montarObservacaoAdesaoUpgrade(string $nomePlano): ?string
    {
        $nomePlano = trim($nomePlano);
        if ($nomePlano === '') {
            return null;
        }

        return self::ADESAO_UPGRADE_PREFIX . $nomePlano;
    }

    private function extrairNomePlanoAdesaoUpgrade(string $observacoes): ?string
    {
        $pattern = '/' . preg_quote(self::ADESAO_UPGRADE_PREFIX, '/') . '\\s*([^|]+)/u';

        if (!preg_match($pattern, $observacoes, $matches)) {
            return null;
        }

        $nomePlano = trim((string) ($matches[1] ?? ''));

        return $nomePlano !== '' ? $nomePlano : null;
    }

    private function recomporObservacoesAdesao(string $observacoes): ?string
    {
        $resultado = '';
        $patternProvisionamento = '/' . preg_quote(self::ADESAO_PROVISIONAMENTO_PREFIX, '/') . '([0-9]+(?:\.[0-9]+)?)/';

        if (preg_match($patternProvisionamento, $observacoes, $matches)) {
            $resultado = self::ADESAO_PROVISIONAMENTO_PREFIX . number_format((float) ($matches[1] ?? 0), 2, '.', '');
        }

        $nomePlanoUpgrade = $this->extrairNomePlanoAdesaoUpgrade($observacoes);
        if ($nomePlanoUpgrade !== null) {
            $resultado = $this->anexarObservacao(
                $resultado,
                (string) $this->montarObservacaoAdesaoUpgrade($nomePlanoUpgrade)
            );
        }

        $resultado = trim($resultado);

        return $resultado !== '' ? $resultado : null;
    }

    private function resolverValorAdesaoProvisionamento(AssinaturaPlanoPagamento $pagamento): float
    {
        $observacoes = (string) ($pagamento->observacoes ?? '');
        if ($observacoes !== '') {
            $pattern = '/' . preg_quote(self::ADESAO_PROVISIONAMENTO_PREFIX, '/') . '([0-9]+(?:\.[0-9]+)?)/';

            if (preg_match($pattern, $observacoes, $matches)) {
                return round((float) ($matches[1] ?? 0), 2);
            }
        }

        return round((float) $pagamento->valor, 2);
    }

    public function obterAssinaturaEmpresa(int $idEmpresa): ?AssinaturaPlano
    {
        if (!Schema::hasTable('assinaturas_planos')) {
            return null;
        }

        $assinatura = AssinaturaPlano::query()
            ->where('id_empresa', $idEmpresa)
            ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
            ->latest('id')
            ->first();

        if (
            $assinatura
            && $assinatura->status === AssinaturaPlano::STATUS_CANCELAMENTO_AGENDADO
            && $this->colunasCancelamentoDisponiveis()
            && $assinatura->cancelamento_efetivo_em
            && Carbon::parse((string) $assinatura->cancelamento_efetivo_em)->lt(now()->startOfDay())
        ) {
            $this->finalizarCancelamentoAssinatura($assinatura);

            return AssinaturaPlano::query()
                ->where('id_empresa', $idEmpresa)
                ->where('status', '!=', AssinaturaPlano::STATUS_CANCELADA)
                ->latest('id')
                ->first();
        }

        return $assinatura;
    }

    public function atualizarStatusOnboarding(Empresa $empresa): ?AssinaturaPlano
    {
        $assinatura = $this->obterAssinaturaEmpresa((int) $empresa->id_empresa);

        if (!$assinatura) {
            return null;
        }

        if ($assinatura->status === AssinaturaPlano::STATUS_PENDENTE_PAGAMENTO) {
            return $assinatura;
        }

        if ($assinatura->status === AssinaturaPlano::STATUS_SUSPENSA && $assinatura->bloqueada_por_inadimplencia) {
            return $assinatura;
        }

        if ($assinatura->status === AssinaturaPlano::STATUS_CANCELAMENTO_AGENDADO) {
            return $assinatura;
        }

        $novoStatus = $this->resolverStatusOnboarding($empresa);

        if ($assinatura->status !== $novoStatus) {
            $assinatura->status = $novoStatus;
            $assinatura->save();
        }

        return $assinatura->fresh();
    }

    private function colunaSubscriptionDisponivel(): bool
    {
        return Schema::hasColumn('assinaturas_planos', 'asaas_subscription_id');
    }

    private function obterSubscriptionId(AssinaturaPlano $assinatura): ?string
    {
        if (!$this->colunaSubscriptionDisponivel()) {
            return null;
        }

        $subscriptionId = trim((string) ($assinatura->asaas_subscription_id ?? ''));

        return $subscriptionId !== '' ? $subscriptionId : null;
    }

    private function extrairUltimos4CartaoAssinatura(array $subscription): ?string
    {
        $candidatos = [
            data_get($subscription, 'creditCard.creditCardNumber'),
            data_get($subscription, 'creditCard.number'),
            data_get($subscription, 'creditCard.maskedNumber'),
            data_get($subscription, 'creditCard.displayNumber'),
            data_get($subscription, 'creditCard.last4'),
            data_get($subscription, 'creditCard.lastDigits'),
            data_get($subscription, 'creditCardNumber'),
            data_get($subscription, 'cardNumber'),
            data_get($subscription, 'card.last4'),
            data_get($subscription, 'payment.creditCard.creditCardNumber'),
            data_get($subscription, 'payment.creditCard.number'),
            data_get($subscription, 'payment.creditCard.maskedNumber'),
            data_get($subscription, 'payment.creditCard.last4'),
            data_get($subscription, 'payment.cardNumber'),
            data_get($subscription, 'subscription.creditCard.creditCardNumber'),
            data_get($subscription, 'subscription.creditCard.number'),
            data_get($subscription, 'subscription.creditCard.maskedNumber'),
            data_get($subscription, 'subscription.creditCard.last4'),
            data_get($subscription, 'subscription.cardNumber'),
        ];

        foreach ($candidatos as $candidato) {
            if (!is_string($candidato) && !is_numeric($candidato)) {
                continue;
            }

            $digitos = preg_replace('/\D/', '', (string) $candidato);
            if (strlen($digitos) >= 4) {
                return substr($digitos, -4);
            }
        }

        return null;
    }

    private function extrairBandeiraCartaoAssinatura(array $subscription): ?string
    {
        $candidatos = [
            data_get($subscription, 'creditCard.creditCardBrand'),
            data_get($subscription, 'creditCard.brand'),
            data_get($subscription, 'creditCardBrand'),
            data_get($subscription, 'cardBrand'),
            data_get($subscription, 'card.brand'),
            data_get($subscription, 'payment.creditCard.creditCardBrand'),
            data_get($subscription, 'payment.creditCard.brand'),
            data_get($subscription, 'payment.creditCardBrand'),
            data_get($subscription, 'payment.cardBrand'),
            data_get($subscription, 'subscription.creditCard.creditCardBrand'),
            data_get($subscription, 'subscription.creditCard.brand'),
            data_get($subscription, 'subscription.creditCardBrand'),
            data_get($subscription, 'subscription.cardBrand'),
        ];

        foreach ($candidatos as $candidato) {
            $valor = trim((string) $candidato);
            if ($valor === '') {
                continue;
            }

            if (in_array(strtoupper($valor), ['NULL', 'N/A', 'NA', 'UNDEFINED'], true)) {
                continue;
            }

            return strtoupper(str_replace(['-', '_'], ' ', $valor));
        }

        return null;
    }

    private function extrairSubscriptionIdWebhookAsaas(array $payload, array $payment = []): string
    {
        $candidatos = [
            data_get($payment, 'subscription.id'),
            data_get($payment, 'subscription'),
            data_get($payload, 'subscription.id'),
            data_get($payload, 'subscription'),
            data_get($payload, 'id'),
        ];

        foreach ($candidatos as $candidato) {
            $valor = trim((string) $candidato);
            if ($valor === '') {
                continue;
            }

            return $valor;
        }

        return '';
    }

    private function sincronizarMetadadosCartaoWebhookAsaas(array $payload, array $payment = []): void
    {
        $subscriptionId = $this->extrairSubscriptionIdWebhookAsaas($payload, $payment);
        if ($subscriptionId === '' || !$this->colunaSubscriptionDisponivel()) {
            return;
        }

        $assinatura = AssinaturaPlano::query()
            ->where('asaas_subscription_id', $subscriptionId)
            ->latest('id')
            ->first();

        if (!$assinatura) {
            return;
        }

        $billingTypeRaw = strtoupper(trim((string) (
            data_get($payload, 'billingType')
            ?? data_get($payload, 'subscription.billingType')
            ?? data_get($payment, 'billingType')
            ?? ''
        )));

        if ($billingTypeRaw === '') {
            return;
        }

        $tiposConhecidos = [
            'BOLETO',
            'CREDIT_CARD',
            'DEBIT_CARD',
            'CARTAO_CREDITO',
            'CARTAO_DEBITO',
            'CREDITO',
            'DEBITO',
        ];

        if (!in_array($billingTypeRaw, $tiposConhecidos, true)) {
            return;
        }

        $billingType = $this->asaasGateway->normalizeBillingType($billingTypeRaw);

        if ($billingType !== AssinaturaPlano::METODO_PIX && $assinatura->metodo_mensal !== $billingType) {
            $assinatura->metodo_mensal = $billingType;
            $assinatura->save();
        }
    }

    private function extrairMetadadosCartaoAssinatura(AssinaturaPlano $assinatura): array
    {
        $observacoes = (string) ($assinatura->observacoes ?? '');
        $posicao = strrpos($observacoes, self::CARD_META_PREFIX);

        if ($posicao === false) {
            return [];
        }

        $json = trim(substr($observacoes, $posicao + strlen(self::CARD_META_PREFIX)));
        if ($json === '') {
            return [];
        }

        $dados = json_decode($json, true);

        return is_array($dados) ? $dados : [];
    }

    private function obterMetadadosCartaoDoUltimoWebhookPagamento(AssinaturaPlano $assinatura): array
    {
        $pagamento = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->whereNotNull('json_webhook')
            ->orderByDesc('id')
            ->first();

        if (!$pagamento) {
            return [];
        }

        $jsonWebhook = trim((string) ($pagamento->json_webhook ?? ''));
        if ($jsonWebhook === '') {
            return [];
        }

        $payload = json_decode($jsonWebhook, true);
        if (!is_array($payload)) {
            return [];
        }

        $last4 = $this->extrairUltimos4CartaoAssinatura($payload);
        $brand = $this->extrairBandeiraCartaoAssinatura($payload);
        $billingType = strtoupper((string) (
            data_get($payload, 'billingType')
            ?? data_get($payload, 'subscription.billingType')
            ?? data_get($payload, 'payment.billingType')
            ?? ''
        ));

        if ($last4 === null && $brand === null && $billingType === '') {
            return [];
        }

        return [
            'last4' => $last4,
            'brand' => $brand,
            'billingType' => $billingType,
        ];
    }

    private function salvarMetadadosCartaoAssinatura(AssinaturaPlano $assinatura, array $metadados): void
    {
        $last4 = preg_replace('/\D/', '', (string) ($metadados['last4'] ?? ''));
        $last4 = strlen($last4) >= 4 ? substr($last4, -4) : '';

        $brand = strtoupper(trim((string) ($metadados['brand'] ?? '')));
        if (in_array($brand, ['NULL', 'N/A', 'NA', 'UNDEFINED'], true)) {
            $brand = '';
        }
        $billingType = strtoupper(trim((string) ($metadados['billingType'] ?? '')));
        $subscriptionId = trim((string) ($metadados['subscription_id'] ?? ''));
        $source = trim((string) ($metadados['source'] ?? ''));
        $event = trim((string) ($metadados['event'] ?? ''));

        if ($last4 === '' && $brand === '' && $billingType === '') {
            return;
        }

        $payloadMeta = [
            'updated_at' => now()->format('Y-m-d H:i:s'),
            'last4' => $last4 !== '' ? $last4 : null,
            'brand' => $brand !== '' ? $brand : null,
            'billingType' => $billingType !== '' ? $billingType : null,
            'subscription_id' => $subscriptionId !== '' ? $subscriptionId : ($this->obterSubscriptionId($assinatura) ?? null),
            'source' => $source !== '' ? $source : null,
            'event' => $event !== '' ? $event : null,
        ];

        $payloadMeta = array_filter($payloadMeta, static fn($valor) => $valor !== null && $valor !== '');

        $observacoesAtuais = (string) ($assinatura->observacoes ?? '');
        $posicao = strrpos($observacoesAtuais, self::CARD_META_PREFIX);
        $observacoesSemMeta = $posicao === false
            ? trim($observacoesAtuais)
            : trim(substr($observacoesAtuais, 0, $posicao));

        $novaObservacao = trim(
            $observacoesSemMeta
            . ' '
            . self::CARD_META_PREFIX
            . json_encode($payloadMeta, JSON_UNESCAPED_UNICODE)
        );

        if ($novaObservacao === $observacoesAtuais) {
            return;
        }

        $assinatura->observacoes = $novaObservacao;
        $assinatura->save();
    }

    private function definirSubscriptionId(AssinaturaPlano $assinatura, ?string $subscriptionId): void
    {
        if (!$this->colunaSubscriptionDisponivel()) {
            throw new Exception('Coluna assinaturas_planos.asaas_subscription_id não encontrada. Aplique o SQL de upgrade de billing.');
        }

        $assinatura->asaas_subscription_id = $subscriptionId !== null && trim($subscriptionId) !== ''
            ? trim($subscriptionId)
            : null;
        $assinatura->save();
    }

    private function cancelarAssinaturaRecorrenteAsaas(string $subscriptionId): void
    {
        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return;
        }

        try {
            $this->asaasGateway->cancelSubscription($subscriptionId);
        } catch (Exception $e) {
            Log::error('Falha ao cancelar assinatura recorrente no Asaas', [
                'subscription_id' => $subscriptionId,
                'erro' => $e->getMessage(),
            ]);

            throw new Exception(
                'Não foi possível cancelar a assinatura recorrente anterior no Asaas: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function obterTokenCartaoAssinaturaAsaas(string $subscriptionId): ?string
    {
        if (!$this->asaasGateway->isConfigured()) {
            return null;
        }

        $subscriptionId = trim($subscriptionId);
        if ($subscriptionId === '') {
            return null;
        }

        try {
            $dadosAssinatura = $this->asaasGateway->getSubscriptionWithCard($subscriptionId);
            $token = trim((string) ($dadosAssinatura['creditCardToken'] ?? ''));

            return $token !== '' ? $token : null;
        } catch (Exception $e) {
            Log::warning('Nao foi possivel obter token do cartao da assinatura anterior no Asaas.', [
                'subscription_id' => $subscriptionId,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function criarAssinaturaMensalAsaas(
        AssinaturaPlano $assinatura,
        Empresa $empresa,
        string $billingType,
        float $valor,
        string $dataPrimeiroVencimento,
        string $descricao,
        ?array $cardData = null,
        ?string $creditCardToken = null
    ): array {
        if (!$this->asaasGateway->isConfigured()) {
            throw new Exception('Integração Asaas não configurada. Defina ASAAS_API_KEY no .env.');
        }

        if (!$this->colunaSubscriptionDisponivel()) {
            throw new Exception('Banco desatualizado: falta coluna assinaturas_planos.asaas_subscription_id para recorrência no Asaas.');
        }

        $billingType = $this->asaasGateway->normalizeBillingType($billingType);
        if ($billingType === AssinaturaPlano::METODO_PIX) {
            throw new Exception('PIX não é permitido para recorrência mensal.');
        }

        $payload = [
            'customer' => (string) $assinatura->asaas_customer_id,
            'billingType' => $billingType,
            'value' => round($valor, 2),
            'nextDueDate' => Carbon::parse($dataPrimeiroVencimento)->toDateString(),
            'cycle' => 'MONTHLY',
            'description' => mb_substr($descricao, 0, 500),
            'externalReference' => $this->montarExternalReferenceRecorrencia($assinatura),
        ];

        // Se for cartão de crédito, incluir dados do cartão informado ou token já cadastrado no Asaas.
        if ($billingType === AssinaturaPlano::METODO_CREDIT_CARD) {
            if ($cardData) {
                $payload['creditCard'] = [
                    'holderName' => $cardData['holderName'] ?? '',
                    'number' => preg_replace('/\D/', '', $cardData['number'] ?? ''),
                    'expiryMonth' => $cardData['expiryMonth'] ?? '',
                    'expiryYear' => $cardData['expiryYear'] ?? '',
                    'ccv' => $cardData['ccv'] ?? '',
                ];

                $payload['creditCardHolderInfo'] = $this->montarDadosTitularCartao($empresa, $cardData);

                // remoteIp é obrigatório para criação de assinatura com cartão de crédito
                $payload['remoteIp'] = $this->obterRemoteIpCompra();
            } else {
                $token = trim((string) $creditCardToken);
                if ($token !== '') {
                    $payload['creditCardToken'] = $token;
                }
            }
        }

        $assinaturaAsaas = $this->asaasGateway->createSubscription($payload);
        $subscriptionId = (string) ($assinaturaAsaas['id'] ?? '');

        if ($subscriptionId === '') {
            throw new Exception('Asaas não retornou ID da assinatura recorrente.');
        }

        try {
            $this->definirSubscriptionId($assinatura, $subscriptionId);
        } catch (Exception $e) {
            try {
                $this->asaasGateway->cancelSubscription($subscriptionId);
            } catch (Exception $cancelException) {
                Log::warning('Falha ao reverter assinatura recorrente no Asaas após erro local', [
                    'subscription_id' => $subscriptionId,
                    'erro_cancelamento' => $cancelException->getMessage(),
                ]);
            }

            throw $e;
        }

        return [
            'id' => $subscriptionId,
            'status' => (string) ($assinaturaAsaas['status'] ?? 'UNKNOWN'),
            'next_due_date' => (string) ($assinaturaAsaas['nextDueDate'] ?? $dataPrimeiroVencimento),
            'billing_type' => $billingType,
            'value' => round($valor, 2),
        ];
    }

    private function obterPrimeiroPaymentSubscription(
        string $subscriptionId,
        int $maxTentativas = 1,
        int $intervaloMs = 0
    ): ?array
    {
        if (trim($subscriptionId) === '') {
            return null;
        }

        $tentativas = max(1, $maxTentativas);
        $intervaloMs = max(0, $intervaloMs);

        for ($indice = 1; $indice <= $tentativas; $indice++) {
            try {
                $payments = $this->asaasGateway->getSubscriptionPayments($subscriptionId);

                if (!empty($payments)) {
                    $primeiroPayment = collect($payments)
                        ->filter(fn ($p) => in_array($p['status'] ?? '', ['PENDING', 'AWAITING_RISK_ANALYSIS', 'CONFIRMED']))
                        ->sortBy('dueDate')
                        ->first();

                    if (!$primeiroPayment) {
                        $primeiroPayment = $payments[0] ?? null;
                    }

                    if (is_array($primeiroPayment)) {
                        return $primeiroPayment;
                    }
                }
            } catch (Exception $e) {
                Log::warning('Falha ao consultar primeiro payment da subscription no Asaas.', [
                    'subscription_id' => $subscriptionId,
                    'tentativa' => $indice,
                    'erro' => $e->getMessage(),
                ]);
            }

            if ($indice < $tentativas && $intervaloMs > 0) {
                usleep($intervaloMs * 1000);
            }
        }

        return null;
    }

    private function sincronizarPrimeiroPaymentSubscription(
        string $subscriptionId,
        AssinaturaPlanoPagamento $pagamento,
        ?array $primeiroPayment = null
    ): void
    {
        try {
            $primeiroPayment = $primeiroPayment ?: $this->obterPrimeiroPaymentSubscription($subscriptionId);

            if (!$primeiroPayment) {
                return;
            }

            $pagamento->asaas_payment_id = $primeiroPayment['id'] ?? null;
            $pagamento->asaas_invoice_url = $primeiroPayment['invoiceUrl'] ?? null;
            $pagamento->asaas_bank_slip_url = $primeiroPayment['bankSlipUrl'] ?? null;
            $pagamento->status = $this->mapearStatusAsaas($primeiroPayment['status'] ?? null);
            $pagamento->json_resposta = json_encode($primeiroPayment);

            // Atualizar data de vencimento e competência com base no Asaas (fonte da verdade)
            if (!empty($primeiroPayment['dueDate'])) {
                $dataVencimentoAsaas = Carbon::parse($primeiroPayment['dueDate']);
                $pagamento->data_vencimento = $dataVencimentoAsaas->toDateString();
                $pagamento->competencia = $dataVencimentoAsaas->startOfMonth()->toDateString();
            }

            $pagamento->save();
        } catch (Exception $e) {
            Log::warning('Falha ao sincronizar primeiro payment da subscription', [
                'subscription_id' => $subscriptionId,
                'pagamento_id' => $pagamento->id,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function registrarMensalidadeRecorrenteInicial(
        AssinaturaPlano $assinatura,
        Empresa $empresa,
        string $billingType,
        float $valor,
        string $dataVencimento
    ): AssinaturaPlanoPagamento {
        $billingType = $this->asaasGateway->normalizeBillingType($billingType);
        $dataVencimento = Carbon::parse($dataVencimento)->toDateString();
        $competencia = Carbon::parse($dataVencimento)->startOfMonth()->toDateString();

        $pagamento = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereDate('data_vencimento', $dataVencimento)
            ->latest('id')
            ->first();

        if ($pagamento) {
            $pagamento->metodo_pagamento = $billingType;
            $pagamento->valor = round($valor, 2);
            $pagamento->status = in_array($pagamento->status, [
                AssinaturaPlanoPagamento::STATUS_PAGO,
                AssinaturaPlanoPagamento::STATUS_CANCELADO,
            ], true) ? $pagamento->status : AssinaturaPlanoPagamento::STATUS_GERADO;
            $pagamento->observacoes = $this->normalizarObservacaoPagamento(
                $pagamento->observacoes ?: 'Mensalidade vinculada a assinatura recorrente do Asaas.'
            );
            $pagamento->save();

            return $pagamento;
        }

        return AssinaturaPlanoPagamento::query()->create([
            'id_assinatura_plano' => $assinatura->id,
            'id_empresa' => $empresa->id_empresa,
            'id_plano' => $assinatura->id_plano,
            'id_plano_contratado' => $assinatura->id_plano_contratado,
            'tipo_cobranca' => AssinaturaPlanoPagamento::TIPO_MENSALIDADE,
            'competencia' => $competencia,
            'metodo_pagamento' => $billingType,
            'valor' => round($valor, 2),
            'data_vencimento' => $dataVencimento,
            'status' => AssinaturaPlanoPagamento::STATUS_GERADO,
            'tentativas' => 0,
            'observacoes' => $this->normalizarObservacaoPagamento('Mensalidade vinculada a assinatura recorrente do Asaas.'),
        ]);
    }

    private function criarPagamentoAsaas(
        AssinaturaPlano $assinatura,
        Empresa $empresa,
        string $tipoCobranca,
        string $billingType,
        float $valor,
        string $dataVencimento,
        string $descricao,
        Carbon $competencia
    ): AssinaturaPlanoPagamento {
        if (!$this->asaasGateway->isConfigured()) {
            throw new Exception('Integração Asaas não configurada. Defina ASAAS_API_KEY no .env.');
        }

        $billingType = $this->asaasGateway->normalizeBillingType($billingType);

        if ($billingType === AssinaturaPlano::METODO_PIX && $tipoCobranca !== AssinaturaPlanoPagamento::TIPO_ADESAO) {
            throw new Exception('PIX está permitido apenas para cobrança de adesão.');
        }

        $payload = [
            'customer' => (string) $assinatura->asaas_customer_id,
            'billingType' => $billingType,
            'value' => round($valor, 2),
            'dueDate' => Carbon::parse($dataVencimento)->toDateString(),
            'description' => mb_substr($descricao, 0, 500),
            'externalReference' => $this->montarExternalReference($assinatura, $tipoCobranca, $competencia),
        ];

        $asaasPagamento = $this->asaasGateway->createPayment($payload);
        $paymentId = (string) ($asaasPagamento['id'] ?? '');

        if ($paymentId === '') {
            throw new Exception('Asaas não retornou ID da cobrança.');
        }

        $pixQr = null;
        if ($billingType === AssinaturaPlano::METODO_PIX) {
            $pixQr = $this->asaasGateway->getPixQrCode($paymentId);
        }

        return AssinaturaPlanoPagamento::create([
            'id_assinatura_plano' => $assinatura->id,
            'id_empresa' => $empresa->id_empresa,
            'id_plano' => $assinatura->id_plano,
            'id_plano_contratado' => $assinatura->id_plano_contratado,
            'tipo_cobranca' => $tipoCobranca,
            'competencia' => $competencia->toDateString(),
            'metodo_pagamento' => $billingType,
            'asaas_payment_id' => $paymentId,
            'asaas_invoice_url' => $asaasPagamento['invoiceUrl'] ?? null,
            'asaas_bank_slip_url' => $asaasPagamento['bankSlipUrl'] ?? null,
            'asaas_pix_qr_code' => $pixQr['encodedImage'] ?? null,
            'asaas_pix_copy_paste' => $pixQr['payload'] ?? null,
            'valor' => round($valor, 2),
            'data_vencimento' => Carbon::parse($dataVencimento)->toDateString(),
            'status' => $this->mapearStatusAsaas($asaasPagamento['status'] ?? null),
            'json_resposta' => json_encode($asaasPagamento),
            'tentativas' => 0,
        ]);
    }

    private function registrarPagamentoConfirmado(AssinaturaPlanoPagamento $pagamento, array $paymentData): void
    {
        if ($pagamento->status !== AssinaturaPlanoPagamento::STATUS_PAGO) {
            $pagamento->status = AssinaturaPlanoPagamento::STATUS_PAGO;
        }

        $pagamento->data_pagamento = $this->parseAsaasDateTime(
            $paymentData['paymentDate'] ?? $paymentData['confirmedDate'] ?? null
        ) ?? now();
        $pagamento->save();

        $assinatura = $pagamento->assinatura;
        if (!$assinatura) {
            return;
        }

        $empresa = $assinatura->empresa;
        if (!$empresa) {
            return;
        }

        if ($pagamento->tipo_cobranca === AssinaturaPlanoPagamento::TIPO_ADESAO) {
            $plano = $assinatura->plano;
            if (!$plano) {
                return;
            }

            $valorMensal = (float) ($assinatura->planoContratado->valor ?? $plano->valor);
            $valorAdesao = $this->resolverValorAdesaoProvisionamento($pagamento);

            $this->ativarAssinaturaAposPagamentoAdesao(
                $assinatura,
                $empresa,
                $plano,
                $valorMensal,
                $valorAdesao
            );
        }

        if ($pagamento->tipo_cobranca === AssinaturaPlanoPagamento::TIPO_MENSALIDADE) {
            $assinatura->ultimo_pagamento_em = now();

            if ($pagamento->data_vencimento) {
                $assinatura->proxima_cobranca_em = Carbon::parse($pagamento->data_vencimento)
                    ->addMonthNoOverflow()
                    ->toDateString();
            }

            if ($assinatura->status === AssinaturaPlano::STATUS_SUSPENSA && !$assinatura->bloqueada_por_inadimplencia) {
                $assinatura->status = $this->resolverStatusOnboarding($empresa);
            }
            $assinatura->save();
        }

        $this->tentarDesbloquearEmpresaPorPagamento($assinatura, $empresa);
    }

    private function registrarPagamentoVencido(AssinaturaPlanoPagamento $pagamento): void
    {
        $pagamento->status = AssinaturaPlanoPagamento::STATUS_VENCIDO;
        $pagamento->save();

        $assinatura = $pagamento->assinatura;
        if ($assinatura && !$assinatura->inadimplente_desde) {
            $assinatura->inadimplente_desde = $pagamento->data_vencimento;
            $assinatura->save();
        }
    }

    private function ativarAssinaturaAposPagamentoAdesao(
        AssinaturaPlano $assinatura,
        Empresa $empresa,
        Plano $plano,
        float $valorMensal,
        float $valorAdesao
    ): void {
        $assinatura->loadMissing('planoContratado');

        if ($this->deveProvisionarPlanoContratado($assinatura->planoContratado, $plano, $valorMensal, $valorAdesao)) {
            $contrato = $this->planoProvisioningService->ativarPlano(
                $empresa,
                $plano,
                $valorMensal,
                $valorAdesao,
                'Ativado após confirmação do pagamento de adesão'
            );

            $assinatura->id_plano_contratado = $contrato->id;
            $assinatura->setRelation('planoContratado', $contrato);
        }

        $assinatura->status = $this->resolverStatusOnboarding($empresa);
        $assinatura->ultimo_pagamento_em = now();
        $assinatura->save();
    }

    private function deveProvisionarPlanoContratado(
        ?PlanoContratado $contratoAtual,
        Plano $plano,
        float $valorMensal,
        float $valorAdesao
    ): bool {
        if (!$contratoAtual || $contratoAtual->status !== 'ativo') {
            return true;
        }

        $nomeContrato = $this->normalizarNomePlanoParaComparacao((string) $contratoAtual->nome);
        $nomePlano = $this->normalizarNomePlanoParaComparacao((string) $plano->nome);

        if ($nomeContrato === '' || $nomePlano === '' || $nomeContrato !== $nomePlano) {
            return true;
        }

        return !$this->valoresMonetariosIguais((float) $contratoAtual->valor, $valorMensal)
            || !$this->valoresMonetariosIguais((float) $contratoAtual->adesao, $valorAdesao);
    }

    private function valoresMonetariosIguais(float $valorA, float $valorB): bool
    {
        return abs(round($valorA, 2) - round($valorB, 2)) < 0.01;
    }

    private function normalizarNomePlanoParaComparacao(string $nomePlano): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($nomePlano))) ?: '';
    }

    private function tentarDesbloquearEmpresaPorPagamento(AssinaturaPlano $assinatura, Empresa $empresa): void
    {
        if (!$assinatura->bloqueada_por_inadimplencia && $empresa->status !== 'bloqueado') {
            return;
        }

        $temPendenciasCriticas = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereIn('status', [
                AssinaturaPlanoPagamento::STATUS_GERADO,
                AssinaturaPlanoPagamento::STATUS_PENDENTE,
                AssinaturaPlanoPagamento::STATUS_VENCIDO,
                AssinaturaPlanoPagamento::STATUS_FALHOU,
            ])
            ->whereDate('data_vencimento', '<=', now()->subDays(5)->toDateString())
            ->exists();

        if ($temPendenciasCriticas) {
            return;
        }

        if ($empresa->status === 'bloqueado') {
            $empresa->update([
                'status' => 'ativo',
                'data_bloqueio' => null,
            ]);
        }

        $assinatura->update([
            'status' => $this->resolverStatusOnboarding($empresa),
            'bloqueada_por_inadimplencia' => 0,
            'inadimplente_desde' => null,
        ]);
    }

    private function colunasCancelamentoDisponiveis(): bool
    {
        if (!is_null($this->cancelamentoColsDisponiveis)) {
            return $this->cancelamentoColsDisponiveis;
        }

        $this->cancelamentoColsDisponiveis = Schema::hasTable('assinaturas_planos')
            && Schema::hasColumn('assinaturas_planos', 'cancelamento_solicitado_em')
            && Schema::hasColumn('assinaturas_planos', 'cancelamento_efetivo_em')
            && Schema::hasColumn('assinaturas_planos', 'motivo_cancelamento');

        return $this->cancelamentoColsDisponiveis;
    }

    private function limparAgendamentoCancelamento(AssinaturaPlano $assinatura): void
    {
        if (!$this->colunasCancelamentoDisponiveis()) {
            return;
        }

        $assinatura->cancelamento_solicitado_em = null;
        $assinatura->cancelamento_efetivo_em = null;
        $assinatura->motivo_cancelamento = null;
    }

    private function definirAgendamentoCancelamento(AssinaturaPlano $assinatura, Carbon $dataEfetiva): void
    {
        if (!$this->colunasCancelamentoDisponiveis()) {
            return;
        }

        $assinatura->cancelamento_solicitado_em = now();
        $assinatura->cancelamento_efetivo_em = $dataEfetiva->toDateString();
    }

    private function calcularDataEfetivaCancelamento(AssinaturaPlano $assinatura): Carbon
    {
        $hoje = now()->startOfDay();

        $ultimaMensalidadePaga = $this->obterDataUltimaMensalidadePaga($assinatura);
        if ($ultimaMensalidadePaga) {
            $vigencia = $ultimaMensalidadePaga->copy()->addDays(30)->startOfDay();

            return $vigencia->lt($hoje) ? $hoje : $vigencia;
        }

        // Se ja existe a proxima cobranca, a vigencia paga vai ate a vespera desse vencimento.
        if ($assinatura->proxima_cobranca_em) {
            $proximaCobranca = Carbon::parse((string) $assinatura->proxima_cobranca_em)->startOfDay();

            if ($proximaCobranca->gt($hoje)) {
                return $proximaCobranca->copy()->subDay();
            }
        }

        return $hoje;
    }

    private function cancelarPagamentosFuturos(AssinaturaPlano $assinatura, Carbon $aPartirDe): void
    {
        $pagamentos = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereDate('data_vencimento', '>=', $aPartirDe->toDateString())
            ->whereNotIn('status', [
                AssinaturaPlanoPagamento::STATUS_PAGO,
                AssinaturaPlanoPagamento::STATUS_CANCELADO,
            ])
            ->get();

        foreach ($pagamentos as $pagamento) {
            if (!empty($pagamento->asaas_payment_id)) {
                try {
                    $this->asaasGateway->deletePayment((string) $pagamento->asaas_payment_id);
                } catch (Exception $e) {
                    Log::warning('Falha ao cancelar payment futuro no Asaas durante cancelamento de assinatura', [
                        'assinatura_id' => $assinatura->id,
                        'payment_id' => $pagamento->asaas_payment_id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }

            $pagamento->status = AssinaturaPlanoPagamento::STATUS_CANCELADO;
            $pagamento->observacoes = $this->anexarObservacaoPagamento(
                (string) $pagamento->observacoes,
                'Cancelado por encerramento da assinatura em ' . now()->format('d/m/Y H:i')
            );
            $pagamento->save();
        }
    }

    private function cancelarAdesoesPendentes(AssinaturaPlano $assinatura, string $motivo): void
    {
        $pagamentos = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $assinatura->id)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_ADESAO)
            ->whereNotIn('status', [
                AssinaturaPlanoPagamento::STATUS_PAGO,
                AssinaturaPlanoPagamento::STATUS_CANCELADO,
            ])
            ->get();

        foreach ($pagamentos as $pagamento) {
            if (!empty($pagamento->asaas_payment_id)) {
                try {
                    $this->asaasGateway->deletePayment((string) $pagamento->asaas_payment_id);
                } catch (Exception $e) {
                    Log::warning('Falha ao cancelar pagamento de adesao pendente no Asaas.', [
                        'assinatura_id' => $assinatura->id,
                        'pagamento_id' => $pagamento->id,
                        'asaas_payment_id' => $pagamento->asaas_payment_id,
                        'erro' => $e->getMessage(),
                    ]);
                }
            }

            $pagamento->status = AssinaturaPlanoPagamento::STATUS_CANCELADO;
            $pagamento->observacoes = $this->anexarObservacaoPagamento(
                (string) $pagamento->observacoes,
                $motivo . ' em ' . now()->format('d/m/Y H:i')
            );
            $pagamento->save();
        }
    }

    private function finalizarCancelamentoAssinatura(AssinaturaPlano $assinatura): void
    {
        DB::transaction(function () use ($assinatura) {
            $registro = AssinaturaPlano::query()->lockForUpdate()->find($assinatura->id);

            if (!$registro || $registro->status === AssinaturaPlano::STATUS_CANCELADA) {
                return;
            }

            $subscriptionId = $this->obterSubscriptionId($registro);
            if ($subscriptionId) {
                try {
                    $this->cancelarAssinaturaRecorrenteAsaas($subscriptionId);
                } catch (Exception $e) {
                    Log::warning('Falha ao confirmar cancelamento da recorrencia no fechamento da assinatura', [
                        'assinatura_id' => $registro->id,
                        'subscription_id' => $subscriptionId,
                        'erro' => $e->getMessage(),
                    ]);
                }

                $this->definirSubscriptionId($registro, null);
            }

            $this->cancelarPagamentosFuturos($registro, now()->startOfDay());

            PlanoContratado::query()
                ->where('id_empresa', $registro->id_empresa)
                ->where('status', 'ativo')
                ->update(['status' => 'inativo']);

            $registro->status = AssinaturaPlano::STATUS_CANCELADA;
            $registro->proxima_cobranca_em = null;
            $registro->bloqueada_por_inadimplencia = 0;
            $registro->inadimplente_desde = null;
            $registro->observacoes = $this->anexarObservacao(
                (string) $registro->observacoes,
                'Cancelamento efetivado em ' . now()->format('d/m/Y H:i')
            );

            if ($this->colunasCancelamentoDisponiveis() && !$registro->cancelamento_efetivo_em) {
                $registro->cancelamento_efetivo_em = now()->toDateString();
            }

            $registro->save();

            $empresa = Empresa::find($registro->id_empresa);
            if ($empresa && $empresa->status === 'ativo' && $empresa->semPlanoAtivo()) {
                $empresa->update([
                    'status' => 'bloqueado',
                    'data_bloqueio' => now(),
                ]);
            }
        });
    }

    private function anexarObservacaoPagamento(string $observacaoAtual, string $novoTrecho): string
    {
        return (string) $this->normalizarObservacaoPagamento(
            $this->anexarObservacao($observacaoAtual, $novoTrecho)
        );
    }

    private function normalizarObservacaoPagamento(?string $observacoes): ?string
    {
        if ($observacoes === null) {
            return null;
        }

        $observacoes = trim($observacoes);
        if ($observacoes === '') {
            return null;
        }

        $limite = $this->obterLimiteObservacaoPagamento();
        if (mb_strlen($observacoes) <= $limite) {
            return $observacoes;
        }

        if ($limite <= 3) {
            return mb_substr($observacoes, -$limite);
        }

        // Preserva o trecho mais recente da observação para manter o motivo atual do cancelamento.
        $sufixo = mb_substr($observacoes, -($limite - 3));

        return '...' . ltrim($sufixo, ' |');
    }

    private function obterLimiteObservacaoPagamento(): int
    {
        if ($this->limiteObservacoesPagamento !== null) {
            return $this->limiteObservacoesPagamento;
        }

        $limite = 255;

        try {
            if (Schema::hasTable('assinaturas_planos_pagamentos') && Schema::hasColumn('assinaturas_planos_pagamentos', 'observacoes')) {
                $tipoColuna = strtolower((string) Schema::getColumnType('assinaturas_planos_pagamentos', 'observacoes'));

                if ($tipoColuna === 'text') {
                    $limite = 65535;
                }

                if (DB::getDriverName() === 'mysql') {
                    $coluna = DB::selectOne(
                        'SELECT data_type, character_maximum_length
                         FROM information_schema.columns
                         WHERE table_schema = database()
                           AND table_name = ?
                           AND column_name = ?
                         LIMIT 1',
                        ['assinaturas_planos_pagamentos', 'observacoes']
                    );

                    if ($coluna) {
                        $dataType = strtolower((string) ($coluna->data_type ?? ''));
                        $characterMaximumLength = (int) ($coluna->character_maximum_length ?? 0);

                        if ($characterMaximumLength > 0) {
                            $limite = $characterMaximumLength;
                        } elseif ($dataType === 'tinytext') {
                            $limite = 255;
                        } elseif (in_array($dataType, ['text', 'mediumtext', 'longtext'], true)) {
                            $limite = 65535;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning('Falha ao identificar limite da coluna observacoes em assinaturas_planos_pagamentos.', [
                'erro' => $e->getMessage(),
            ]);
        }

        $this->limiteObservacoesPagamento = max(32, $limite);

        return $this->limiteObservacoesPagamento;
    }

    private function anexarObservacao(string $observacaoAtual, string $novoTrecho): string
    {
        $observacaoAtual = trim($observacaoAtual);
        $novoTrecho = trim($novoTrecho);

        if ($observacaoAtual === '') {
            return $novoTrecho;
        }

        return $observacaoAtual . ' | ' . $novoTrecho;
    }

    private function obterRemoteIpCompra(): string
    {
        $candidatos = [
            (string) request()->header('CF-Connecting-IP', ''),
            (string) request()->header('True-Client-IP', ''),
            (string) request()->header('X-Forwarded-For', ''),
            (string) request()->ip(),
        ];

        foreach ($candidatos as $candidato) {
            if ($candidato === '') {
                continue;
            }

            $partes = explode(',', $candidato);
            foreach ($partes as $parte) {
                $ip = trim($parte);
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    private function montarDadosTitularCartao(Empresa $empresa, array $cardData): array
    {
        $cpfCnpj = preg_replace('/\D/', '', (string) (
            $cardData['cpfCnpj']
            ?? ($empresa->cpf ?: ($empresa->cnpj ?: ''))
        ));

        $phone = preg_replace('/\D/', '', (string) ($cardData['phone'] ?? ($empresa->telefone ?? '')));
        $mobilePhone = preg_replace('/\D/', '', (string) (
            $cardData['mobilePhone']
            ?? ($cardData['phone'] ?? ($empresa->telefone ?? ''))
        ));

        return [
            'name' => $cardData['holderName'] ?? $empresa->razao_social ?? $empresa->nome_empresa,
            'email' => $cardData['email'] ?? ($empresa->email ?? ''),
            'cpfCnpj' => $cpfCnpj,
            'postalCode' => preg_replace('/\D/', '', (string) ($cardData['postalCode'] ?? ($empresa->cep ?? ''))),
            'addressNumber' => (string) ($cardData['addressNumber'] ?? ($empresa->numero ?: 'S/N')),
            'addressComplement' => (string) ($cardData['addressComplement'] ?? ($empresa->complemento ?? '')),
            'phone' => $phone,
            'mobilePhone' => $mobilePhone,
        ];
    }

    private function resolverStatusOnboarding(Empresa $empresa): string
    {
        if (!$empresa->dadosCadastraisCompletos()) {
            return AssinaturaPlano::STATUS_ONBOARDING_DADOS;
        }

        $contratoAssinado = false;

        if (Schema::hasTable('client_contracts')) {
            $contratoAssinado = ClientContract::query()
                ->where('id_empresa', $empresa->id_empresa)
                ->where('status', ClientContract::STATUS_ATIVO)
                ->exists();
        }

        if (!$contratoAssinado && Schema::hasTable('empresa_contratos_software')) {
            $contratoAssinado = EmpresaContratoSoftware::query()
                ->where('id_empresa', $empresa->id_empresa)
                ->where('status', EmpresaContratoSoftware::STATUS_ASSINADO)
                ->exists();
        }

        if (!$contratoAssinado) {
            return AssinaturaPlano::STATUS_ONBOARDING_CONTRATO;
        }

        return AssinaturaPlano::STATUS_ATIVA;
    }

    private function montarExternalReference(AssinaturaPlano $assinatura, string $tipo, Carbon $competencia): string
    {
        return sprintf(
            'ASSINATURA:%d:%s:%s',
            (int) $assinatura->id,
            strtoupper($tipo),
            $competencia->format('Ym')
        );
    }

    private function montarExternalReferenceRecorrencia(AssinaturaPlano $assinatura): string
    {
        return sprintf('ASSINATURA:%d:SUBSCRIPTION', (int) $assinatura->id);
    }

    private function buscarPagamentoPorReferenciaExterna(string $externalReference, array $paymentData = []): ?AssinaturaPlanoPagamento
    {
        if ($externalReference === '') {
            return null;
        }

        if (preg_match('/^ASSINATURA:(\d+):(ADESAO|MENSALIDADE):(\d{6})$/', $externalReference, $m)) {
            $idAssinatura = (int) $m[1];
            $tipo = strtolower($m[2]);
            $competencia = Carbon::createFromFormat('Ym', $m[3])->startOfMonth()->toDateString();

            return AssinaturaPlanoPagamento::query()
                ->where('id_assinatura_plano', $idAssinatura)
                ->where('tipo_cobranca', $tipo)
                ->whereDate('competencia', $competencia)
                ->latest('id')
                ->first();
        }

        if (!preg_match('/^ASSINATURA:(\d+):SUBSCRIPTION$/', $externalReference, $mRecorrente)) {
            return null;
        }

        $idAssinatura = (int) $mRecorrente[1];
        $assinatura = AssinaturaPlano::query()->find($idAssinatura);

        if (!$assinatura) {
            return null;
        }

        $dueDate = Carbon::parse((string) ($paymentData['dueDate'] ?? now()->toDateString()))->toDateString();
        $competencia = Carbon::parse($dueDate)->startOfMonth()->toDateString();
        $paymentId = (string) ($paymentData['id'] ?? '');

        $queryExistente = AssinaturaPlanoPagamento::query()
            ->where('id_assinatura_plano', $idAssinatura)
            ->where('tipo_cobranca', AssinaturaPlanoPagamento::TIPO_MENSALIDADE)
            ->whereDate('data_vencimento', $dueDate);

        if ($paymentId !== '') {
            $existenteComMesmoPaymentId = (clone $queryExistente)
                ->where('asaas_payment_id', $paymentId)
                ->latest('id')
                ->first();

            if ($existenteComMesmoPaymentId) {
                $existenteComMesmoPaymentId->metodo_pagamento = $this->asaasGateway->normalizeBillingType(
                    (string) ($paymentData['billingType'] ?? $existenteComMesmoPaymentId->metodo_pagamento)
                );
                $existenteComMesmoPaymentId->asaas_invoice_url = $paymentData['invoiceUrl'] ?? $existenteComMesmoPaymentId->asaas_invoice_url;
                $existenteComMesmoPaymentId->asaas_bank_slip_url = $paymentData['bankSlipUrl'] ?? $existenteComMesmoPaymentId->asaas_bank_slip_url;
                $existenteComMesmoPaymentId->status = $this->mapearStatusAsaas($paymentData['status'] ?? null);
                $existenteComMesmoPaymentId->json_resposta = json_encode($paymentData);
                $existenteComMesmoPaymentId->save();

                return $existenteComMesmoPaymentId;
            }
        }

        $existente = $queryExistente
            ->latest('id')
            ->first();

        if ($existente) {
            if ($paymentId !== '' && empty($existente->asaas_payment_id)) {
                $existente->asaas_payment_id = $paymentId;
            }

            $existente->metodo_pagamento = $this->asaasGateway->normalizeBillingType(
                (string) ($paymentData['billingType'] ?? $existente->metodo_pagamento)
            );
            $existente->asaas_invoice_url = $paymentData['invoiceUrl'] ?? $existente->asaas_invoice_url;
            $existente->asaas_bank_slip_url = $paymentData['bankSlipUrl'] ?? $existente->asaas_bank_slip_url;
            $existente->status = $this->mapearStatusAsaas($paymentData['status'] ?? null);
            $existente->json_resposta = json_encode($paymentData);
            $existente->save();

            return $existente;
        }

        $plano = $assinatura->plano;
        $billingType = $this->asaasGateway->normalizeBillingType((string) ($paymentData['billingType'] ?? $assinatura->metodo_mensal));
        $valor = isset($paymentData['value'])
            ? (float) $paymentData['value']
            : (float) ($assinatura->planoContratado->valor ?? $plano->valor ?? 0);

        return AssinaturaPlanoPagamento::query()->create([
            'id_assinatura_plano' => $assinatura->id,
            'id_empresa' => $assinatura->id_empresa,
            'id_plano' => $assinatura->id_plano,
            'id_plano_contratado' => $assinatura->id_plano_contratado,
            'tipo_cobranca' => AssinaturaPlanoPagamento::TIPO_MENSALIDADE,
            'competencia' => $competencia,
            'metodo_pagamento' => $billingType,
            'asaas_payment_id' => $paymentId !== '' ? $paymentId : null,
            'asaas_invoice_url' => $paymentData['invoiceUrl'] ?? null,
            'asaas_bank_slip_url' => $paymentData['bankSlipUrl'] ?? null,
            'valor' => round($valor, 2),
            'data_vencimento' => $dueDate,
            'status' => $this->mapearStatusAsaas($paymentData['status'] ?? null),
            'json_resposta' => json_encode($paymentData),
            'tentativas' => 0,
            'observacoes' => $this->normalizarObservacaoPagamento('Cobrança mensal gerada pela assinatura recorrente do Asaas.'),
        ]);
    }

    private function mapearStatusAsaas(?string $statusAsaas): string
    {
        $status = strtoupper((string) $statusAsaas);

        return match ($status) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED' => AssinaturaPlanoPagamento::STATUS_PAGO,
            'OVERDUE' => AssinaturaPlanoPagamento::STATUS_VENCIDO,
            'REFUNDED', 'DELETED', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => AssinaturaPlanoPagamento::STATUS_CANCELADO,
            'PENDING', 'AWAITING_RISK_ANALYSIS' => AssinaturaPlanoPagamento::STATUS_PENDENTE,
            default => AssinaturaPlanoPagamento::STATUS_GERADO,
        };
    }

    private function parseAsaasDateTime(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Exception) {
            return null;
        }
    }

    private function tabelasBillingDisponiveis(): bool
    {
        $tabelaContratoDisponivel = Schema::hasTable('client_contracts')
            || Schema::hasTable('empresa_contratos_software');

        return Schema::hasTable('assinaturas_planos')
            && Schema::hasTable('assinaturas_planos_pagamentos')
            && $tabelaContratoDisponivel;
    }
}
