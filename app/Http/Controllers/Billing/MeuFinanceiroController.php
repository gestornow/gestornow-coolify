<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Auth\Models\Empresa;
use App\Http\Controllers\Controller;
use App\Models\AssinaturaPlano;
use App\Models\ClientContract;
use App\Models\Plano;
use App\Models\PlanoContratado;
use App\Services\Billing\AssinaturaPlanoService;
use App\Services\Billing\ContractPdfService;
use App\Services\Billing\PlanoPromocaoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class MeuFinanceiroController extends Controller
{
    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService,
        private readonly PlanoPromocaoService $planoPromocaoService,
        private readonly ContractPdfService $contractPdfService
    ) {
    }

    public function index(Request $request): View
    {
        $empresa = $this->empresaLogada();
        $resumo = $this->assinaturaPlanoService->obterResumoFinanceiroEmpresa((int) $empresa->id_empresa);

        $assinatura = $resumo['assinatura'] ?? null;
        $cartaoDebitoInfo = [
            'confirmado' => false,
            'final' => null,
            'brand' => null,
            'subscription_id' => null,
            'source' => null,
            'error' => null,
        ];

        if ($assinatura) {
            $cartaoDebitoInfo = $this->assinaturaPlanoService->obterInfoCartaoDebito($assinatura);
        }

        $onboardingPendente = $assinatura
            && in_array($assinatura->status, [
                AssinaturaPlano::STATUS_ONBOARDING_DADOS,
                AssinaturaPlano::STATUS_ONBOARDING_CONTRATO,
            ], true);

        $planosDisponiveisUpgrade = collect();
        $contratosRecibos = collect();

        if ($assinatura) {
            $valorMensalAtual = (float) ($assinatura->planoContratado?->valor ?? $assinatura->plano?->valor ?? 0);

            $planosDisponiveisUpgrade = Plano::ativos()
                ->with(['modulos.modulo.moduloPai'])
                ->where('id_plano', '!=', (int) $assinatura->id_plano)
                ->whereNotIn('nome', ['Plano Gestor', 'Gestor'])
                ->orderBy('valor', 'asc')
                ->orderBy('nome', 'asc')
                ->get()
                ->map(function (Plano $plano) use ($empresa, $assinatura, $valorMensalAtual) {
                    $precos = $this->planoPromocaoService->calcularValoresPromocionais($plano, $empresa);
                    $valorMensalNovo = (float) ($precos['valor_mensal_final'] ?? $plano->valor ?? 0);

                    $previewMensalidades = $this->simularProximasMensalidadesTroca(
                        $assinatura,
                        $valorMensalAtual,
                        $valorMensalNovo
                    );

                    return [
                        'plano' => $plano,
                        'precos' => $precos,
                        'recursos' => $this->montarRecursosPlano($plano),
                        'preview_mensalidades' => $previewMensalidades,
                    ];
                })
                ->values();

            $contratosRecibos = ClientContract::query()
                ->where('id_empresa', (int) $empresa->id_empresa)
                ->with(['plano:id_plano,nome', 'planoContratado:id,nome'])
                ->orderByDesc('aceito_em')
                ->orderByDesc('id')
                ->limit(30)
                ->get();
        }

        return view('billing.meu-financeiro', [
            'empresa' => $empresa,
            'assinatura' => $assinatura,
            'cartaoDebitoInfo' => $cartaoDebitoInfo,
            'pagamentosAbertos' => $resumo['pagamentos_abertos'] ?? collect(),
            'historicoPagamentos' => $resumo['historico_pagamentos'] ?? collect(),
            'onboardingPendente' => $onboardingPendente,
            'planosDisponiveisUpgrade' => $planosDisponiveisUpgrade,
            'contratosRecibos' => $contratosRecibos,
        ]);
    }

    public function upgradePlano(Request $request, int $idPlano): RedirectResponse
    {
        $dados = $request->validate([
            'metodo_mensal' => 'required|string|in:BOLETO,CREDIT_CARD,DEBIT_CARD,CARTAO_CREDITO,CARTAO_DEBITO,CREDITO,DEBITO',
            'metodo_adesao' => 'required|string|in:PIX,BOLETO,CREDIT_CARD',
            'card_holderName' => 'nullable|string|max:100',
            'card_number' => 'nullable|string|max:25',
            'card_expiryMonth' => 'nullable|string|max:2',
            'card_expiryYear' => 'nullable|string|max:4',
            'card_ccv' => 'nullable|string|max:4',
            'card_phone' => 'nullable|string|max:20',
            'card_mobilePhone' => 'nullable|string|max:20',
            'card_postalCode' => 'nullable|string|max:10',
            'card_addressNumber' => 'nullable|string|max:20',
            'card_addressComplement' => 'nullable|string|max:100',
            'card_cpfCnpj' => 'nullable|string|max:18',
            'card_email' => 'nullable|email|max:255',
        ]);

        try {
            $empresa = $this->empresaLogada();
            $plano = Plano::ativos()->findOrFail($idPlano);

            $metodoAdesao = strtoupper((string) ($dados['metodo_adesao'] ?? AssinaturaPlano::METODO_BOLETO));
            $cardData = null;

            if ($metodoAdesao === AssinaturaPlano::METODO_CREDIT_CARD) {
                if (empty($dados['card_number']) || empty($dados['card_holderName']) ||
                    empty($dados['card_expiryMonth']) || empty($dados['card_expiryYear']) ||
                    empty($dados['card_ccv'])) {
                    return redirect()
                        ->route('billing.meu-financeiro.index')
                        ->with('error', 'Para pagar a adesao com cartao de credito, informe todos os dados do cartao.');
                }

                $phone = preg_replace('/\D/', '', (string) (!empty($dados['card_phone']) ? $dados['card_phone'] : ($empresa->telefone ?? '')));
                $mobilePhone = preg_replace('/\D/', '', (string) (!empty($dados['card_mobilePhone']) ? $dados['card_mobilePhone'] : ($phone ?: ($empresa->telefone ?? ''))));
                $postalCode = preg_replace('/\D/', '', (string) (!empty($dados['card_postalCode']) ? $dados['card_postalCode'] : ($empresa->cep ?? '')));
                $addressNumber = trim((string) (!empty($dados['card_addressNumber']) ? $dados['card_addressNumber'] : ($empresa->numero ?? '')));
                $addressComplement = trim((string) (!empty($dados['card_addressComplement']) ? $dados['card_addressComplement'] : ($empresa->complemento ?? '')));
                $cpfCnpj = preg_replace('/\D/', '', (string) (!empty($dados['card_cpfCnpj']) ? $dados['card_cpfCnpj'] : ($empresa->cpf ?: $empresa->cnpj)));
                $email = trim((string) (!empty($dados['card_email']) ? $dados['card_email'] : ($empresa->email ?? '')));

                if ($phone === '' && $mobilePhone === '') {
                    return redirect()
                        ->route('billing.meu-financeiro.index')
                        ->with('error', 'Telefone ou celular do titular e obrigatorio para pagamento com cartao.');
                }

                if ($postalCode === '') {
                    return redirect()
                        ->route('billing.meu-financeiro.index')
                        ->with('error', 'CEP do titular e obrigatorio para pagamento com cartao.');
                }

                if ($cpfCnpj === '') {
                    return redirect()
                        ->route('billing.meu-financeiro.index')
                        ->with('error', 'CPF/CNPJ do titular e obrigatorio para pagamento com cartao.');
                }

                $cardData = [
                    'holderName' => $dados['card_holderName'],
                    'number' => preg_replace('/\D/', '', $dados['card_number']),
                    'expiryMonth' => $dados['card_expiryMonth'],
                    'expiryYear' => $dados['card_expiryYear'],
                    'ccv' => $dados['card_ccv'],
                    'phone' => $phone,
                    'mobilePhone' => $mobilePhone,
                    'postalCode' => $postalCode,
                    'addressNumber' => $addressNumber !== '' ? $addressNumber : 'S/N',
                    'addressComplement' => $addressComplement,
                    'cpfCnpj' => $cpfCnpj,
                    'email' => $email,
                ];
            }

            $resultado = $this->assinaturaPlanoService->realizarUpgradePlano($empresa, $plano, [
                'origem' => AssinaturaPlano::ORIGEM_DASHBOARD,
                'metodo_mensal' => $dados['metodo_mensal'],
                'metodo_adesao' => $metodoAdesao,
                'observacoes' => 'Troca de plano solicitada em Meu Financeiro.',
                'card_data' => $cardData,
            ]);

            $mensagem = (string) ($resultado['mensagem'] ?? 'Plano alterado com sucesso.');

            if (!empty($resultado['promocao_aplicada']['nome'])) {
                $mensagem .= ' Promocao aplicada: ' . $resultado['promocao_aplicada']['nome'] . '.';
            }

            $reciboUrl = null;
            $contratoTroca = null;
            $falhaRecibo = false;

            try {
                $contratoTroca = $this->registrarContratoTrocaPlano($empresa, $plano, $resultado, $request);
            } catch (\Throwable $reciboException) {
                $falhaRecibo = true;

                $this->logWarningSeguro('Nao foi possivel registrar contrato/recibo completo da troca de plano no Meu Financeiro. Tentando fallback simplificado.', [
                    'id_empresa' => $empresa->id_empresa,
                    'id_plano_novo' => $plano->id_plano,
                    'erro' => $reciboException->getMessage(),
                ]);

                $contratoTroca = $this->registrarContratoTrocaPlanoSimplificado($empresa, $plano, $resultado, $request);

                if (!$contratoTroca) {
                    $contratoTroca = ClientContract::query()
                        ->where('id_empresa', (int) $empresa->id_empresa)
                        ->orderByDesc('aceito_em')
                        ->orderByDesc('id')
                        ->first();
                }
            }

            if ($contratoTroca) {
                $reciboUrl = route('billing.contrato.recibo', ['id' => $contratoTroca->id]);

                if ($falhaRecibo) {
                    $mensagem .= ' A migracao foi formalizada e o recibo sera gerado no download.';
                }
            } elseif ($falhaRecibo) {
                $mensagem .= ' A migracao foi concluida e formalizada, porem o recibo nao ficou disponivel imediatamente.';
            }

            $redirect = redirect()
                ->route('billing.meu-financeiro.index')
                ->with('success', $mensagem);

            if ($reciboUrl) {
                $redirect->with('recibo_url', $reciboUrl);
            }

            return $redirect;
        } catch (Exception $e) {
            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('error', 'Nao foi possivel alterar o plano: ' . $e->getMessage());
        }
    }

    public function cancelarAssinatura(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'motivo_cancelamento' => 'nullable|string|max:255',
        ]);

        try {
            $empresa = $this->empresaLogada();

            $resultado = $this->assinaturaPlanoService->solicitarCancelamentoFimDoPeriodo(
                $empresa,
                $dados['motivo_cancelamento'] ?? null
            );

            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('warning', (string) ($resultado['mensagem'] ?? 'Cancelamento agendado com sucesso.'));
        } catch (Exception $e) {
            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('error', 'Nao foi possivel agendar o cancelamento: ' . $e->getMessage());
        }
    }

    public function atualizarMetodoMensal(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'metodo_mensal' => 'required|string|in:BOLETO,CREDIT_CARD,DEBIT_CARD,CARTAO_CREDITO,CARTAO_DEBITO,CREDITO,DEBITO',
        ]);

        $metodo = strtoupper((string) $dados['metodo_mensal']);

        try {
            $empresa = $this->empresaLogada();
            $this->assinaturaPlanoService->atualizarMetodoMensal($empresa, $metodo);

            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('success', 'Método de cobrança mensal atualizado com sucesso.');
        } catch (Exception $e) {
            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('error', 'Não foi possível atualizar o método mensal: ' . $e->getMessage());
        }
    }

    public function atualizarMetodoAdesao(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'metodo_adesao' => 'required|string|in:PIX,BOLETO,CREDIT_CARD',
            'card_holderName' => 'nullable|string|max:100',
            'card_number' => 'nullable|string|max:25',
            'card_expiryMonth' => 'nullable|string|max:2',
            'card_expiryYear' => 'nullable|string|max:4',
            'card_ccv' => 'nullable|string|max:4',
            'card_phone' => 'nullable|string|max:20',
            'card_mobilePhone' => 'nullable|string|max:20',
            'card_postalCode' => 'nullable|string|max:10',
            'card_addressNumber' => 'nullable|string|max:20',
            'card_addressComplement' => 'nullable|string|max:100',
            'card_cpfCnpj' => 'nullable|string|max:18',
            'card_email' => 'nullable|email|max:255',
        ]);

        $metodo = strtoupper((string) $dados['metodo_adesao']);

        // Se for cartão, validar dados do cartão
        $cardData = null;
        if ($metodo === 'CREDIT_CARD') {
            if (empty($dados['card_number']) || empty($dados['card_holderName']) ||
                empty($dados['card_expiryMonth']) || empty($dados['card_expiryYear']) ||
                empty($dados['card_ccv'])) {
                return redirect()
                    ->route('billing.meu-financeiro.index')
                    ->with('error', 'Para pagar com cartão de crédito, informe todos os dados do cartão.');
            }

            $empresa = $this->empresaLogada();

            // Usar dados do formulário ou da empresa
            $phone = preg_replace('/\D/', '', (string) (!empty($dados['card_phone']) ? $dados['card_phone'] : ($empresa->telefone ?? '')));
            $mobilePhone = preg_replace('/\D/', '', (string) (!empty($dados['card_mobilePhone']) ? $dados['card_mobilePhone'] : ($phone ?: ($empresa->telefone ?? ''))));
            $postalCode = preg_replace('/\D/', '', (string) (!empty($dados['card_postalCode']) ? $dados['card_postalCode'] : ($empresa->cep ?? '')));
            $addressNumber = trim((string) (!empty($dados['card_addressNumber']) ? $dados['card_addressNumber'] : ($empresa->numero ?? '')));
            $addressComplement = trim((string) (!empty($dados['card_addressComplement']) ? $dados['card_addressComplement'] : ($empresa->complemento ?? '')));
            $cpfCnpj = preg_replace('/\D/', '', (string) (!empty($dados['card_cpfCnpj']) ? $dados['card_cpfCnpj'] : ($empresa->cpf ?: $empresa->cnpj)));
            $email = trim((string) (!empty($dados['card_email']) ? $dados['card_email'] : ($empresa->email ?? '')));

            // Validar dados obrigatórios do titular
            if ($phone === '' && $mobilePhone === '') {
                return redirect()
                    ->route('billing.meu-financeiro.index')
                    ->with('error', 'Telefone ou celular do titular é obrigatório para pagamento com cartão.');
            }

            if ($postalCode === '') {
                return redirect()
                    ->route('billing.meu-financeiro.index')
                    ->with('error', 'CEP do titular é obrigatório para pagamento com cartão.');
            }

            if ($cpfCnpj === '') {
                return redirect()
                    ->route('billing.meu-financeiro.index')
                    ->with('error', 'CPF/CNPJ do titular é obrigatório para pagamento com cartão.');
            }

            $cardData = [
                'holderName' => $dados['card_holderName'],
                'number' => preg_replace('/\D/', '', $dados['card_number']),
                'expiryMonth' => $dados['card_expiryMonth'],
                'expiryYear' => $dados['card_expiryYear'],
                'ccv' => $dados['card_ccv'],
                'phone' => $phone,
                'mobilePhone' => $mobilePhone,
                'postalCode' => $postalCode,
                'addressNumber' => $addressNumber !== '' ? $addressNumber : 'S/N',
                'addressComplement' => $addressComplement,
                'cpfCnpj' => $cpfCnpj,
                'email' => $email,
            ];
        }

        try {
            $empresa = $empresa ?? $this->empresaLogada();
            $this->assinaturaPlanoService->alterarMetodoAdesao($empresa, $metodo, $cardData);

            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('success', 'Método de pagamento da adesão alterado com sucesso.');
        } catch (Exception $e) {
            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('error', 'Não foi possível alterar o método: ' . $e->getMessage());
        }
    }

    public function cadastrarCartao(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'holderName' => 'required|string|max:100',
            'number' => 'required|string|min:13|max:19',
            'expiryMonth' => 'required|string|size:2',
            'expiryYear' => 'required|string|size:4',
            'ccv' => 'required|string|min:3|max:4',
            'phone' => 'nullable|string|max:15',
            'mobilePhone' => 'nullable|string|max:15',
            'postalCode' => 'nullable|string|max:10',
            'addressNumber' => 'nullable|string|max:20',
            'addressComplement' => 'nullable|string|max:100',
            'cpfCnpj' => 'nullable|string|max:18',
            'email' => 'nullable|email|max:255',
        ]);

        try {
            $empresa = $this->empresaLogada();

            $assinatura = $this->assinaturaPlanoService->obterAssinaturaEmpresa((int) $empresa->id_empresa);
            if (!$assinatura || strtoupper((string) $assinatura->metodo_mensal) !== AssinaturaPlano::METODO_CREDIT_CARD) {
                return response()->json([
                    'success' => false,
                    'message' => 'Altere o metodo mensal para cartao de credito antes de cadastrar o cartao para debito automatico.',
                ], 422);
            }

            // Usar dados do formulário ou da empresa
            $phone = preg_replace('/\D/', '', (string) (!empty($dados['phone']) ? $dados['phone'] : ($empresa->telefone ?? '')));
            $mobilePhone = preg_replace('/\D/', '', (string) (!empty($dados['mobilePhone']) ? $dados['mobilePhone'] : ($phone ?: ($empresa->telefone ?? ''))));
            $postalCode = preg_replace('/\D/', '', (string) (!empty($dados['postalCode']) ? $dados['postalCode'] : ($empresa->cep ?? '')));
            $addressNumber = trim((string) (!empty($dados['addressNumber']) ? $dados['addressNumber'] : ($empresa->numero ?? '')));
            $addressComplement = trim((string) (!empty($dados['addressComplement']) ? $dados['addressComplement'] : ($empresa->complemento ?? '')));
            $cpfCnpj = preg_replace('/\D/', '', (string) (!empty($dados['cpfCnpj']) ? $dados['cpfCnpj'] : ($empresa->cpf ?: $empresa->cnpj)));
            $email = trim((string) (!empty($dados['email']) ? $dados['email'] : ($empresa->email ?? '')));

            // Validar dados obrigatórios
            if ($phone === '' && $mobilePhone === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Telefone ou celular do titular é obrigatório para cadastrar o cartão.',
                ], 422);
            }

            if ($postalCode === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'CEP do titular é obrigatório para cadastrar o cartão.',
                ], 422);
            }

            if ($cpfCnpj === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'CPF/CNPJ do titular é obrigatório para cadastrar o cartão.',
                ], 422);
            }

            $assinatura = $this->assinaturaPlanoService->atualizarMetodoMensalCartao($empresa, [
                'holderName' => $dados['holderName'],
                'number' => $dados['number'],
                'expiryMonth' => $dados['expiryMonth'],
                'expiryYear' => $dados['expiryYear'],
                'ccv' => $dados['ccv'],
                'phone' => $phone,
                'mobilePhone' => $mobilePhone,
                'postalCode' => $postalCode,
                'addressNumber' => $addressNumber !== '' ? $addressNumber : 'S/N',
                'addressComplement' => $addressComplement,
                'cpfCnpj' => $cpfCnpj,
                'email' => $email,
            ]);

            $subscriptionId = trim((string) ($assinatura->asaas_subscription_id ?? ''));

            $mensagem = 'Cartão cadastrado e método de cobrança atualizado com sucesso.';

            if ($subscriptionId !== '') {
                $mensagem .= ' Asaas confirmado. Assinatura: ' . $subscriptionId . '.';
            }

            session()->flash('success', $mensagem);

            return response()->json([
                'success' => true,
                'message' => $mensagem,
                'asaas_subscription_id' => $subscriptionId,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Não foi possível cadastrar o cartão: ' . $e->getMessage(),
            ], 422);
        }
    }

    private function empresaLogada(): Empresa
    {
        return Empresa::findOrFail((int) (session('id_empresa') ?: Auth::user()->id_empresa));
    }

    private function registrarContratoTrocaPlano(Empresa $empresa, Plano $novoPlano, array $resultado, Request $request): ?ClientContract
    {
        $assinatura = $resultado['assinatura'] ?? $this->assinaturaPlanoService->obterAssinaturaEmpresa((int) $empresa->id_empresa);

        if ($assinatura instanceof AssinaturaPlano) {
            $assinatura->loadMissing(['planoContratado']);
        }

        $tipoTroca = strtolower((string) ($resultado['tipo_troca'] ?? 'upgrade'));
        $valorMensalAtual = (float) ($resultado['valor_mensal_atual'] ?? 0);
        $valorMensalNovo = (float) ($resultado['valor_mensal_novo'] ?? $novoPlano->valor ?? 0);
        $valorAdesaoTroca = (float) ($resultado['valor_adesao_cobranca'] ?? 0);
        $estrategiaMensalidade = (string) ($resultado['estrategia_mensalidade']['codigo'] ?? '');

        $recursosPlano = $this->montarRecursosPlano($novoPlano);
        $limitesContrato = [];

        foreach ($recursosPlano as $indice => $recurso) {
            $limitesContrato['recurso_' . ($indice + 1)] = $recurso;
        }

        if ($estrategiaMensalidade !== '') {
            $limitesContrato['estrategia_mensalidade'] = $estrategiaMensalidade;
        }

        $limitesContrato['tipo_troca'] = strtoupper($tipoTroca);

        $nomeAssinante = trim((string) (Auth::user()->nome ?? $empresa->razao_social ?? $empresa->nome_empresa ?? 'Responsavel'));
        $documentoAssinante = preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf ?: ''));
        $emailAssinante = trim((string) (Auth::user()->email ?? $empresa->email ?? ''));
        $aceitoEm = now();

        $corpoContrato = $this->montarCorpoContratoTrocaPlano(
            empresa: $empresa,
            plano: $novoPlano,
            recursosPlano: $recursosPlano,
            tipoTroca: $tipoTroca,
            valorMensalAtual: $valorMensalAtual,
            valorMensalNovo: $valorMensalNovo,
            valorAdesaoTroca: $valorAdesaoTroca,
            estrategiaMensalidade: $estrategiaMensalidade
        );

        $dadosHash = [
            'id_empresa' => $empresa->id_empresa,
            'cliente_razao_social' => $empresa->razao_social ?? $empresa->nome_empresa,
            'cliente_cnpj_cpf' => $empresa->cnpj ?? $empresa->cpf,
            'valor_adesao' => $valorAdesaoTroca,
            'valor_mensalidade' => $valorMensalNovo,
            'limites_contratados' => $limitesContrato,
            'versao_contrato' => '1.1',
            'corpo_contrato' => $corpoContrato,
            'aceito_em' => $aceitoEm->toIso8601String(),
        ];

        $hashDocumento = ClientContract::calcularHash($dadosHash);

        ClientContract::where('id_empresa', (int) $empresa->id_empresa)
            ->where('status', ClientContract::STATUS_ATIVO)
            ->update([
                'status' => ClientContract::STATUS_SUBSTITUIDO,
                'motivo_revogacao' => 'Substituido por troca de plano em ' . now()->format('d/m/Y H:i:s'),
                'revogado_em' => now(),
            ]);

        $idPlanoContratado = (int) ($assinatura instanceof AssinaturaPlano ? ($assinatura->id_plano_contratado ?? 0) : 0);

        if ($idPlanoContratado <= 0) {
            $planoContratadoResultado = $resultado['plano_contratado'] ?? null;
            $idPlanoContratado = (int) (is_object($planoContratadoResultado)
                ? ((int) ($planoContratadoResultado->id ?? 0))
                : 0);
        }

        if ($idPlanoContratado <= 0) {
            $idPlanoContratado = (int) PlanoContratado::query()
                ->where('id_empresa', (int) $empresa->id_empresa)
                ->orderByDesc('id')
                ->value('id');
        }

        $contrato = ClientContract::create([
            'id_empresa' => (int) $empresa->id_empresa,
            'id_plano' => (int) $novoPlano->id_plano,
            'id_plano_contratado' => $idPlanoContratado > 0 ? $idPlanoContratado : null,
            'cliente_razao_social' => $empresa->razao_social ?? $empresa->nome_empresa,
            'cliente_cnpj_cpf' => $empresa->cnpj ?? $empresa->cpf,
            'cliente_email' => $empresa->email,
            'cliente_endereco' => $this->montarEnderecoCompleto($empresa),
            'valor_adesao' => $valorAdesaoTroca,
            'valor_mensalidade' => $valorMensalNovo,
            'limites_contratados' => $limitesContrato,
            'versao_contrato' => '1.1',
            'titulo_contrato' => 'Termo Aditivo de Troca de Plano - Gestor Now',
            'corpo_contrato' => $corpoContrato,
            'hash_documento' => $hashDocumento,
            'assinatura_base64' => 'texto:' . $nomeAssinante,
            'assinado_por_nome' => $nomeAssinante,
            'assinado_por_documento' => $documentoAssinante,
            'assinado_por_email' => $emailAssinante !== '' ? $emailAssinante : $empresa->email,
            'ip_aceite' => (string) $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'aceito_em' => $aceitoEm,
            'status' => ClientContract::STATUS_ATIVO,
        ]);

        try {
            $reciboPath = $this->contractPdfService->gerarReciboAdesao($contrato);
            $contrato->registrarReciboGerado($reciboPath);
        } catch (\Throwable $e) {
            $this->logWarningSeguro('Contrato de troca salvo, mas a geracao automatica do recibo falhou. O download sob demanda permanece disponivel.', [
                'id_empresa' => (int) $empresa->id_empresa,
                'contrato_id' => (int) $contrato->id,
                'erro' => $e->getMessage(),
            ]);
        }

        return $contrato->fresh();
    }

    private function registrarContratoTrocaPlanoSimplificado(Empresa $empresa, Plano $novoPlano, array $resultado, Request $request): ?ClientContract
    {
        try {
            $contratoRecente = ClientContract::query()
                ->where('id_empresa', (int) $empresa->id_empresa)
                ->where('id_plano', (int) $novoPlano->id_plano)
                ->where('status', ClientContract::STATUS_ATIVO)
                ->where('aceito_em', '>=', now()->subMinutes(10))
                ->orderByDesc('id')
                ->first();

            if ($contratoRecente) {
                return $contratoRecente;
            }

            $tipoTroca = strtolower((string) ($resultado['tipo_troca'] ?? 'upgrade'));
            $valorMensalAtual = (float) ($resultado['valor_mensal_atual'] ?? 0);
            $valorMensalNovo = (float) ($resultado['valor_mensal_novo'] ?? $novoPlano->valor ?? 0);
            $valorAdesaoTroca = (float) ($resultado['valor_adesao_cobranca'] ?? 0);
            $aceitoEm = now();

            $nomeAssinante = trim((string) (Auth::user()->nome ?? $empresa->razao_social ?? $empresa->nome_empresa ?? 'Responsavel'));
            $documentoAssinante = preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf ?: ''));
            $documentoCliente = (string) preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf ?: $documentoAssinante));

            $limitesContrato = [
                'tipo_troca' => strtoupper($tipoTroca),
                'mensalidade_anterior' => number_format($valorMensalAtual, 2, '.', ''),
                'mensalidade_nova' => number_format($valorMensalNovo, 2, '.', ''),
                'adesao_troca' => number_format($valorAdesaoTroca, 2, '.', ''),
                'registro' => 'termo_simplificado_migracao',
            ];

            $corpoContrato = implode("\n", [
                'TERMO SIMPLIFICADO DE MIGRACAO DE PLANO - GESTOR NOW',
                '',
                'Tipo da troca: ' . strtoupper($tipoTroca),
                'Plano contratado: ' . (string) ($novoPlano->nome ?? 'Plano nao informado'),
                'Valor mensal anterior: R$ ' . number_format($valorMensalAtual, 2, ',', '.'),
                'Novo valor mensal: R$ ' . number_format($valorMensalNovo, 2, ',', '.'),
                'Valor de adesao da troca: R$ ' . number_format($valorAdesaoTroca, 2, ',', '.'),
                'Data da formalizacao: ' . $aceitoEm->format('d/m/Y H:i:s'),
                '',
                'Este termo foi aceito eletronicamente e possui validade juridica conforme Lei 14.063/2020 e MP 2.200-2/2001.',
            ]);

            $hashDocumento = ClientContract::calcularHash([
                'id_empresa' => (int) $empresa->id_empresa,
                'cliente_razao_social' => $empresa->razao_social ?? $empresa->nome_empresa,
                'cliente_cnpj_cpf' => $documentoCliente,
                'valor_adesao' => $valorAdesaoTroca,
                'valor_mensalidade' => $valorMensalNovo,
                'limites_contratados' => $limitesContrato,
                'versao_contrato' => '1.1',
                'corpo_contrato' => $corpoContrato,
                'aceito_em' => $aceitoEm->toIso8601String(),
            ]);

            ClientContract::query()
                ->where('id_empresa', (int) $empresa->id_empresa)
                ->where('status', ClientContract::STATUS_ATIVO)
                ->update([
                    'status' => ClientContract::STATUS_SUBSTITUIDO,
                    'motivo_revogacao' => 'Substituido por termo simplificado de migracao em ' . now()->format('d/m/Y H:i:s'),
                    'revogado_em' => now(),
                ]);

            $contrato = ClientContract::create([
                'id_empresa' => (int) $empresa->id_empresa,
                'id_plano' => (int) $novoPlano->id_plano,
                'id_plano_contratado' => null,
                'cliente_razao_social' => $empresa->razao_social ?? $empresa->nome_empresa,
                'cliente_cnpj_cpf' => $documentoCliente,
                'cliente_email' => $empresa->email,
                'cliente_endereco' => $this->montarEnderecoCompleto($empresa),
                'valor_adesao' => $valorAdesaoTroca,
                'valor_mensalidade' => $valorMensalNovo,
                'limites_contratados' => $limitesContrato,
                'versao_contrato' => '1.1',
                'titulo_contrato' => 'Termo Simplificado de Migracao de Plano - Gestor Now',
                'corpo_contrato' => $corpoContrato,
                'hash_documento' => $hashDocumento,
                'assinatura_base64' => 'texto:' . $nomeAssinante,
                'assinado_por_nome' => $nomeAssinante,
                'assinado_por_documento' => $documentoAssinante,
                'assinado_por_email' => trim((string) (Auth::user()->email ?? $empresa->email ?? '')),
                'ip_aceite' => (string) $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'aceito_em' => $aceitoEm,
                'status' => ClientContract::STATUS_ATIVO,
            ]);

            return $contrato->fresh();
        } catch (\Throwable $e) {
            $this->logWarningSeguro('Falha ao registrar termo simplificado de migracao de plano.', [
                'id_empresa' => (int) $empresa->id_empresa,
                'id_plano_novo' => (int) $novoPlano->id_plano,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function logWarningSeguro(string $mensagem, array $contexto = []): void
    {
        try {
            Log::warning($mensagem, $contexto);
        } catch (\Throwable) {
            // Evita quebrar o fluxo por falha de log em ambiente restrito.
        }
    }

    private function montarCorpoContratoTrocaPlano(
        Empresa $empresa,
        Plano $plano,
        array $recursosPlano,
        string $tipoTroca,
        float $valorMensalAtual,
        float $valorMensalNovo,
        float $valorAdesaoTroca,
        string $estrategiaMensalidade
    ): string {
        $linhasRecursos = array_map(static fn (string $recurso): string => '- ' . $recurso, $recursosPlano);

        return implode("\n", array_merge([
            'TERMO ADITIVO DE TROCA DE PLANO - GESTOR NOW',
            '',
            'Tipo da troca: ' . strtoupper($tipoTroca),
            'Data da formalizacao: ' . now()->format('d/m/Y H:i:s'),
            '',
            'CONTRATANTE',
            'Razao Social/Nome: ' . ($empresa->razao_social ?? $empresa->nome_empresa),
            'Documento: ' . ($empresa->cnpj ?? $empresa->cpf ?? '-'),
            'Endereco: ' . ($this->montarEnderecoCompleto($empresa) ?: '-'),
            'E-mail: ' . ($empresa->email ?? '-'),
            '',
            'DETALHES DA TROCA',
            'Plano contratado: ' . (string) ($plano->nome ?? 'Plano nao informado'),
            'Valor mensal anterior: R$ ' . number_format($valorMensalAtual, 2, ',', '.'),
            'Novo valor mensal: R$ ' . number_format($valorMensalNovo, 2, ',', '.'),
            'Valor de adesao da troca: R$ ' . number_format($valorAdesaoTroca, 2, ',', '.'),
            'Estrategia de mensalidade: ' . $this->descricaoEstrategiaMensalidade($estrategiaMensalidade),
            '',
            'RECURSOS DO PLANO',
        ], $linhasRecursos, [
            '',
            'Este termo foi aceito eletronicamente e possui validade juridica conforme Lei 14.063/2020 e MP 2.200-2/2001.',
        ]));
    }

    private function descricaoEstrategiaMensalidade(string $codigo): string
    {
        return match ($codigo) {
            'cancelar_proxima_e_iniciar_novo_valor' => 'Substitui a proxima mensalidade pelo novo valor',
            'manter_proxima_antiga_e_iniciar_apos_proxima' => 'Mantem a proxima mensalidade atual e aplica o novo valor no ciclo seguinte',
            default => 'Aplicacao padrao da regra de migracao',
        };
    }

    private function montarRecursosPlano(Plano $plano): array
    {
        $nomePlanoNormalizado = strtolower(trim((string) $plano->nome));
        $nomePlanoNormalizado = preg_replace('/^plano\s+/i', '', $nomePlanoNormalizado);

        $recursosFixosPorPlano = [
            'start' => [
                'Clientes - Limite: 500',
                'Produtos - Limite: 500',
                'Locações Completas',
                '1 Modelo de contrato',
                'Financeiro Completo',
                'Sem emissão de Boleto',
                'Usuários - Limite: 1',
            ],
            'pro' => [
                'Clientes - Limite: 1.500',
                'Produtos - Limite: 1.500',
                'Locações Completas',
                'Modelos de contratos ilimitados',
                'Financeiro Completo',
                '1 banco pra boleto',
                'Usuários - Limite: 3',
            ],
            'plus' => [
                'Clientes - Limite: 3.000',
                'Produtos - Limite: 3.000',
                'Locações Completas',
                'Modelos de contratos ilimitados',
                'Financeiro Completo',
                'Bancos pra Boletos Ilimitados',
                'Usuários - Limite: 10',
            ],
            'premium' => [
                'Clientes - Ilimitado',
                'Produtos - Ilimitado',
                'Locações Completas',
                'Modelos de contratos ilimitados',
                'Financeiro Completo',
                'Bancos pra Boletos Ilimitados',
                'Usuários - Ilimitado',
            ],
        ];

        if (array_key_exists($nomePlanoNormalizado, $recursosFixosPorPlano)) {
            return $recursosFixosPorPlano[$nomePlanoNormalizado];
        }

        $modulosPlanoRaw = $plano->relationLoaded('modulos')
            ? $plano->modulos->filter(fn ($item) => !empty($item->modulo))
            : $plano->modulos()->with(['modulo.moduloPai'])->get()->filter(fn ($item) => !empty($item->modulo));

        if ($modulosPlanoRaw->isEmpty()) {
            return ['Sem módulos configurados para este plano.'];
        }

        $idsComFilhosNoPlano = $modulosPlanoRaw
            ->map(function ($moduloPlano) {
                return !empty($moduloPlano->modulo->moduloPai)
                    ? (string) $moduloPlano->modulo->moduloPai->id_modulo
                    : null;
            })
            ->filter(function ($idPai) {
                return !empty($idPai);
            })
            ->map(function ($idPai) {
                return (string) $idPai;
            })
            ->unique();

        $modulosExcluidos = ['dashboard'];

        $modulosAgrupados = $modulosPlanoRaw
            ->filter(function ($moduloPlano) use ($modulosExcluidos) {
                $nomeModulo = strtolower(trim((string) ($moduloPlano->modulo->nome ?? '')));

                return !in_array($nomeModulo, $modulosExcluidos, true);
            })
            ->map(function ($moduloPlano) use ($idsComFilhosNoPlano) {
                $modulo = $moduloPlano->modulo;
                $nomeOriginal = trim((string) ($modulo->nome ?? ''));
                $nomeGrupo = $nomeOriginal;
                $ordemGrupo = (int) ($modulo->ordem ?? 9999);
                $categoriaGrupo = (int) ($modulo->categoria ?? 9999);
                $grupoId = null;

                if (!empty($modulo->moduloPai)) {
                    $grupoId = (string) $modulo->moduloPai->id_modulo;
                    $nomeGrupo = trim((string) ($modulo->moduloPai->nome ?? $nomeOriginal));
                    $ordemGrupo = (int) ($modulo->moduloPai->ordem ?? $ordemGrupo);
                    $categoriaGrupo = (int) ($modulo->moduloPai->categoria ?? $categoriaGrupo);
                } elseif ($idsComFilhosNoPlano->contains((string) $modulo->id_modulo)) {
                    $grupoId = (string) $modulo->id_modulo;
                } else {
                    $grupoId = 'nome:' . strtolower($nomeGrupo);
                }

                return [
                    'grupo_id' => $grupoId,
                    'nome' => $nomeGrupo,
                    'categoria' => $categoriaGrupo,
                    'ordem' => $ordemGrupo,
                    'limite' => is_numeric($moduloPlano->limite) ? (int) $moduloPlano->limite : null,
                ];
            })
            ->groupBy('grupo_id')
            ->map(function ($itens) {
                $primeiro = $itens->first();
                $ordemGrupo = $itens->pluck('ordem')->filter(fn ($o) => !is_null($o))->min();
                $categoriaGrupo = $itens->pluck('categoria')->filter(fn ($c) => !is_null($c))->min();

                $limiteSelecionado = $itens->pluck('limite')
                    ->filter(function ($limite) {
                        return !is_null($limite) && (int) $limite > 0;
                    })
                    ->min();

                return [
                    'nome' => $primeiro['nome'],
                    'categoria' => !is_null($categoriaGrupo) ? (int) $categoriaGrupo : 9999,
                    'ordem' => !is_null($ordemGrupo) ? (int) $ordemGrupo : 9999,
                    'limite' => is_null($limiteSelecionado) ? null : (int) $limiteSelecionado,
                ];
            })
            ->sortBy(function ($item) {
                $categoria = str_pad((int) ($item['categoria'] ?? 9999), 5, '0', STR_PAD_LEFT);
                $ordem = str_pad((int) ($item['ordem'] ?? 9999), 5, '0', STR_PAD_LEFT);

                return $categoria . '-' . $ordem . '-' . strtolower((string) $item['nome']);
            })
            ->values();

        $temBoletos = false;
        $recursosPlano = [];

        foreach ($modulosAgrupados as $moduloInfo) {
            $nomeModulo = (string) ($moduloInfo['nome'] ?? '');
            $nomeModuloLower = strtolower($nomeModulo);
            $limiteModulo = $moduloInfo['limite'] ?? null;

            if (str_contains($nomeModuloLower, 'boleto')) {
                $temBoletos = true;

                if (!is_null($limiteModulo) && (int) $limiteModulo > 0) {
                    $recursosPlano[] = $nomeModulo . ' - Limite: ' . number_format((int) $limiteModulo, 0, ',', '.') . ' Bancos';
                } else {
                    $recursosPlano[] = $nomeModulo . ' - Limite: Ilimitado';
                }

                continue;
            }

            if (!is_null($limiteModulo) && (int) $limiteModulo > 0) {
                $recursosPlano[] = $nomeModulo . ' - Limite: ' . number_format((int) $limiteModulo, 0, ',', '.');
            } else {
                $recursosPlano[] = $nomeModulo . ' - Limite: Ilimitado';
            }
        }

        if (!$temBoletos) {
            $recursosPlano[] = 'Sem emissão de Boleto';
        }

        return $recursosPlano;
    }

    private function simularProximasMensalidadesTroca(AssinaturaPlano $assinatura, float $valorMensalAtual, float $valorMensalNovo): array
    {
        $hoje = now()->startOfDay();

        $ultimaMensalidadePaga = $assinatura->pagamentos()
            ->where('tipo_cobranca', 'mensalidade')
            ->where('status', 'pago')
            ->where(function ($query) {
                $query->whereNotNull('data_pagamento')
                    ->orWhereNotNull('data_vencimento');
            })
            ->orderByDesc('data_pagamento')
            ->orderByDesc('data_vencimento')
            ->first();

        $dataUltimaPaga = $ultimaMensalidadePaga
            ? 
                ($ultimaMensalidadePaga->data_pagamento
                    ? \Carbon\Carbon::parse((string) $ultimaMensalidadePaga->data_pagamento)->startOfDay()
                    : \Carbon\Carbon::parse((string) $ultimaMensalidadePaga->data_vencimento)->startOfDay())
            : ($assinatura->ultimo_pagamento_em
                ? \Carbon\Carbon::parse((string) $assinatura->ultimo_pagamento_em)->startOfDay()
                : null);

        $proximaAberta = $assinatura->pagamentos()
            ->where('tipo_cobranca', 'mensalidade')
            ->whereNotIn('status', ['pago', 'cancelado'])
            ->whereNotNull('data_vencimento')
            ->orderBy('data_vencimento')
            ->first();

        $dataProxima = $proximaAberta
            ? \Carbon\Carbon::parse((string) $proximaAberta->data_vencimento)->startOfDay()
            : ($assinatura->proxima_cobranca_em
                ? \Carbon\Carbon::parse((string) $assinatura->proxima_cobranca_em)->startOfDay()
                : null);

        if (!$dataProxima) {
            $base = $dataUltimaPaga ?: $hoje;
            $dataProxima = $base->copy()->addDays(30);
        }

        if (!$dataUltimaPaga) {
            $dataUltimaPaga = $dataProxima->copy()->subDays(30);
        }

        $distanciaUltima = abs($hoje->diffInDays($dataUltimaPaga, false));
        $distanciaProxima = abs($hoje->diffInDays($dataProxima, false));

        $maisPertoUltima = $distanciaUltima < $distanciaProxima;

        $primeiraData = $dataProxima->copy();
        $segundaData = $dataProxima->copy()->addDays(30);

        $primeiroValor = $maisPertoUltima ? $valorMensalNovo : $valorMensalAtual;
        $segundoValor = $valorMensalNovo;

        return [
            'estrategia' => $maisPertoUltima
                ? 'Substitui proxima pelo novo valor'
                : 'Mantem proxima atual e aplica novo valor depois',
            'primeira' => [
                'data' => $primeiraData->toDateString(),
                'valor' => round($primeiroValor, 2),
            ],
            'segunda' => [
                'data' => $segundaData->toDateString(),
                'valor' => round($segundoValor, 2),
            ],
        ];
    }
}
