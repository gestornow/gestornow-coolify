<?php

namespace App\Http\Controllers\Billing;

use App\Domain\Auth\Models\Empresa;
use App\Http\Controllers\Controller;
use App\Models\AssinaturaPlano;
use App\Models\Plano;
use App\Services\Billing\AssinaturaPlanoService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AssinaturaController extends Controller
{
    public function __construct(
        private readonly AssinaturaPlanoService $assinaturaPlanoService
    ) {
    }

    /**
     * Fluxo self-service a partir do dashboard.
     */
    public function assinarDashboard(Request $request, int $idPlano): RedirectResponse
    {
        $request->validate([
            'metodo_adesao' => 'nullable|string|in:BOLETO,PIX,CREDIT_CARD,DEBIT_CARD,CARTAO_CREDITO,CARTAO_DEBITO,CREDITO,DEBITO',
            'metodo_mensal' => 'nullable|string|in:BOLETO,CREDIT_CARD,DEBIT_CARD,CARTAO_CREDITO,CARTAO_DEBITO,CREDITO,DEBITO',
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

        $empresa = Empresa::findOrFail((int) (session('id_empresa') ?: Auth::user()->id_empresa));
        $plano = Plano::ativos()->findOrFail($idPlano);

        $metodoAdesao = (string) $request->input('metodo_adesao', AssinaturaPlano::METODO_PIX);
        $metodoMensal = (string) $request->input('metodo_mensal', AssinaturaPlano::METODO_BOLETO);

        // Se o método for cartão de crédito, coletar os dados do cartão
        $cardData = null;
        if ($metodoMensal === 'CREDIT_CARD' || $metodoAdesao === 'CREDIT_CARD') {
            $cardNumber = $request->input('card_number');
            $cardHolderName = $request->input('card_holderName');
            $cardMonth = $request->input('card_expiryMonth');
            $cardYear = $request->input('card_expiryYear');
            $cardCcv = $request->input('card_ccv');
            $cardPhone = $request->input('card_phone') ?: $empresa->telefone;
            $cardMobilePhone = $request->input('card_mobilePhone') ?: ($cardPhone ?: $empresa->telefone);
            $cardPostalCode = $request->input('card_postalCode') ?: $empresa->cep;
            $cardAddressNumber = $request->input('card_addressNumber') ?: $empresa->numero;
            $cardAddressComplement = $request->input('card_addressComplement') ?: $empresa->complemento;
            $cardCpfCnpj = $request->input('card_cpfCnpj') ?: ($empresa->cpf ?: $empresa->cnpj);
            $cardEmail = $request->input('card_email') ?: $empresa->email;

            if (!$cardNumber || !$cardHolderName || !$cardMonth || !$cardYear || !$cardCcv) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'Ao escolher cartão de crédito, é necessário informar todos os dados do cartão.');
            }

            // Validar dados obrigatórios do titular
            if (empty($cardPhone) && empty($cardMobilePhone)) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'Telefone ou celular do titular é obrigatório para pagamento com cartão.');
            }

            if (empty($cardPostalCode)) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'CEP do titular é obrigatório para pagamento com cartão.');
            }

            if (empty($cardCpfCnpj)) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'CPF/CNPJ do titular é obrigatório para pagamento com cartão.');
            }

            $cardData = [
                'holderName' => $cardHolderName,
                'number' => preg_replace('/\D/', '', $cardNumber),
                'expiryMonth' => $cardMonth,
                'expiryYear' => $cardYear,
                'ccv' => $cardCcv,
                'phone' => preg_replace('/\D/', '', $cardPhone),
                'mobilePhone' => preg_replace('/\D/', '', $cardMobilePhone),
                'postalCode' => preg_replace('/\D/', '', $cardPostalCode),
                'addressNumber' => $cardAddressNumber ?: 'S/N',
                'addressComplement' => $cardAddressComplement,
                'cpfCnpj' => preg_replace('/\D/', '', (string) $cardCpfCnpj),
                'email' => (string) $cardEmail,
            ];
        }

        try {
            $resultado = $this->assinaturaPlanoService->iniciarAssinatura($empresa, $plano, [
                'origem' => AssinaturaPlano::ORIGEM_DASHBOARD,
                'metodo_adesao' => $metodoAdesao,
                'metodo_mensal' => $metodoMensal,
                'gerar_cobrancas' => true,
                'observacoes' => 'Contratação via dashboard do cliente.',
                'card_data' => $cardData,
            ]);

            // Se houve erro de cartão mas assinatura foi criada como boleto
            if (!empty($resultado['erro_cartao'])) {
                return redirect()
                    ->route('billing.meu-financeiro.index')
                    ->with('warning', $resultado['mensagem'] ?? 'Cartão recusado, assinatura criada com boleto.');
            }

            $mensagem = (string) ($resultado['mensagem'] ?? 'Assinatura iniciada com sucesso.');
            if (!empty($resultado['promocao_aplicada']['nome'])) {
                $mensagem .= ' Promocao aplicada: ' . $resultado['promocao_aplicada']['nome'] . '.';
            }

            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('success', $mensagem);
        } catch (Exception $e) {
            Log::error('Falha ao iniciar assinatura self-service', [
                'id_empresa' => $empresa->id_empresa,
                'id_plano' => $plano->id_plano,
                'metodo_adesao' => $metodoAdesao,
                'metodo_mensal' => $metodoMensal,
                'erro' => $e->getMessage(),
            ]);

            return redirect()
                ->route('billing.meu-financeiro.index')
                ->with('error', 'Não foi possível iniciar a assinatura: ' . $e->getMessage());
        }
    }

    /**
     * Fluxo comercial via lista de filiais.
     */
    public function assinarComercial(Request $request): \Illuminate\Http\JsonResponse
    {
        $dados = $request->validate([
            'id_empresa' => 'required|integer',
            'id_plano' => 'required|integer|exists:planos,id_plano',
            'metodo_adesao' => 'required|string|in:BOLETO,PIX,CREDIT_CARD,DEBIT_CARD,CARTAO_CREDITO,CARTAO_DEBITO,CREDITO,DEBITO',
            'metodo_mensal' => 'required|string|in:BOLETO,CREDIT_CARD,DEBIT_CARD,CARTAO_CREDITO,CARTAO_DEBITO,CREDITO,DEBITO',
            'gerar_link_pagamento' => 'nullable|boolean',
            'valor' => 'nullable|numeric|min:0',
            'adesao' => 'nullable|numeric|min:0',
            'observacoes' => 'nullable|string|max:500',
        ]);

        $usuario = Auth::user();
        $isSuporte = (int) ($usuario->is_suporte ?? $usuario->isSuporte ?? 0) === 1;

        $idEmpresaSessao = (int) (session('id_empresa') ?: ($usuario->id_empresa ?? 0));
        $idEmpresaRequest = (int) $dados['id_empresa'];

        if ($isSuporte && $idEmpresaRequest > 0) {
            // Suporte pode contratar para a filial em edicao mesmo com sessao em outra empresa.
            $idEmpresaSessao = $idEmpresaRequest;
        }

        if ($idEmpresaSessao <= 0 || (!$isSuporte && $idEmpresaRequest !== $idEmpresaSessao)) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para iniciar assinatura para outra empresa.',
            ], 403); // Seguranca: bloqueia IDOR de assinatura comercial fora da empresa da sessao.
        }

        $dados['id_empresa'] = $idEmpresaSessao;

        try {
            $empresa = Empresa::where('id_empresa', $idEmpresaSessao)
                ->findOrFail($idEmpresaSessao); // Seguranca: consulta sempre restrita ao id_empresa da sessao.
            $plano = Plano::findOrFail((int) $dados['id_plano']);

            $resultado = $this->assinaturaPlanoService->iniciarAssinatura($empresa, $plano, [
                'origem' => AssinaturaPlano::ORIGEM_COMERCIAL,
                'metodo_adesao' => (string) $dados['metodo_adesao'],
                'metodo_mensal' => (string) $dados['metodo_mensal'],
                'gerar_cobrancas' => (bool) ($dados['gerar_link_pagamento'] ?? true),
                'valor_mensal' => $dados['valor'] ?? null,
                'valor_adesao' => $dados['adesao'] ?? null,
                'observacoes' => (string) ($dados['observacoes'] ?? 'Contratação realizada pelo time comercial.'),
            ]);

            return response()->json([
                'success' => true,
                'message' => $resultado['mensagem'] ?? 'Fluxo comercial iniciado com sucesso.',
                'assinatura' => [
                    'id' => $resultado['assinatura']->id,
                    'status' => $resultado['assinatura']->status,
                ],
                'plano_contratado' => $resultado['plano_contratado'] ? [
                    'id' => $resultado['plano_contratado']->id,
                    'nome' => $resultado['plano_contratado']->nome,
                    'valor_formatado' => $resultado['plano_contratado']->valor_formatado,
                    'adesao_formatada' => $resultado['plano_contratado']->adesao_formatada,
                    'data_contratacao_formatada' => $resultado['plano_contratado']->data_contratacao_formatada,
                    'observacoes' => $resultado['plano_contratado']->observacoes,
                ] : null,
                'pagamento_adesao' => $resultado['adesao_pagamento'] ? [
                    'id' => $resultado['adesao_pagamento']->id,
                    'status' => $resultado['adesao_pagamento']->status,
                    'url' => $resultado['adesao_pagamento']->payment_url,
                    'boleto' => $resultado['adesao_pagamento']->asaas_bank_slip_url,
                    'pix_copy_paste' => $resultado['adesao_pagamento']->asaas_pix_copy_paste,
                ] : null,
                'pagamento_mensal' => $resultado['mensal_pagamento'] ? [
                    'id' => $resultado['mensal_pagamento']->id,
                    'status' => $resultado['mensal_pagamento']->status,
                    'url' => $resultado['mensal_pagamento']->payment_url,
                    'boleto' => $resultado['mensal_pagamento']->asaas_bank_slip_url,
                ] : null,
                'mensal_assinatura' => !empty($resultado['mensal_assinatura']) ? [
                    'id' => $resultado['mensal_assinatura']['id'] ?? null,
                    'status' => $resultado['mensal_assinatura']['status'] ?? null,
                    'next_due_date' => $resultado['mensal_assinatura']['next_due_date'] ?? null,
                    'billing_type' => $resultado['mensal_assinatura']['billing_type'] ?? null,
                    'value' => $resultado['mensal_assinatura']['value'] ?? null,
                ] : null,
                'promocao_aplicada' => $resultado['promocao_aplicada'] ?? null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao iniciar fluxo comercial: ' . $e->getMessage(),
            ], 500);
        }
    }
}
