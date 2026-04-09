@extends('layouts/layoutMaster')

@section('title', 'Meu Financeiro')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <h4 class="mb-1">Meu Financeiro</h4>
    <p class="text-muted mb-0">Gerencie adesão, mensalidades e método de cobrança do seu plano.</p>
  </div>
</div>

@if(session('success'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-success" role="alert">
      <div>{{ session('success') }}</div>
      @if(session('recibo_url'))
      <div class="mt-2">
        <a href="{{ session('recibo_url') }}" class="btn btn-sm btn-success" target="_blank" rel="noopener">
          Baixar Recibo da Troca de Plano
        </a>
      </div>
      @endif
    </div>
  </div>
</div>
@endif

@if(session('warning'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
  </div>
</div>
@endif

@if(session('error'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
  </div>
</div>
@endif

@if(!$assinatura)
<div class="row">
  <div class="col-12">
    <div class="alert alert-info" role="alert">
      Nenhuma assinatura encontrada para esta filial.
    </div>
  </div>
</div>
@else
@php
  $statusMap = [
      'pendente_pagamento' => ['label' => 'Pendente de Pagamento', 'class' => 'warning'],
      'onboarding_dados' => ['label' => 'Onboarding - Dados', 'class' => 'info'],
      'onboarding_contrato' => ['label' => 'Onboarding - Contrato', 'class' => 'info'],
      'ativa' => ['label' => 'Ativa', 'class' => 'success'],
        'cancelamento_agendado' => ['label' => 'Cancelamento Agendado', 'class' => 'warning'],
      'suspensa' => ['label' => 'Suspensa', 'class' => 'danger'],
      'cancelada' => ['label' => 'Cancelada', 'class' => 'secondary'],
  ];

  $statusInfo = $statusMap[$assinatura->status] ?? ['label' => ucfirst((string) $assinatura->status), 'class' => 'secondary'];
    $metodoPagamentoLabels = [
      'BOLETO' => 'Boleto',
      'PIX' => 'PIX',
      'CREDIT_CARD' => 'Cartão de Crédito',
      'DEBIT_CARD' => 'Cartão de Débito',
      'CARTAO_CREDITO' => 'Cartão de Crédito',
      'CARTAO_DEBITO' => 'Cartão de Débito',
      'CREDITO' => 'Cartão de Crédito',
      'DEBITO' => 'Cartão de Débito',
    ];
    $planoContratado = $assinatura->planoContratado;
    $planoBase = $assinatura->plano;
    $planoAtual = $planoContratado ?: $planoBase;
    $nomePlano = $planoAtual->nome ?? 'Plano não informado';
    $valorAdesaoPlano = (float) ($planoContratado->adesao ?? $planoBase->adesao ?? 0);
    $valorMensalPlano = (float) ($planoContratado->valor ?? $planoBase->valor ?? 0);
    $metodoAdesaoLabel = $metodoPagamentoLabels[strtoupper((string) $assinatura->metodo_adesao)] ?? ucfirst(strtolower((string) $assinatura->metodo_adesao));
    $metodoMensalLabel = $metodoPagamentoLabels[strtoupper((string) $assinatura->metodo_mensal)] ?? ucfirst(strtolower((string) $assinatura->metodo_mensal));
      $metodoMensalAtual = strtoupper((string) $assinatura->metodo_mensal);
      $opcoesMetodoMensal = [
        'BOLETO' => 'Boleto',
        'CREDIT_CARD' => 'Cartão de Crédito',
      ];
      if (array_key_exists($metodoMensalAtual, $opcoesMetodoMensal)) {
        unset($opcoesMetodoMensal[$metodoMensalAtual]);
      }
      if (empty($opcoesMetodoMensal)) {
        $opcoesMetodoMensal = [
          'BOLETO' => 'Boleto',
          'CREDIT_CARD' => 'Cartão de Crédito',
        ];
      }
    $cartaoDebitoConfirmado = (bool) ($cartaoDebitoInfo['confirmado'] ?? false);
    $cartaoDebitoFinal = trim((string) ($cartaoDebitoInfo['final'] ?? ''));
    $cartaoDebitoSubscriptionId = trim((string) ($cartaoDebitoInfo['subscription_id'] ?? ''));
    $cancelamentoEfetivoEm = $assinatura->cancelamento_efetivo_em
      ? \Carbon\Carbon::parse($assinatura->cancelamento_efetivo_em)->format('d/m/Y')
      : null;

  // Calcular próxima cobrança - apenas mensalidades pendentes (não adesão)
  $proximaMensalidade = $pagamentosAbertos
      ->filter(fn($p) => $p->tipo_cobranca === 'mensalidade')
      ->sortBy('data_vencimento')
      ->first();
  $dataProximaCobranca = $proximaMensalidade
      ? \Carbon\Carbon::parse($proximaMensalidade->data_vencimento)->format('d/m/Y')
      : ($assinatura->proxima_cobranca_em ? \Carbon\Carbon::parse($assinatura->proxima_cobranca_em)->format('d/m/Y') : '-');

  // Verificar se há adesão pendente
  $adesaoPendente = $pagamentosAbertos
      ->filter(fn($p) => $p->tipo_cobranca === 'adesao' && $p->status !== 'pago')
      ->first();

    $ultimaAdesaoPaga = $historicoPagamentos
      ->first(fn($p) => $p->tipo_cobranca === 'adesao' && $p->status === 'pago');

    $valorAdesaoPagoAtual = $ultimaAdesaoPaga
      ? (float) $ultimaAdesaoPaga->valor
      : $valorAdesaoPlano;

    $tipoCobrancaLabel = function ($pagamento) {
      $tipo = strtolower((string) ($pagamento->tipo_cobranca ?? ''));

      if ($tipo !== 'adesao') {
        return ucfirst((string) ($pagamento->tipo_cobranca ?? ''));
      }

      $observacoes = (string) ($pagamento->observacoes ?? '');
      if (preg_match('/\[ADESAO_UPGRADE\]\s*([^|]+)/', $observacoes, $matches)) {
        $nomePlano = trim((string) ($matches[1] ?? ''));
        if ($nomePlano !== '') {
          return 'Adesão - Upgrade Plano ' . $nomePlano;
        }
      }

      $jsonResposta = json_decode((string) ($pagamento->json_resposta ?? ''), true);
      if (is_array($jsonResposta)) {
        $descricao = trim((string) ($jsonResposta['description'] ?? ''));
        if ($descricao !== '' && stripos($descricao, 'Adesão - Upgrade Plano') !== false) {
          return $descricao;
        }
      }

      return 'Adesão';
    };
@endphp

<div class="row g-4 mb-4">
  <div class="col-xl-8 col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h5 class="mb-1">Resumo da Assinatura</h5>
            <p class="text-muted mb-0">Empresa: {{ $empresa->nome_empresa }}</p>
          </div>
          <span class="badge bg-label-{{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
        </div>

        <div class="row g-3">
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Plano</small>
            <strong>{{ $nomePlano }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Valor da Adesão</small>
            <strong>R$ {{ number_format($valorAdesaoPlano, 2, ',', '.') }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Valor da Mensalidade</small>
            <strong>R$ {{ number_format($valorMensalPlano, 2, ',', '.') }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Método da Adesão</small>
            <strong>{{ $metodoAdesaoLabel }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Método Mensal</small>
            <strong>{{ $metodoMensalLabel }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Próxima Cobrança</small>
            <strong>{{ $dataProximaCobranca }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Último Pagamento</small>
            <strong>{{ $assinatura->ultimo_pagamento_em ? \Carbon\Carbon::parse($assinatura->ultimo_pagamento_em)->format('d/m/Y H:i') : '-' }}</strong>
          </div>
          <div class="col-lg-3 col-md-4 col-6">
            <small class="text-muted d-block">Bloqueio por Inadimplência</small>
            <strong>{{ $assinatura->bloqueada_por_inadimplencia ? 'Sim' : 'Não' }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h6 class="mb-3">Alterar Método Mensal</h6>
        <form method="POST" action="{{ route('billing.meu-financeiro.metodo-mensal') }}" id="formMetodoMensal">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="metodo_mensal">Novo método</label>
            <select class="form-select" id="metodo_mensal" name="metodo_mensal" required>
              @foreach($opcoesMetodoMensal as $metodoOpcao => $metodoOpcaoLabel)
              <option value="{{ $metodoOpcao }}">{{ $metodoOpcaoLabel }}</option>
              @endforeach
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100" id="btnAtualizarMetodo">Atualizar Método</button>
        </form>

        @if(strtoupper((string) $assinatura->metodo_mensal) === 'CREDIT_CARD')
        <div class="mt-3">
          <button type="button" class="btn btn-outline-secondary w-100" data-bs-toggle="modal" data-bs-target="#modalCadastrarCartao">
            <i class="ti ti-credit-card me-1"></i>
            @if($cartaoDebitoConfirmado)
            Atualizar Cartão para Débito Automático
            @else
            Cadastrar Cartão para Débito Automático
            @endif
          </button>

          @if($cartaoDebitoConfirmado)
          <div class="alert alert-success py-2 px-3 mt-2 mb-0" role="alert">
            <small>
              <i class="ti ti-check me-1"></i> Cartão cadastrado para débito automático.
              @if($cartaoDebitoFinal !== '')
              <span class="d-block mt-1 fw-semibold">
                final {{ $cartaoDebitoFinal }}
              </span>
              @endif
            </small>
          </div>
          @else
          <small class="text-muted d-block mt-1">
            <i class="ti ti-info-circle me-1"></i> Se quiser débito automático fixo, cadastre o cartão.
          </small>
          @endif

          @if($cartaoDebitoConfirmado && $cartaoDebitoSubscriptionId !== '')
          <small class="text-muted d-block mt-1">
            <i class="ti ti-link me-1"></i> Assinatura Asaas: {{ $cartaoDebitoSubscriptionId }}
          </small>
          @endif
        </div>
        @endif

        @if($onboardingPendente)
        <hr>
        <a href="{{ route('onboarding.index') }}" class="btn btn-label-info w-100">
          Continuar Onboarding
        </a>
        @endif
      </div>
    </div>
  </div>
</div>

@if($assinatura->status === 'cancelamento_agendado')
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="alert alert-warning mb-0" role="alert">
      <i class="ti ti-alert-triangle me-1"></i>
      Cancelamento agendado.
      @if($cancelamentoEfetivoEm)
      O sistema permanece ativo por 30 dias apos o ultimo pagamento, ate <strong>{{ $cancelamentoEfetivoEm }}</strong>.
      @else
      O sistema permanece ativo por 30 dias apos o ultimo pagamento.
      @endif
    </div>
  </div>
</div>
@endif

<div class="row g-4 mb-4">
  <div class="col-xl-8 col-lg-7">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Troca de Plano</h5>
      </div>
      <div class="card-body">
        @if($planosDisponiveisUpgrade->isEmpty())
        <p class="text-muted mb-0">Nao ha outros planos ativos disponiveis para upgrade no momento.</p>
        @else
        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead>
              <tr>
                <th>Plano</th>
                <th>Mensalidade</th>
                <th>Adesao</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($planosDisponiveisUpgrade as $itemUpgrade)
              @php
                $planoUpgrade = $itemUpgrade['plano'];
                $precosUpgrade = $itemUpgrade['precos'];
                $promoUpgrade = $precosUpgrade['promocao_aplicada'] ?? null;
                $valorMensalNovo = (float) ($precosUpgrade['valor_mensal_final'] ?? 0);
                $valorAdesaoPlanoNovo = (float) ($precosUpgrade['valor_adesao_final'] ?? 0);
                $isDowngrade = $valorMensalNovo < $valorMensalPlano;
                $valorAdesaoTroca = $isDowngrade
                  ? 0.0
                  : max(0, $valorAdesaoPlanoNovo - $valorAdesaoPagoAtual);
                $recursosPlanoUpgrade = array_values($itemUpgrade['recursos'] ?? []);
                $previewMensalidades = $itemUpgrade['preview_mensalidades'] ?? [];
                $primeiraMensalidadePreview = $previewMensalidades['primeira'] ?? [];
                $segundaMensalidadePreview = $previewMensalidades['segunda'] ?? [];
                $estrategiaMensalidadePreview = (string) ($previewMensalidades['estrategia'] ?? '');
              @endphp
              <tr>
                <td>
                  <strong>{{ $planoUpgrade->nome }}</strong>
                  @if($promoUpgrade)
                  <span class="badge bg-label-success ms-1">{{ $promoUpgrade['nome'] }}</span>
                  @endif
                </td>
                <td>
                  @if((float) $precosUpgrade['valor_mensal_final'] < (float) $precosUpgrade['valor_mensal_original'])
                  <small class="text-muted text-decoration-line-through d-block">R$ {{ number_format((float) $precosUpgrade['valor_mensal_original'], 2, ',', '.') }}</small>
                  @endif
                  <strong>R$ {{ number_format((float) $precosUpgrade['valor_mensal_final'], 2, ',', '.') }}</strong>
                </td>
                <td>
                  @if((float) $precosUpgrade['valor_adesao_final'] < (float) $precosUpgrade['valor_adesao_original'])
                  <small class="text-muted text-decoration-line-through d-block">R$ {{ number_format((float) $precosUpgrade['valor_adesao_original'], 2, ',', '.') }}</small>
                  @endif
                  <strong>R$ {{ number_format((float) $precosUpgrade['valor_adesao_final'], 2, ',', '.') }}</strong>
                </td>
                <td class="text-end">
                  <button
                    type="button"
                    class="btn btn-sm btn-primary btn-ver-plano"
                    data-bs-toggle="modal"
                    data-bs-target="#modalVerPlano"
                    data-id-plano="{{ $planoUpgrade->id_plano }}"
                    data-nome-plano="{{ e($planoUpgrade->nome) }}"
                    data-valor-mensal="{{ number_format($valorMensalNovo, 2, '.', '') }}"
                    data-valor-mensal-formatado="{{ number_format($valorMensalNovo, 2, ',', '.') }}"
                    data-valor-adesao-plano="{{ number_format($valorAdesaoPlanoNovo, 2, '.', '') }}"
                    data-valor-adesao-plano-formatado="{{ number_format($valorAdesaoPlanoNovo, 2, ',', '.') }}"
                    data-valor-adesao-cobranca="{{ number_format((float) $valorAdesaoTroca, 2, '.', '') }}"
                    data-valor-adesao-cobranca-formatado="{{ number_format((float) $valorAdesaoTroca, 2, ',', '.') }}"
                    data-tipo-troca="{{ $isDowngrade ? 'downgrade' : 'upgrade' }}"
                    data-recursos='@json($recursosPlanoUpgrade)'
                    data-promocao="{{ e((string) ($promoUpgrade['nome'] ?? '')) }}"
                    data-primeira-mensalidade-data="{{ (string) ($primeiraMensalidadePreview['data'] ?? '') }}"
                    data-primeira-mensalidade-valor="{{ number_format((float) ($primeiraMensalidadePreview['valor'] ?? 0), 2, '.', '') }}"
                    data-segunda-mensalidade-data="{{ (string) ($segundaMensalidadePreview['data'] ?? '') }}"
                    data-segunda-mensalidade-valor="{{ number_format((float) ($segundaMensalidadePreview['valor'] ?? 0), 2, '.', '') }}"
                    data-estrategia-mensalidade="{{ e($estrategiaMensalidadePreview) }}"
                  >
                    Ver Plano
                  </button>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <small class="text-muted d-block mt-2">A troca de plano aplica diferenca de adesao no upgrade e nao gera devolucao no downgrade.</small>
        @endif
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h6 class="mb-0">Cancelar Assinatura</h6>
      </div>
      <div class="card-body">
        @if($assinatura->status === 'cancelamento_agendado')
        <div class="alert alert-warning mb-0" role="alert">
          <small>
            <i class="ti ti-info-circle me-1"></i>
            Cancelamento ja agendado.
            @if($cancelamentoEfetivoEm)
            Vigencia ate {{ $cancelamentoEfetivoEm }}.
            @endif
          </small>
        </div>
        @else
        <p class="text-muted small">A assinatura recorrente sera encerrada e o acesso ao sistema sera mantido por 30 dias apos o ultimo pagamento.</p>
        <form method="POST" action="{{ route('billing.meu-financeiro.cancelar-assinatura') }}" onsubmit="return confirm('Confirmar cancelamento com vigencia de 30 dias apos o ultimo pagamento?');">
          @csrf
          <div class="mb-3">
            <label for="motivo_cancelamento" class="form-label">Motivo (opcional)</label>
            <textarea class="form-control" id="motivo_cancelamento" name="motivo_cancelamento" rows="3" maxlength="255" placeholder="Descreva o motivo do cancelamento"></textarea>
          </div>
          <button type="submit" class="btn btn-outline-danger w-100">
            Agendar Cancelamento
          </button>
        </form>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalVerPlano" tabindex="-1" aria-labelledby="modalVerPlanoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST" id="formTrocaPlano">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="modalVerPlanoLabel">Ver Plano e Confirmar Troca</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-primary mb-3">
            <div class="fw-semibold" id="modalVerPlanoNome">Plano</div>
            <small id="modalVerPlanoValores">Mensalidade e adesao</small>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <small class="text-muted d-block">Tipo da troca</small>
                <strong id="modalVerPlanoTipoTroca">Upgrade</strong>
                <small class="text-muted d-block mt-2">Adesao do novo plano</small>
                <strong id="modalVerPlanoAdesaoPlano">R$ 0,00</strong>
                <small class="text-muted d-block mt-2">Adesao a cobrar agora</small>
                <strong id="modalVerPlanoAdesaoCobranca">R$ 0,00</strong>
                <small class="text-muted d-block mt-2" id="modalVerPlanoResumoAdesao"></small>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <small class="text-muted d-block mb-2">Recursos do plano</small>
                <ul class="mb-0 ps-3" id="modalVerPlanoRecursos">
                  <li class="text-muted">Sem recursos informados.</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="mb-3" id="modalUpgradeMetodoAdesaoWrapper">
            <label class="form-label" for="modal_upgrade_metodo_adesao">Como pagar a adesao da troca</label>
            <select class="form-select" id="modal_upgrade_metodo_adesao" name="metodo_adesao" required>
              <option value="PIX">PIX</option>
              <option value="BOLETO">Boleto</option>
              <option value="CREDIT_CARD">Cartao de Credito</option>
            </select>
          </div>

          <input type="hidden" id="modal_upgrade_metodo_adesao_fallback" name="metodo_adesao" value="PIX" disabled>

          <input type="hidden" name="metodo_mensal" value="{{ strtoupper((string) $assinatura->metodo_mensal) }}">

          <div id="camposCartaoUpgrade" class="d-none">
            <hr>
            <h6 class="mb-3"><i class="ti ti-credit-card me-1"></i>Dados do Cartao para Adesao</h6>
            <div class="mb-3">
              <label class="form-label" for="upgrade_card_holderName">Nome no Cartao *</label>
              <input type="text" class="form-control" id="upgrade_card_holderName" name="card_holderName" placeholder="NOME COMO ESTA NO CARTAO">
            </div>
            <div class="mb-3">
              <label class="form-label" for="upgrade_card_number">Numero do Cartao *</label>
              <input type="text" class="form-control" id="upgrade_card_number" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19">
            </div>
            <div class="row">
              <div class="col-4 mb-3">
                <label class="form-label" for="upgrade_card_expiryMonth">Mes *</label>
                <select class="form-select" id="upgrade_card_expiryMonth" name="card_expiryMonth">
                  <option value="">Mes</option>
                  @for($m = 1; $m <= 12; $m++)
                  <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                  @endfor
                </select>
              </div>
              <div class="col-4 mb-3">
                <label class="form-label" for="upgrade_card_expiryYear">Ano *</label>
                <select class="form-select" id="upgrade_card_expiryYear" name="card_expiryYear">
                  <option value="">Ano</option>
                  @for($y = date('Y'); $y <= date('Y') + 15; $y++)
                  <option value="{{ $y }}">{{ $y }}</option>
                  @endfor
                </select>
              </div>
              <div class="col-4 mb-3">
                <label class="form-label" for="upgrade_card_ccv">CVV *</label>
                <input type="text" class="form-control" id="upgrade_card_ccv" name="card_ccv" placeholder="000" maxlength="4">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label" for="upgrade_card_cpfCnpj">CPF/CNPJ do Titular *</label>
              <input
                type="text"
                class="form-control"
                id="upgrade_card_cpfCnpj"
                name="card_cpfCnpj"
                value="{{ old('card_cpfCnpj', $empresa->cpf ?: $empresa->cnpj) }}"
                placeholder="000.000.000-00 ou 00.000.000/0000-00"
                maxlength="18"
              >
            </div>

            <div class="mb-3">
              <label class="form-label" for="upgrade_card_email">E-mail do Titular</label>
              <input type="email" class="form-control" id="upgrade_card_email" name="card_email" value="{{ old('card_email', $empresa->email) }}" placeholder="email@dominio.com">
            </div>

            @php
              $temTelefoneUpgrade = !empty($empresa->telefone);
              $temCepUpgrade = !empty($empresa->cep);
              $temNumeroUpgrade = !empty($empresa->numero);
            @endphp

            @if(!$temTelefoneUpgrade || !$temCepUpgrade || !$temNumeroUpgrade)
            <h6 class="mb-3 mt-3"><i class="ti ti-user me-1"></i>Dados do Titular</h6>
            @endif

            @if(!$temTelefoneUpgrade)
            <div class="mb-3">
              <label class="form-label" for="upgrade_card_phone">Telefone (com DDD) *</label>
              <input type="text" class="form-control" id="upgrade_card_phone" name="card_phone" placeholder="(11) 99999-9999" maxlength="15">
            </div>
            @endif

            @if(!$temCepUpgrade)
            <div class="mb-3">
              <label class="form-label" for="upgrade_card_postalCode">CEP *</label>
              <input type="text" class="form-control" id="upgrade_card_postalCode" name="card_postalCode" placeholder="00000-000" maxlength="9">
            </div>
            @endif

            @if(!$temNumeroUpgrade)
            <div class="mb-3">
              <label class="form-label" for="upgrade_card_addressNumber">Numero do Endereco *</label>
              <input type="text" class="form-control" id="upgrade_card_addressNumber" name="card_addressNumber" placeholder="123">
            </div>
            @endif

            <div class="mb-3">
              <label class="form-label" for="upgrade_card_addressComplement">Complemento</label>
              <input type="text" class="form-control" id="upgrade_card_addressComplement" name="card_addressComplement" placeholder="Apto, sala, bloco...">
            </div>
          </div>

          <div class="border rounded p-3 bg-label-info mb-0">
            <small class="text-muted d-block mb-2">Duas proximas mensalidades previstas</small>
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
              <div>
                <small class="text-muted d-block">1a mensalidade</small>
                <strong id="modalVerPlanoMensalidade1">-</strong>
              </div>
              <div class="text-md-end">
                <small class="text-muted d-block">2a mensalidade</small>
                <strong id="modalVerPlanoMensalidade2">-</strong>
              </div>
            </div>
            <small class="d-block" id="modalVerPlanoRegraMensalidade"></small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Confirmar Troca de Plano</button>
        </div>
      </form>
    </div>
  </div>
</div>

@if($adesaoPendente)
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card border-warning">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div>
            <h6 class="mb-1"><i class="ti ti-alert-triangle text-warning me-1"></i> Adesão Pendente</h6>
            <p class="mb-0 text-muted">Valor: <strong>R$ {{ number_format((float) $adesaoPendente->valor, 2, ',', '.') }}</strong> | Método atual: <strong>{{ $metodoPagamentoLabels[strtoupper((string) $adesaoPendente->metodo_pagamento)] ?? ucfirst(strtolower((string) $adesaoPendente->metodo_pagamento)) }}</strong></p>
          </div>
          <div class="d-flex gap-2 flex-wrap">
            @if($adesaoPendente->asaas_invoice_url)
            <a href="{{ $adesaoPendente->asaas_invoice_url }}" target="_blank" class="btn btn-primary">
              <i class="ti ti-external-link me-1"></i> Pagar Agora
            </a>
            @endif
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalAlterarMetodoAdesao">
              <i class="ti ti-refresh me-1"></i> Alterar Método
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Alterar Método da Adesão -->
<div class="modal fade" id="modalAlterarMetodoAdesao" tabindex="-1" aria-labelledby="modalAlterarMetodoAdesaoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('billing.meu-financeiro.metodo-adesao') }}" id="formMetodoAdesao">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="modalAlterarMetodoAdesaoLabel">Alterar Método de Pagamento da Adesão</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info mb-3">
            <small>A cobrança atual será cancelada e uma nova será gerada com o método escolhido.</small>
          </div>
          <div class="mb-3">
            <label class="form-label" for="novo_metodo_adesao">Novo método de pagamento</label>
            <select class="form-select" id="novo_metodo_adesao" name="metodo_adesao" required>
              <option value="PIX" {{ $adesaoPendente->metodo_pagamento === 'PIX' ? 'selected' : '' }}>PIX</option>
              <option value="BOLETO" {{ $adesaoPendente->metodo_pagamento === 'BOLETO' ? 'selected' : '' }}>Boleto</option>
              <option value="CREDIT_CARD" {{ $adesaoPendente->metodo_pagamento === 'CREDIT_CARD' ? 'selected' : '' }}>Cartão de Crédito</option>
            </select>
          </div>

          <!-- Campos de Cartão (aparecem quando necessário) -->
          <div id="camposCartaoAdesao" class="d-none">
            <hr>
            <h6 class="mb-3"><i class="ti ti-credit-card me-1"></i> Dados do Cartão</h6>
            <div class="mb-3">
              <label class="form-label" for="adesao_holderName">Nome no Cartão *</label>
              <input type="text" class="form-control" id="adesao_holderName" name="card_holderName" placeholder="NOME COMO ESTÁ NO CARTÃO">
            </div>
            <div class="mb-3">
              <label class="form-label" for="adesao_number">Número do Cartão *</label>
              <input type="text" class="form-control" id="adesao_number" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19">
            </div>
            <div class="row">
              <div class="col-4 mb-3">
                <label class="form-label" for="adesao_expiryMonth">Mês *</label>
                <select class="form-select" id="adesao_expiryMonth" name="card_expiryMonth">
                  <option value="">Mês</option>
                  @for($m = 1; $m <= 12; $m++)
                  <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                  @endfor
                </select>
              </div>
              <div class="col-4 mb-3">
                <label class="form-label" for="adesao_expiryYear">Ano *</label>
                <select class="form-select" id="adesao_expiryYear" name="card_expiryYear">
                  <option value="">Ano</option>
                  @for($y = date('Y'); $y <= date('Y') + 15; $y++)
                  <option value="{{ $y }}">{{ $y }}</option>
                  @endfor
                </select>
              </div>
              <div class="col-4 mb-3">
                <label class="form-label" for="adesao_ccv">CVV *</label>
                <input type="text" class="form-control" id="adesao_ccv" name="card_ccv" placeholder="000" maxlength="4">
              </div>
            </div>

            <!-- Dados do Titular - exibe apenas campos não cadastrados na empresa -->
            @php
              $temTelefoneAdesao = !empty($empresa->telefone);
              $temCepAdesao = !empty($empresa->cep);
              $temNumeroAdesao = !empty($empresa->numero);
            @endphp

            @if(!$temTelefoneAdesao || !$temCepAdesao || !$temNumeroAdesao)
            <h6 class="mb-3 mt-3"><i class="ti ti-user me-1"></i> Dados do Titular</h6>
            @endif

            @if(!$temTelefoneAdesao)
            <div class="mb-3">
              <label class="form-label" for="adesao_phone">Telefone (com DDD) *</label>
              <input type="text" class="form-control" id="adesao_phone" name="card_phone" placeholder="(11) 99999-9999" maxlength="15">
            </div>
            @endif

            @if(!$temCepAdesao)
            <div class="mb-3">
              <label class="form-label" for="adesao_postalCode">CEP *</label>
              <input type="text" class="form-control" id="adesao_postalCode" name="card_postalCode" placeholder="00000-000" maxlength="9">
            </div>
            @endif

            @if(!$temNumeroAdesao)
            <div class="mb-3">
              <label class="form-label" for="adesao_addressNumber">Número do Endereço *</label>
              <input type="text" class="form-control" id="adesao_addressNumber" name="card_addressNumber" placeholder="123">
            </div>
            @endif

            <div class="alert alert-warning mb-0">
              <small><i class="ti ti-info-circle me-1"></i> O valor será cobrado imediatamente no cartão.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Alterar e Gerar Nova Cobrança</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<!-- Modal Cadastrar Cartão -->
<div class="modal fade" id="modalCadastrarCartao" tabindex="-1" aria-labelledby="modalCadastrarCartaoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalCadastrarCartaoLabel">Cadastrar Cartão para Débito Automático</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info mb-3">
          <small><i class="ti ti-info-circle me-1"></i> Ao cadastrar um cartão, as cobranças mensais serão debitadas automaticamente na data de vencimento.</small>
        </div>
        <form id="formCadastrarCartao">
          <div class="mb-3">
            <label class="form-label" for="holderName">Nome no Cartão *</label>
            <input type="text" class="form-control" id="holderName" name="holderName" placeholder="NOME COMO ESTÁ NO CARTÃO" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="cardNumber">Número do Cartão *</label>
            <input type="text" class="form-control" id="cardNumber" name="number" placeholder="0000 0000 0000 0000" maxlength="19" required>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label" for="expiryMonth">Mês Validade *</label>
              <select class="form-select" id="expiryMonth" name="expiryMonth" required>
                <option value="">Mês</option>
                @for($m = 1; $m <= 12; $m++)
                <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                @endfor
              </select>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label" for="expiryYear">Ano Validade *</label>
              <select class="form-select" id="expiryYear" name="expiryYear" required>
                <option value="">Ano</option>
                @for($y = date('Y'); $y <= date('Y') + 15; $y++)
                <option value="{{ $y }}">{{ $y }}</option>
                @endfor
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="ccv">CVV *</label>
            <input type="text" class="form-control" id="ccv" name="ccv" placeholder="000" maxlength="4" style="max-width: 100px;" required>
          </div>

          <div class="mb-3">
            <label class="form-label" for="cardCpfCnpj">CPF/CNPJ do Titular *</label>
            <input
              type="text"
              class="form-control"
              id="cardCpfCnpj"
              name="cpfCnpj"
              value="{{ old('cpfCnpj', $empresa->cpf ?: $empresa->cnpj) }}"
              placeholder="000.000.000-00 ou 00.000.000/0000-00"
              maxlength="18"
              required
            >
          </div>

          <div class="mb-3">
            <label class="form-label" for="cardEmail">E-mail do Titular</label>
            <input type="email" class="form-control" id="cardEmail" name="email" value="{{ old('email', $empresa->email) }}" placeholder="email@dominio.com">
          </div>

          <!-- Dados do Titular - exibe apenas campos não cadastrados na empresa -->
          @php
            $temTelefone = !empty($empresa->telefone);
            $temCep = !empty($empresa->cep);
            $temNumero = !empty($empresa->numero);
          @endphp

          @if(!$temTelefone || !$temCep || !$temNumero)
          <h6 class="mb-3 mt-3"><i class="ti ti-user me-1"></i> Dados do Titular</h6>
          @endif

          @if(!$temTelefone)
          <div class="mb-3">
            <label class="form-label" for="cardPhone">Telefone (com DDD) *</label>
            <input type="text" class="form-control" id="cardPhone" name="phone" placeholder="(11) 99999-9999" maxlength="15" required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="cardMobilePhone">Celular (com DDD)</label>
            <input type="text" class="form-control" id="cardMobilePhone" name="mobilePhone" placeholder="(11) 99999-9999" maxlength="15">
          </div>
          @endif

          @if(!$temCep)
          <div class="mb-3">
            <label class="form-label" for="cardPostalCode">CEP *</label>
            <input type="text" class="form-control" id="cardPostalCode" name="postalCode" placeholder="00000-000" maxlength="9" required>
          </div>
          @endif

          @if(!$temNumero)
          <div class="mb-3">
            <label class="form-label" for="cardAddressNumber">Número do Endereço *</label>
            <input type="text" class="form-control" id="cardAddressNumber" name="addressNumber" placeholder="123">
          </div>
          @endif

          <div class="mb-3">
            <label class="form-label" for="cardAddressComplement">Complemento</label>
            <input type="text" class="form-control" id="cardAddressComplement" name="addressComplement" placeholder="Apto, sala, bloco...">
          </div>

          <div class="alert alert-info mb-0">
            <small><i class="ti ti-lock me-1"></i> Seus dados são criptografados e processados com segurança pelo Asaas.</small>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnSalvarCartao">
          <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
          Salvar Cartão
        </button>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Cobranças em Aberto</h5>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Competência</th>
              <th>Vencimento</th>
              <th>Método</th>
              <th>Valor</th>
              <th>Status</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            @forelse($pagamentosAbertos as $pagamento)
            <tr>
              <td>{{ $tipoCobrancaLabel($pagamento) }}</td>
              <td>{{ $pagamento->competencia ? \Carbon\Carbon::parse($pagamento->competencia)->format('m/Y') : '-' }}</td>
              <td>{{ $pagamento->data_vencimento ? \Carbon\Carbon::parse($pagamento->data_vencimento)->format('d/m/Y') : '-' }}</td>
              <td>{{ $metodoPagamentoLabels[strtoupper((string) $pagamento->metodo_pagamento)] ?? ucfirst(strtolower((string) $pagamento->metodo_pagamento)) }}</td>
              <td>R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</td>
              <td><span class="badge bg-label-warning">{{ ucfirst((string) $pagamento->status) }}</span></td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  @if($pagamento->asaas_invoice_url)
                  <a class="btn btn-sm btn-primary" href="{{ $pagamento->asaas_invoice_url }}" target="_blank" rel="noopener">Pagar</a>
                  @endif

                  @if($pagamento->asaas_bank_slip_url)
                  <a class="btn btn-sm btn-label-secondary" href="{{ $pagamento->asaas_bank_slip_url }}" target="_blank" rel="noopener">Boleto</a>
                  @endif

                  @if($pagamento->asaas_pix_copy_paste)
                  <button
                    type="button"
                    class="btn btn-sm btn-label-info btn-copy-pix"
                    data-pix="{{ $pagamento->asaas_pix_copy_paste }}"
                  >Copiar PIX</button>
                  @endif
                </div>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Nenhuma cobrança em aberto.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recibos de Adesao e Troca de Plano</h5>
        <small class="text-muted">Historico para download a qualquer momento</small>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>ID</th>
              <th>Tipo</th>
              <th>Plano</th>
              <th>Valor Adesao</th>
              <th>Aceite</th>
              <th>Status</th>
              <th>Acoes</th>
            </tr>
          </thead>
          <tbody>
            @forelse($contratosRecibos as $contratoRecibo)
            @php
              $tituloContratoRecibo = strtolower((string) ($contratoRecibo->titulo_contrato ?? ''));
              $tipoContratoRecibo = str_contains($tituloContratoRecibo, 'aditivo') ? 'Troca de Plano' : 'Adesao Inicial';
              $nomePlanoRecibo = $contratoRecibo->planoContratado?->nome ?? $contratoRecibo->plano?->nome ?? '-';
              $statusRecibo = strtolower((string) ($contratoRecibo->status ?? ''));
              $classeStatusRecibo = $statusRecibo === 'ativo'
                ? 'success'
                : ($statusRecibo === 'substituido' ? 'warning' : 'secondary');
            @endphp
            <tr>
              <td>#{{ $contratoRecibo->id }}</td>
              <td>{{ $tipoContratoRecibo }}</td>
              <td>{{ $nomePlanoRecibo }}</td>
              <td>R$ {{ number_format((float) $contratoRecibo->valor_adesao, 2, ',', '.') }}</td>
              <td>{{ $contratoRecibo->aceito_em ? \Carbon\Carbon::parse($contratoRecibo->aceito_em)->format('d/m/Y H:i') : '-' }}</td>
              <td>
                <span class="badge bg-label-{{ $classeStatusRecibo }}">{{ ucfirst((string) $contratoRecibo->status) }}</span>
              </td>
              <td>
                <a
                  href="{{ route('billing.contrato.recibo', ['id' => $contratoRecibo->id]) }}"
                  class="btn btn-sm btn-label-primary"
                  target="_blank"
                  rel="noopener"
                >Baixar PDF</a>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Nenhum recibo disponivel para esta filial.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Histórico de Pagamentos</h5>
      </div>
      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Competência</th>
              <th>Pagamento</th>
              <th>Método</th>
              <th>Valor</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse($historicoPagamentos as $pagamento)
            <tr>
              <td>{{ $tipoCobrancaLabel($pagamento) }}</td>
              <td>{{ $pagamento->competencia ? \Carbon\Carbon::parse($pagamento->competencia)->format('m/Y') : '-' }}</td>
              <td>{{ $pagamento->data_pagamento ? \Carbon\Carbon::parse($pagamento->data_pagamento)->format('d/m/Y H:i') : '-' }}</td>
              <td>{{ $metodoPagamentoLabels[strtoupper((string) $pagamento->metodo_pagamento)] ?? ucfirst(strtolower((string) $pagamento->metodo_pagamento)) }}</td>
              <td>R$ {{ number_format((float) $pagamento->valor, 2, ',', '.') }}</td>
              <td>
                <span class="badge bg-label-{{ $pagamento->status === 'pago' ? 'success' : 'secondary' }}">
                  {{ ucfirst((string) $pagamento->status) }}
                </span>
              </td>
            </tr>
            @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">Sem histórico de pagamentos.</td>
            </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endif
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Copiar PIX
  const botoesCopyPix = document.querySelectorAll('.btn-copy-pix');
  botoesCopyPix.forEach(function (botao) {
    botao.addEventListener('click', function () {
      const pix = this.getAttribute('data-pix') || '';
      if (!pix) return;

      navigator.clipboard.writeText(pix).then(() => {
        this.textContent = 'PIX Copiado';
        setTimeout(() => { this.textContent = 'Copiar PIX'; }, 2000);
      });
    });
  });

  // Máscara número do cartão
  const cardNumberInput = document.getElementById('cardNumber');
  if (cardNumberInput) {
    cardNumberInput.addEventListener('input', function (e) {
      let value = e.target.value.replace(/\D/g, '');
      value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
      e.target.value = value.substring(0, 19);
    });
  }

  // Máscara CVV
  const ccvInput = document.getElementById('ccv');
  if (ccvInput) {
    ccvInput.addEventListener('input', function (e) {
      e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    });
  }

  // Máscara para telefone no modal de cadastrar cartão (método mensal)
  const cardPhoneInput = document.getElementById('cardPhone');
  if (cardPhoneInput) {
    cardPhoneInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 11) value = value.substring(0, 11);
      if (value.length > 6) {
        e.target.value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
      } else if (value.length > 2) {
        e.target.value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
      } else if (value.length > 0) {
        e.target.value = '(' + value;
      } else {
        e.target.value = '';
      }
    });
  }

  // Máscara para CEP no modal de cadastrar cartão
  const cardPostalCodeInput = document.getElementById('cardPostalCode');
  if (cardPostalCodeInput) {
    cardPostalCodeInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 8) value = value.substring(0, 8);
      if (value.length > 5) {
        e.target.value = value.substring(0, 5) + '-' + value.substring(5);
      } else {
        e.target.value = value;
      }
    });
  }

  // Salvar cartão
  const btnSalvarCartao = document.getElementById('btnSalvarCartao');
  const formCartao = document.getElementById('formCadastrarCartao');
  if (btnSalvarCartao && formCartao) {
    btnSalvarCartao.addEventListener('click', function () {
      if (!formCartao.checkValidity()) {
        formCartao.reportValidity();
        return;
      }

      const spinner = this.querySelector('.spinner-border');
      spinner.classList.remove('d-none');
      this.disabled = true;

      const formData = new FormData(formCartao);
      const getStringValue = (fieldName) => {
        const value = formData.get(fieldName);
        return typeof value === 'string' ? value.trim() : '';
      };
      const data = {
        holderName: getStringValue('holderName'),
        number: getStringValue('number').replace(/\s/g, ''),
        expiryMonth: getStringValue('expiryMonth'),
        expiryYear: getStringValue('expiryYear'),
        ccv: getStringValue('ccv'),
        cpfCnpj: getStringValue('cpfCnpj').replace(/\D/g, ''),
        email: getStringValue('email'),
        phone: getStringValue('phone').replace(/\D/g, ''),
        mobilePhone: getStringValue('mobilePhone').replace(/\D/g, ''),
        postalCode: getStringValue('postalCode').replace(/\D/g, ''),
        addressNumber: getStringValue('addressNumber'),
        addressComplement: getStringValue('addressComplement'),
      };

      fetch('{{ route("billing.meu-financeiro.cadastrar-cartao") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json',
        },
        body: JSON.stringify(data),
      })
      .then(response => response.json())
      .then(result => {
        spinner.classList.add('d-none');
        this.disabled = false;

        if (result.success) {
          bootstrap.Modal.getInstance(document.getElementById('modalCadastrarCartao')).hide();
          location.reload();
        } else {
          alert(result.message || 'Erro ao cadastrar cartão.');
        }
      })
      .catch(err => {
        spinner.classList.add('d-none');
        this.disabled = false;
        alert('Erro ao processar requisição.');
        console.error(err);
      });
    });
  }

  const modalVerPlano = document.getElementById('modalVerPlano');
  const formTrocaPlano = document.getElementById('formTrocaPlano');
  const selectMetodoAdesaoUpgrade = document.getElementById('modal_upgrade_metodo_adesao');
  const selectMetodoAdesaoUpgradeWrapper = document.getElementById('modalUpgradeMetodoAdesaoWrapper');
  const selectMetodoAdesaoUpgradeFallback = document.getElementById('modal_upgrade_metodo_adesao_fallback');
  const camposCartaoUpgrade = document.getElementById('camposCartaoUpgrade');

  if (modalVerPlano && formTrocaPlano && selectMetodoAdesaoUpgrade && camposCartaoUpgrade) {
    let exibirMetodoAdesaoUpgrade = true;

    const formatDateBr = (dateIso) => {
      if (!dateIso || !/^\d{4}-\d{2}-\d{2}$/.test(dateIso)) {
        return '-';
      }

      const [year, month, day] = dateIso.split('-');
      return `${day}/${month}/${year}`;
    };

    const formatCurrencyBr = (value) => {
      const numeric = Number(value || 0);
      return numeric.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    };

    const camposObrigatoriosCartaoUpgrade = [
      'upgrade_card_holderName',
      'upgrade_card_number',
      'upgrade_card_expiryMonth',
      'upgrade_card_expiryYear',
      'upgrade_card_ccv',
      'upgrade_card_cpfCnpj',
      'upgrade_card_phone',
      'upgrade_card_postalCode',
      'upgrade_card_addressNumber',
    ]
      .map((id) => document.getElementById(id))
      .filter((el) => !!el);

    function toggleCamposCartaoUpgrade() {
      const precisaCartao = exibirMetodoAdesaoUpgrade && selectMetodoAdesaoUpgrade.value === 'CREDIT_CARD';
      camposCartaoUpgrade.classList.toggle('d-none', !precisaCartao);

      camposObrigatoriosCartaoUpgrade.forEach((campo) => {
        campo.required = precisaCartao;
      });
    }

    function toggleMetodoAdesaoUpgrade(valorAdesaoCobranca) {
      exibirMetodoAdesaoUpgrade = Number(valorAdesaoCobranca || 0) > 0;

      if (selectMetodoAdesaoUpgradeWrapper) {
        selectMetodoAdesaoUpgradeWrapper.classList.toggle('d-none', !exibirMetodoAdesaoUpgrade);
      }

      selectMetodoAdesaoUpgrade.disabled = !exibirMetodoAdesaoUpgrade;
      selectMetodoAdesaoUpgrade.required = exibirMetodoAdesaoUpgrade;

      if (selectMetodoAdesaoUpgradeFallback) {
        selectMetodoAdesaoUpgradeFallback.disabled = exibirMetodoAdesaoUpgrade;
      }

      if (!exibirMetodoAdesaoUpgrade) {
        selectMetodoAdesaoUpgrade.value = 'PIX';
      }

      toggleCamposCartaoUpgrade();
    }

    function renderRecursosPlano(recursos) {
      const listaRecursos = document.getElementById('modalVerPlanoRecursos');
      if (!listaRecursos) {
        return;
      }

      listaRecursos.innerHTML = '';

      if (!Array.isArray(recursos) || recursos.length === 0) {
        const item = document.createElement('li');
        item.className = 'text-muted';
        item.textContent = 'Sem recursos informados.';
        listaRecursos.appendChild(item);
        return;
      }

      recursos.forEach((recurso) => {
        const item = document.createElement('li');
        item.className = 'mb-1';
        item.textContent = String(recurso);
        listaRecursos.appendChild(item);
      });
    }

    selectMetodoAdesaoUpgrade.addEventListener('change', toggleCamposCartaoUpgrade);

    modalVerPlano.addEventListener('show.bs.modal', function(event) {
      const button = event.relatedTarget;
      if (!button) {
        return;
      }

      const idPlano = button.getAttribute('data-id-plano') || '';
      const nomePlano = button.getAttribute('data-nome-plano') || 'Plano';
      const valorMensalFormatado = button.getAttribute('data-valor-mensal-formatado') || '0,00';
      const valorAdesaoPlanoFormatado = button.getAttribute('data-valor-adesao-plano-formatado') || '0,00';
      const valorAdesaoCobranca = Number(button.getAttribute('data-valor-adesao-cobranca') || '0');
      const valorAdesaoCobrancaFormatado = button.getAttribute('data-valor-adesao-cobranca-formatado') || '0,00';
      const tipoTroca = button.getAttribute('data-tipo-troca') || 'upgrade';
      const nomePromocao = button.getAttribute('data-promocao') || '';
      const primeiraMensalidadeData = button.getAttribute('data-primeira-mensalidade-data') || '';
      const primeiraMensalidadeValor = Number(button.getAttribute('data-primeira-mensalidade-valor') || '0');
      const segundaMensalidadeData = button.getAttribute('data-segunda-mensalidade-data') || '';
      const segundaMensalidadeValor = Number(button.getAttribute('data-segunda-mensalidade-valor') || '0');
      const estrategiaMensalidade = button.getAttribute('data-estrategia-mensalidade') || '';

      let recursos = [];
      try {
        recursos = JSON.parse(button.getAttribute('data-recursos') || '[]');
      } catch (error) {
        recursos = [];
      }

      formTrocaPlano.action = `{{ url('/billing/meu-financeiro/upgrade') }}/${idPlano}`;

      const nomeEl = document.getElementById('modalVerPlanoNome');
      const valoresEl = document.getElementById('modalVerPlanoValores');
      const tipoTrocaEl = document.getElementById('modalVerPlanoTipoTroca');
      const adesaoPlanoEl = document.getElementById('modalVerPlanoAdesaoPlano');
      const adesaoCobrancaEl = document.getElementById('modalVerPlanoAdesaoCobranca');
      const resumoAdesaoEl = document.getElementById('modalVerPlanoResumoAdesao');
      const mensalidade1El = document.getElementById('modalVerPlanoMensalidade1');
      const mensalidade2El = document.getElementById('modalVerPlanoMensalidade2');
      const regraMensalidadeEl = document.getElementById('modalVerPlanoRegraMensalidade');

      if (nomeEl) {
        nomeEl.textContent = nomePromocao ? `${nomePlano} (${nomePromocao})` : nomePlano;
      }

      if (valoresEl) {
        valoresEl.textContent = `Mensalidade: R$ ${valorMensalFormatado} | Adesao do plano: R$ ${valorAdesaoPlanoFormatado}`;
      }

      if (tipoTrocaEl) {
        tipoTrocaEl.textContent = tipoTroca === 'downgrade' ? 'Downgrade' : 'Upgrade';
      }

      if (adesaoPlanoEl) {
        adesaoPlanoEl.textContent = `R$ ${valorAdesaoPlanoFormatado}`;
      }

      if (adesaoCobrancaEl) {
        adesaoCobrancaEl.textContent = `R$ ${valorAdesaoCobrancaFormatado}`;
      }

      if (resumoAdesaoEl) {
        if (tipoTroca === 'downgrade') {
          resumoAdesaoEl.textContent = 'Downgrade: sem cobranca de adesao e sem devolucao.';
        } else if (valorAdesaoCobranca > 0) {
          resumoAdesaoEl.textContent = 'No upgrade, e cobrada somente a diferenca de adesao.';
        } else {
          resumoAdesaoEl.textContent = 'Nao existe diferenca de adesao a cobrar nesta troca.';
        }
      }

      if (mensalidade1El) {
        mensalidade1El.textContent = `${formatDateBr(primeiraMensalidadeData)} - R$ ${formatCurrencyBr(primeiraMensalidadeValor)}`;
      }

      if (mensalidade2El) {
        mensalidade2El.textContent = `${formatDateBr(segundaMensalidadeData)} - R$ ${formatCurrencyBr(segundaMensalidadeValor)}`;
      }

      if (regraMensalidadeEl) {
        regraMensalidadeEl.textContent = estrategiaMensalidade !== ''
          ? estrategiaMensalidade
          : 'Regra de periodo aplicada automaticamente.';
      }

      renderRecursosPlano(recursos);
      toggleMetodoAdesaoUpgrade(valorAdesaoCobranca);
    });

    const upgradeCardNumber = document.getElementById('upgrade_card_number');
    if (upgradeCardNumber) {
      upgradeCardNumber.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
        e.target.value = value.substring(0, 19);
      });
    }

    const upgradeCardCcv = document.getElementById('upgrade_card_ccv');
    if (upgradeCardCcv) {
      upgradeCardCcv.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
      });
    }

    const upgradeCardPhone = document.getElementById('upgrade_card_phone');
    if (upgradeCardPhone) {
      upgradeCardPhone.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substring(0, 11);
        if (value.length > 6) {
          e.target.value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
        } else if (value.length > 2) {
          e.target.value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
        } else if (value.length > 0) {
          e.target.value = '(' + value;
        } else {
          e.target.value = '';
        }
      });
    }

    const upgradeCardPostalCode = document.getElementById('upgrade_card_postalCode');
    if (upgradeCardPostalCode) {
      upgradeCardPostalCode.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 8) value = value.substring(0, 8);
        if (value.length > 5) {
          e.target.value = value.substring(0, 5) + '-' + value.substring(5);
        } else {
          e.target.value = value;
        }
      });
    }

    const upgradeCardCpfCnpj = document.getElementById('upgrade_card_cpfCnpj');
    if (upgradeCardCpfCnpj) {
      upgradeCardCpfCnpj.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length <= 11) {
          value = value
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        } else {
          value = value
            .replace(/^(\d{2})(\d)/, '$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1/$2')
            .replace(/(\d{4})(\d)/, '$1-$2');
        }
        e.target.value = value.substring(0, 18);
      });
    }

    toggleCamposCartaoUpgrade();
  }

  // Toggle campos de cartão no modal de adesão
  const selectMetodoAdesao = document.getElementById('novo_metodo_adesao');
  const camposCartaoAdesao = document.getElementById('camposCartaoAdesao');
  if (selectMetodoAdesao && camposCartaoAdesao) {
    function toggleCartaoAdesao() {
      const precisaCartao = selectMetodoAdesao.value === 'CREDIT_CARD';
      camposCartaoAdesao.classList.toggle('d-none', !precisaCartao);

      // Ajustar required dos campos
      const campos = camposCartaoAdesao.querySelectorAll('input, select');
      campos.forEach(campo => {
        if (campo.name && campo.name.startsWith('card_')) {
          campo.required = precisaCartao;
        }
      });
    }

    selectMetodoAdesao.addEventListener('change', toggleCartaoAdesao);
    toggleCartaoAdesao();

    // Máscara para número do cartão no modal de adesão
    const adesaoNumberInput = document.getElementById('adesao_number');
    if (adesaoNumberInput) {
      adesaoNumberInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        let formatted = '';
        for (let i = 0; i < value.length && i < 16; i++) {
          if (i > 0 && i % 4 === 0) formatted += ' ';
          formatted += value[i];
        }
        e.target.value = formatted;
      });
    }

    // Máscara para CVV no modal de adesão
    const adesaoCcvInput = document.getElementById('adesao_ccv');
    if (adesaoCcvInput) {
      adesaoCcvInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
      });
    }

    // Máscara para telefone no modal de adesão
    const adesaoPhoneInput = document.getElementById('adesao_phone');
    if (adesaoPhoneInput) {
      adesaoPhoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 11) value = value.substring(0, 11);
        if (value.length > 6) {
          e.target.value = '(' + value.substring(0, 2) + ') ' + value.substring(2, 7) + '-' + value.substring(7);
        } else if (value.length > 2) {
          e.target.value = '(' + value.substring(0, 2) + ') ' + value.substring(2);
        } else if (value.length > 0) {
          e.target.value = '(' + value;
        } else {
          e.target.value = '';
        }
      });
    }

    // Máscara para CEP no modal de adesão
    const adesaoPostalCodeInput = document.getElementById('adesao_postalCode');
    if (adesaoPostalCodeInput) {
      adesaoPostalCodeInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 8) value = value.substring(0, 8);
        if (value.length > 5) {
          e.target.value = value.substring(0, 5) + '-' + value.substring(5);
        } else {
          e.target.value = value;
        }
      });
    }
  }
});
</script>
@endsection
