@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/swiper/swiper.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/cards-advance.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/swiper/swiper.js')}}"></script>
<script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Gráfico de Locações dos Últimos 7 Dias
  const locacoesChartEl = document.querySelector('#locacoesChart');
  if (locacoesChartEl) {
    const locacoesChart = new ApexCharts(locacoesChartEl, {
      series: [{
        name: 'Locações',
        data: {!! json_encode($locacoesUltimos7Dias) !!}
      }],
      chart: {
        height: 200,
        type: 'bar',
        toolbar: { show: false }
      },
      plotOptions: {
        bar: {
          borderRadius: 8,
          columnWidth: '45%',
          distributed: true
        }
      },
      colors: ['#e7d8fc', '#e7d8fc', '#e7d8fc', '#e7d8fc', '#e7d8fc', '#7367f0', '#e7d8fc'],
      dataLabels: { enabled: false },
      legend: { show: false },
      xaxis: {
        categories: {!! json_encode($labelsUltimos7Dias) !!},
        axisBorder: { show: false },
        axisTicks: { show: false },
        labels: { style: { colors: '#697a8d', fontSize: '12px' } }
      },
      yaxis: { 
        show: false,
        labels: { formatter: function(val) { return Math.floor(val); } }
      },
      tooltip: {
        y: { formatter: function(val) { return Math.floor(val); } }
      },
      grid: { show: false }
    });
    locacoesChart.render();
  }

  // Gráfico de Faturamento dos Últimos 6 Meses
  const faturamentoChartEl = document.querySelector('#faturamentoChart');
  if (faturamentoChartEl) {
    const faturamentoChart = new ApexCharts(faturamentoChartEl, {
      series: [{
        name: 'Faturamento',
        data: {!! json_encode($faturamentoUltimos6Meses) !!}
      }],
      chart: {
        height: 100,
        type: 'area',
        sparkline: { enabled: true }
      },
      stroke: { curve: 'smooth', width: 2 },
      colors: ['#28c76f'],
      fill: {
        type: 'gradient',
        gradient: {
          shadeIntensity: 0.8,
          opacityFrom: 0.5,
          opacityTo: 0.1,
          stops: [0, 100]
        }
      },
      tooltip: {
        y: { formatter: function(val) { return 'R$ ' + val.toLocaleString('pt-BR', {minimumFractionDigits: 2}); } }
      }
    });
    faturamentoChart.render();
  }

  // Gráfico de Logística (Donut)
  const logisticaChartEl = document.querySelector('#logisticaChart');
  if (logisticaChartEl) {
    const logisticaChart = new ApexCharts(logisticaChartEl, {
      series: [{{ $logisticaParaSeparar }}, {{ $logisticaProntoPatio }}, {{ $logisticaEmRota }}, {{ $logisticaEntregue }}, {{ $logisticaAguardandoColeta }}],
      chart: {
        height: 200,
        type: 'donut'
      },
      labels: ['Para Separar', 'Pronto no Pátio', 'Em Rota', 'Entregue', 'Aguardando Coleta'],
      colors: ['#ff9f43', '#00cfe8', '#7367f0', '#28c76f', '#ea5455'],
      stroke: { width: 0 },
      dataLabels: { enabled: false },
      legend: { show: false },
      plotOptions: {
        pie: {
          donut: {
            size: '75%',
            labels: {
              show: true,
              total: {
                show: true,
                label: 'Total',
                formatter: function(w) {
                  return w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                }
              }
            }
          }
        }
      }
    });
    logisticaChart.render();
  }

  // Swiper para o card principal
  new Swiper('#swiper-with-pagination-cards', {
    loop: true,
    autoplay: { delay: 5000, disableOnInteraction: false },
    pagination: { el: '.swiper-pagination', clickable: true }
  });

  const modalAssinarPlano = document.getElementById('modalAssinarPlano');
  const formAssinarPlano = document.getElementById('formAssinarPlanoDashboard');

  if (modalAssinarPlano && formAssinarPlano) {
    const camposCartao = document.getElementById('camposCartaoAssinar');
    const selectAdesao = document.getElementById('modal_metodo_adesao');
    const selectMensal = document.getElementById('modal_metodo_mensal');
    const cardNumberInput = document.getElementById('card_number');
    const cardCcvInput = document.getElementById('card_ccv');
    const cardPhoneInput = document.getElementById('card_phone');
    const cardPostalCodeInput = document.getElementById('card_postalCode');

    // Função para verificar se precisa mostrar campos de cartão
    function toggleCamposCartao() {
      const precisaCartao = selectAdesao.value === 'CREDIT_CARD' || selectMensal.value === 'CREDIT_CARD';
      if (camposCartao) {
        camposCartao.classList.toggle('d-none', !precisaCartao);

        // Ajustar required dos campos
        const campos = camposCartao.querySelectorAll('input, select');
        campos.forEach(campo => {
          if (campo.name && campo.name.startsWith('card_')) {
            campo.required = precisaCartao;
          }
        });
      }
    }

    // Máscara para número do cartão
    if (cardNumberInput) {
      cardNumberInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        let formatted = '';
        for (let i = 0; i < value.length && i < 16; i++) {
          if (i > 0 && i % 4 === 0) formatted += ' ';
          formatted += value[i];
        }
        e.target.value = formatted;
      });
    }

    // Máscara para CVV
    if (cardCcvInput) {
      cardCcvInput.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
      });
    }

    // Máscara para telefone
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

    // Máscara para CEP
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

    selectAdesao.addEventListener('change', toggleCamposCartao);
    selectMensal.addEventListener('change', toggleCamposCartao);

    modalAssinarPlano.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) {
        return;
      }

      const idPlano = button.getAttribute('data-id-plano');
      const nomePlano = button.getAttribute('data-nome-plano') || 'Plano selecionado';
      const valorPlano = button.getAttribute('data-valor-plano') || '0,00';
      const adesaoPlano = button.getAttribute('data-adesao-plano') || '0,00';

      const nomeEl = document.getElementById('modalAssinarPlanoNome');
      const valoresEl = document.getElementById('modalAssinarPlanoValores');

      if (nomeEl) {
        nomeEl.textContent = nomePlano;
      }

      if (valoresEl) {
        valoresEl.textContent = `Mensalidade: R$ ${valorPlano} | Adesão: R$ ${adesaoPlano}`;
      }

      formAssinarPlano.action = `{{ url('/planos/assinar') }}/${idPlano}`;

      // Reset e verificar campos do cartão
      toggleCamposCartao();
    });
  }
});
</script>
@endsection

@section('content')

@if(session('success'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
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

@if($errors->any())
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-danger" role="alert">
      <strong>Não foi possível processar a assinatura.</strong>
      <ul class="mb-0 mt-2">
        @foreach($errors->all() as $erro)
          <li>{{ $erro }}</li>
        @endforeach
      </ul>
    </div>
  </div>
</div>
@endif

@if($bloqueadoPorInadimplencia ?? false)
<!-- Banner de Bloqueio por Inadimplência -->
<div class="row mb-4">
  <div class="col-12">
    <div class="card border-danger">
      <div class="card-body">
        <div class="d-flex align-items-center mb-3">
          <span class="badge bg-danger rounded-pill p-2 me-3"><i class="ti ti-alert-triangle ti-md"></i></span>
          <div>
            <h5 class="mb-1 text-danger">Sistema Bloqueado por Inadimplência</h5>
            <p class="mb-0 text-muted">Sua empresa possui pendências financeiras que precisam ser regularizadas.</p>
          </div>
        </div>
        
        @if(strtolower(auth()->user()->finalidade ?? '') === 'administrador')
        <div class="alert alert-warning mb-3">
          <h6 class="alert-heading"><i class="ti ti-info-circle me-1"></i> Informações importantes:</h6>
          <ul class="mb-0">
            <li>O acesso ao sistema está temporariamente suspenso devido a pagamentos em atraso.</li>
            <li>Regularize as pendências para restaurar o acesso completo imediatamente.</li>
            <li>Após a confirmação do pagamento, o sistema será desbloqueado automaticamente.</li>
            <li>Se precisar de ajuda, entre em contato com nosso suporte.</li>
          </ul>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="{{ route('billing.meu-financeiro.index') }}" class="btn btn-primary">
            <i class="ti ti-wallet me-1"></i> Acessar Meu Financeiro
          </a>
          <a href="https://wa.me/5519986116778" target="_blank" class="btn btn-outline-secondary">
            <i class="ti ti-brand-whatsapp me-1"></i> Falar com Suporte
          </a>
        </div>
        @else
        <div class="alert alert-secondary mb-0">
          <i class="ti ti-lock me-2"></i>
          O acesso ao sistema está temporariamente suspenso devido a pendências financeiras. 
          Entre em contato com o administrador da sua empresa para regularizar a situação.
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@elseif($testeBloqueado ?? false)
<!-- Banner de Teste Expirado -->
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-danger d-flex align-items-center" role="alert">
      <span class="alert-icon rounded-circle"><i class="ti ti-alert-circle ti-sm"></i></span>
      <span class="ms-2">
        <strong>Período de Teste Expirado</strong> - Seu período de teste terminou. Para continuar usando o GestorNow, escolha um dos planos abaixo.
      </span>
    </div>
  </div>
</div>
@elseif($semPlanoAtivo ?? false)
<!-- Banner de Sem Plano Ativo -->
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-warning d-flex align-items-center" role="alert">
      <span class="alert-icon rounded-circle"><i class="ti ti-info-circle ti-sm"></i></span>
      <span class="ms-2">
        <strong>Sem Plano Ativo</strong> - Você não possui um plano ativo. Escolha um dos planos abaixo para continuar usando o sistema.
      </span>
    </div>
  </div>
</div>
@elseif($emTeste ?? false)
<!-- Banner de Período de Teste -->
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-warning d-flex align-items-center" role="alert">
      <span class="alert-icon rounded-circle"><i class="ti ti-clock ti-sm"></i></span>
      <span class="ms-2">
        <strong>Período de Teste</strong> - Você ainda tem <strong>{{ $diasRestantesTeste }} {{ $diasRestantesTeste == 1 ? 'dia' : 'dias' }}</strong> restantes para testar o GestorNow. Escolha um plano abaixo para continuar usando após o teste.
      </span>
    </div>
  </div>
</div>
@endif

@if($assinaturaPendentePagamento ?? false)
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
      <span>
        <strong>Pagamento de adesão pendente</strong> - Regularize seu pagamento para ativar o plano e liberar o sistema.
      </span>
      <a href="{{ route('billing.meu-financeiro.index') }}" class="btn btn-sm btn-primary">Ir para Meu Financeiro</a>
    </div>
  </div>
</div>
@endif

@if(($onboardingDadosPendente ?? false) || ($onboardingContratoPendente ?? false))
<div class="row mb-4">
  <div class="col-12">
    <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2" role="alert">
      <span>
        <strong>Onboarding obrigatório pendente</strong> - Complete os dados cadastrais e assine o contrato digital para acessar o sistema.
      </span>
      <a href="{{ route('onboarding.index') }}" class="btn btn-sm btn-info">Continuar Onboarding</a>
    </div>
  </div>
</div>
@endif

@if(($emTeste ?? false) || ($testeBloqueado ?? false) || ($semPlanoAtivo ?? false))
<!-- Cards de Planos -->
<div class="row mb-4">
  @foreach($planos as $index => $plano)
  @php
    $cores = ['primary', 'success', 'info', 'warning'];
    $cor = $cores[$index % count($cores)];
    $nomePlanoNormalizado = strtolower(trim((string) $plano->nome));
    $nomePlanoNormalizado = preg_replace('/^plano\s+/i', '', $nomePlanoNormalizado);
    $valorMensalExibicao = (float) ($plano->valor_exibicao ?? $plano->valor ?? 0);
    $valorAdesaoExibicao = (float) ($plano->adesao_exibicao ?? $plano->adesao ?? 0);

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

    $recursosPlano = $recursosFixosPorPlano[$nomePlanoNormalizado] ?? null;

    // Fallback para planos fora do padrão Start/Pro/Plus/Premium.
    if (is_null($recursosPlano)) {
      $modulosPlanoRaw = collect($plano->modulos ?? [])
        ->filter(function ($moduloPlano) {
          return !empty($moduloPlano->modulo);
        });

      $idsComFilhosNoPlano = $modulosPlanoRaw
        ->map(function ($moduloPlano) {
          return $moduloPlano->modulo->id_modulo_pai ?? null;
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
          return !in_array($nomeModulo, $modulosExcluidos);
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
          $ordemGrupo = $itens->pluck('ordem')->filter(fn($o) => !is_null($o))->min();
          $categoriaGrupo = $itens->pluck('categoria')->filter(fn($c) => !is_null($c))->min();

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
    }
  @endphp
  <div class="col-xl-3 col-lg-4 col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header bg-{{ $cor }} text-white py-3">
        <h5 class="card-title mb-0 text-white text-center">{{ $plano->nome }}</h5>
      </div>
      <div class="card-body text-center pb-2">
        <div class="my-3">
          <h3 class="fw-bold mb-0">R$ {{ number_format($valorMensalExibicao, 2, ',', '.') }}</h3>
          <small class="text-muted">/mês</small>
          <div class="mt-1">
            <small class="text-muted">
              Adesão: {{ $valorAdesaoExibicao > 0 ? 'R$ ' . number_format($valorAdesaoExibicao, 2, ',', '.') : 'Grátis' }}
            </small>
          </div>
        </div>
        <ul class="list-unstyled mb-3 text-start px-2" style="max-height: 230px; overflow-y: auto;">
          @forelse($recursosPlano as $recurso)
          <li class="mb-2 d-flex align-items-start">
            <i class="ti ti-check text-{{ $cor }} me-2 mt-1"></i>
            <span>{{ $recurso }}</span>
          </li>
          @empty
          <li class="mb-2 text-muted text-center">Sem módulos configurados para este plano.</li>
          @endforelse
        </ul>
      </div>
      <div class="card-footer bg-transparent border-0 pt-0 pb-3">
        <button
          type="button"
          class="btn btn-{{ $cor }} w-100 btn-assinar-plano"
          data-bs-toggle="modal"
          data-bs-target="#modalAssinarPlano"
          data-id-plano="{{ $plano->id_plano }}"
          data-nome-plano="{{ $plano->nome }}"
          data-valor-plano="{{ number_format($valorMensalExibicao, 2, ',', '.') }}"
          data-adesao-plano="{{ number_format($valorAdesaoExibicao, 2, ',', '.') }}"
        >
          Assinar Agora
        </button>
      </div>
    </div>
  </div>
  @endforeach
</div>

<div class="modal fade" id="modalAssinarPlano" tabindex="-1" aria-labelledby="modalAssinarPlanoLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="formAssinarPlanoDashboard" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title" id="modalAssinarPlanoLabel">Confirmar Assinatura</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-primary mb-3">
            <div class="fw-semibold" id="modalAssinarPlanoNome">Plano</div>
            <small id="modalAssinarPlanoValores">Mensalidade e adesão</small>
          </div>

          <div class="mb-3">
            <label class="form-label" for="modal_metodo_adesao">Método da adesão</label>
            <select class="form-select" id="modal_metodo_adesao" name="metodo_adesao" required>
              <option value="PIX">PIX</option>
              <option value="BOLETO">Boleto</option>
              <option value="CREDIT_CARD">Cartão de Crédito</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label" for="modal_metodo_mensal">Método das mensalidades</label>
            <select class="form-select" id="modal_metodo_mensal" name="metodo_mensal" required>
              <option value="BOLETO">Boleto</option>
              <option value="CREDIT_CARD">Cartão de Crédito</option>
            </select>
          </div>

          <!-- Campos de Cartão de Crédito (aparecem quando necessário) -->
          <div id="camposCartaoAssinar" class="d-none">
            <hr>
            <h6 class="mb-3"><i class="ti ti-credit-card me-1"></i> Dados do Cartão</h6>
            <div class="mb-3">
              <label class="form-label" for="card_holderName">Nome no Cartão *</label>
              <input type="text" class="form-control" id="card_holderName" name="card_holderName" placeholder="NOME COMO ESTÁ NO CARTÃO">
            </div>
            <div class="mb-3">
              <label class="form-label" for="card_number">Número do Cartão *</label>
              <input type="text" class="form-control" id="card_number" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19">
            </div>
            <div class="row">
              <div class="col-4 mb-3">
                <label class="form-label" for="card_expiryMonth">Mês *</label>
                <select class="form-select" id="card_expiryMonth" name="card_expiryMonth">
                  <option value="">Mês</option>
                  @for($m = 1; $m <= 12; $m++)
                  <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}</option>
                  @endfor
                </select>
              </div>
              <div class="col-4 mb-3">
                <label class="form-label" for="card_expiryYear">Ano *</label>
                <select class="form-select" id="card_expiryYear" name="card_expiryYear">
                  <option value="">Ano</option>
                  @for($y = date('Y'); $y <= date('Y') + 15; $y++)
                  <option value="{{ $y }}">{{ $y }}</option>
                  @endfor
                </select>
              </div>
              <div class="col-4 mb-3">
                <label class="form-label" for="card_ccv">CVV *</label>
                <input type="text" class="form-control" id="card_ccv" name="card_ccv" placeholder="000" maxlength="4">
              </div>
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
              <label class="form-label" for="card_phone">Telefone (com DDD) *</label>
              <input type="text" class="form-control" id="card_phone" name="card_phone" placeholder="(11) 99999-9999" maxlength="15">
            </div>
            @endif

            @if(!$temCep)
            <div class="mb-3">
              <label class="form-label" for="card_postalCode">CEP *</label>
              <input type="text" class="form-control" id="card_postalCode" name="card_postalCode" placeholder="00000-000" maxlength="9">
            </div>
            @endif

            @if(!$temNumero)
            <div class="mb-3">
              <label class="form-label" for="card_addressNumber">Número do Endereço *</label>
              <input type="text" class="form-control" id="card_addressNumber" name="card_addressNumber" placeholder="123">
            </div>
            @endif

            <div class="alert alert-info mb-0">
              <small><i class="ti ti-lock me-1"></i> Seus dados são criptografados e processados com segurança.</small>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Gerar Cobranças</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

<div class="row">
  <!-- Card Principal - Resumo do Dia -->
  <div class="col-lg-6 mb-4">
    <div class="swiper-container swiper-container-horizontal swiper swiper-card-advance-bg" id="swiper-with-pagination-cards">
      <div class="swiper-wrapper">
        <div class="swiper-slide">
          <div class="row">
            <div class="col-12">
              <h5 class="text-white mb-0 mt-2">Locações do Dia</h5>
              <small>{{ $dataHoje }}</small>
            </div>
            <div class="row">
              <div class="col-lg-7 col-md-9 col-12 order-2 order-md-1">
                <h6 class="text-white mt-0 mt-md-3 mb-3">Movimentações</h6>
                <div class="row g-4">
                  <div class="col-6 pe-3">
                    <ul class="list-unstyled mb-0">
                      <li class="d-flex mb-4 align-items-center">
                        <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $locacoesIniciamHoje }}</p>
                        <p class="mb-0">Iniciam Hoje</p>
                      </li>
                      <li class="d-flex align-items-center mb-2">
                        <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $locacoesTerminamHoje }}</p>
                        <p class="mb-0">Terminam Hoje</p>
                      </li>
                    </ul>
                  </div>
                  <div class="col-6 ps-3">
                    <ul class="list-unstyled mb-0">
                      <li class="d-flex mb-4 align-items-center">
                        <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $locacoesEmAndamento }}</p>
                        <p class="mb-0">Em Andamento</p>
                      </li>
                      <li class="d-flex align-items-center mb-2">
                        <p class="mb-0 fw-semibold me-2 website-analytics-text-bg text-warning">{{ $locacoesAtrasadas }}</p>
                        <p class="mb-0">Atrasadas</p>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
              <div class="col-lg-5 col-md-3 col-12 order-1 order-md-2 my-4 my-md-0 text-center">
                <img src="{{asset('assets/img/illustrations/card-website-analytics-1.png')}}" alt="Locações" width="170" class="card-website-analytics-img">
              </div>
            </div>
          </div>
        </div>
        <div class="swiper-slide">
          <div class="row">
            <div class="col-12">
              <h5 class="text-white mb-0 mt-2">Financeiro do Mês</h5>
              <small>{{ $mesAno }}</small>
            </div>
            <div class="col-lg-7 col-md-9 col-12 order-2 order-md-1">
              <h6 class="text-white mt-0 mt-md-3 mb-3">Resumo</h6>
              <div class="row g-3">
                <div class="col-6 col-lg-3">
                  <div class="d-flex flex-column align-items-start">
                    <p class="mb-1 fw-semibold website-analytics-text-bg" style="font-size: 0.85rem;">R$ {{ number_format($totalRecebidoMes/1000, 1, ',', '.') }}k</p>
                    <small class="text-white-50">Recebido</small>
                  </div>
                </div>
                <div class="col-6 col-lg-3">
                  <div class="d-flex flex-column align-items-start">
                    <p class="mb-1 fw-semibold website-analytics-text-bg" style="font-size: 0.85rem;">R$ {{ number_format($contasReceberMes/1000, 1, ',', '.') }}k</p>
                    <small class="text-white-50">A Receber</small>
                  </div>
                </div>
                <div class="col-6 col-lg-3">
                  <div class="d-flex flex-column align-items-start">
                    <p class="mb-1 fw-semibold website-analytics-text-bg" style="font-size: 0.85rem;">R$ {{ number_format($totalPagoMes/1000, 1, ',', '.') }}k</p>
                    <small class="text-white-50">Pago</small>
                  </div>
                </div>
                <div class="col-6 col-lg-3">
                  <div class="d-flex flex-column align-items-start">
                    <p class="mb-1 fw-semibold website-analytics-text-bg" style="font-size: 0.85rem;">R$ {{ number_format($contasPagarMes/1000, 1, ',', '.') }}k</p>
                    <small class="text-white-50">A Pagar</small>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-lg-5 col-md-3 col-12 order-1 order-md-2 my-4 my-md-0 text-center">
              <img src="{{asset('assets/img/illustrations/card-website-analytics-2.png')}}" alt="Financeiro" width="170" class="card-website-analytics-img">
            </div>
          </div>
        </div>
        <div class="swiper-slide">
          <div class="row">
            <div class="col-12">
              <h5 class="text-white mb-0 mt-2">Logística</h5>
              <small>Status das Entregas</small>
            </div>
            <div class="col-lg-7 col-md-9 col-12 order-2 order-md-1">
              <h6 class="text-white mt-0 mt-md-3 mb-3">Situação Atual</h6>
              <div class="row">
                <div class="col-6">
                  <ul class="list-unstyled mb-0">
                    <li class="d-flex mb-4 align-items-center">
                      <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $logisticaParaSeparar }}</p>
                      <p class="mb-0">Para Separar</p>
                    </li>
                    <li class="d-flex align-items-center mb-2">
                      <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $logisticaEmRota }}</p>
                      <p class="mb-0">Em Rota</p>
                    </li>
                  </ul>
                </div>
                <div class="col-6">
                  <ul class="list-unstyled mb-0">
                    <li class="d-flex mb-4 align-items-center">
                      <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $logisticaProntoPatio }}</p>
                      <p class="mb-0">Pronto Pátio</p>
                    </li>
                    <li class="d-flex align-items-center mb-2">
                      <p class="mb-0 fw-semibold me-2 website-analytics-text-bg">{{ $logisticaAguardandoColeta }}</p>
                      <p class="mb-0">Ag. Coleta</p>
                    </li>
                  </ul>
                </div>
              </div>
            </div>
            <div class="col-lg-5 col-md-3 col-12 order-1 order-md-2 my-4 my-md-0 text-center">
              <img src="{{asset('assets/img/illustrations/card-website-analytics-3.png')}}" alt="Logística" width="170" class="card-website-analytics-img">
            </div>
          </div>
        </div>
      </div>
      <div class="swiper-pagination"></div>
    </div>
  </div>
  <!--/ Card Principal -->

  <!-- Faturamento do Mês -->
  <div class="col-lg-3 col-sm-6 mb-4">
    <div class="card h-100">
      <div class="card-header">
        <div class="d-flex justify-content-between">
          <small class="d-block mb-1 text-muted">Faturamento do Mês</small>
          <p class="card-text {{ $variacaoFaturamento >= 0 ? 'text-success' : 'text-danger' }}">
            {{ $variacaoFaturamento >= 0 ? '+' : '' }}{{ $variacaoFaturamento }}%
          </p>
        </div>
        <h4 class="card-title mb-1">R$ {{ number_format($faturamentoMes, 0, ',', '.') }}</h4>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-4">
            <div class="d-flex gap-2 align-items-center mb-2">
              <span class="badge bg-label-success p-1 rounded"><i class="ti ti-arrow-up ti-xs"></i></span>
              <p class="mb-0">Receber</p>
            </div>
            <h5 class="mb-0 pt-1 text-nowrap">R$ {{ number_format($contasReceberHoje/1000, 1, ',', '.') }}k</h5>
            <small class="text-muted">Hoje</small>
          </div>
          <div class="col-4">
            <div class="divider divider-vertical">
              <div class="divider-text">
                <span class="badge-divider-bg bg-label-secondary">VS</span>
              </div>
            </div>
          </div>
          <div class="col-4 text-end">
            <div class="d-flex gap-2 justify-content-end align-items-center mb-2">
              <p class="mb-0">Pagar</p>
              <span class="badge bg-label-danger p-1 rounded"><i class="ti ti-arrow-down ti-xs"></i></span>
            </div>
            <h5 class="mb-0 pt-1 text-nowrap ms-lg-n3 ms-xl-0">R$ {{ number_format($contasPagarHoje/1000, 1, ',', '.') }}k</h5>
            <small class="text-muted">Hoje</small>
          </div>
        </div>
        <div class="d-flex align-items-center mt-4">
          <div class="progress w-100" style="height: 8px;">
            @php
              $totalFinanceiro = $contasReceberHoje + $contasPagarHoje;
              $percentReceber = $totalFinanceiro > 0 ? ($contasReceberHoje / $totalFinanceiro) * 100 : 50;
            @endphp
            <div class="progress-bar bg-success" style="width: {{ $percentReceber }}%" role="progressbar"></div>
            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ 100 - $percentReceber }}%"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--/ Faturamento do Mês -->

  <!-- Faturamento Gerado -->
  <div class="col-lg-3 col-md-6 col-sm-6 mb-4">
    <div class="card h-100">
      <div class="card-body pb-0">
        <div class="card-icon">
          <span class="badge bg-label-success rounded-pill p-2">
            <i class='ti ti-chart-line ti-sm'></i>
          </span>
        </div>
        <h5 class="card-title mb-0 mt-2">R$ {{ number_format($faturamentoMes/1000, 1, ',', '.') }}k</h5>
        <small>Faturamento do Mês</small>
      </div>
      <div id="faturamentoChart" class="mt-auto"></div>
    </div>
  </div>
  <!--/ Faturamento Gerado -->

  <!-- Locações da Semana -->
  <div class="col-lg-6 mb-4">
    <div class="card h-100">
      <div class="card-header pb-0 d-flex justify-content-between mb-lg-n4">
        <div class="card-title mb-0">
          <h5 class="mb-0">Locações da Semana</h5>
          <small class="text-muted">Últimos 7 Dias</small>
        </div>
        <div class="dropdown">
          <button class="btn p-0" type="button" id="locacoesMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="locacoesMenu">
            @pode('locacoes.visualizar')
              <a class="dropdown-item" href="{{ route('locacoes.index') }}">Ver Todas</a>
            @endpode
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-12 col-md-4 d-flex flex-column align-self-end">
            <div class="d-flex gap-2 align-items-center mb-2 pb-1 flex-wrap">
              <h1 class="mb-0">{{ $locacoesMes }}</h1>
              <div class="badge rounded {{ $variacaoLocacoes >= 0 ? 'bg-label-success' : 'bg-label-danger' }}">
                {{ $variacaoLocacoes >= 0 ? '+' : '' }}{{ $variacaoLocacoes }}%
              </div>
            </div>
            <small class="text-muted">Locações este mês comparado ao anterior</small>
          </div>
          <div class="col-12 col-md-8">
            <div id="locacoesChart"></div>
          </div>
        </div>
        <div class="border rounded p-3 mt-2">
          <div class="row gap-4 gap-sm-0">
            <div class="col-12 col-sm-4">
              <div class="d-flex gap-2 align-items-center">
                <div class="badge rounded bg-label-primary p-1"><i class="ti ti-calendar-event ti-sm"></i></div>
                <h6 class="mb-0">Iniciam Hoje</h6>
              </div>
              <h4 class="my-2 pt-1">{{ $locacoesIniciamHoje }}</h4>
              <div class="progress w-75" style="height:4px">
                <div class="progress-bar" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="col-12 col-sm-4">
              <div class="d-flex gap-2 align-items-center">
                <div class="badge rounded bg-label-info p-1"><i class="ti ti-calendar-check ti-sm"></i></div>
                <h6 class="mb-0">Terminam Hoje</h6>
              </div>
              <h4 class="my-2 pt-1">{{ $locacoesTerminamHoje }}</h4>
              <div class="progress w-75" style="height:4px">
                <div class="progress-bar bg-info" role="progressbar" style="width: 50%" aria-valuenow="50" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>
            <div class="col-12 col-sm-4">
              <div class="d-flex gap-2 align-items-center">
                <div class="badge rounded bg-label-danger p-1"><i class="ti ti-alert-circle ti-sm"></i></div>
                <h6 class="mb-0">Atrasadas</h6>
              </div>
              <h4 class="my-2 pt-1">{{ $locacoesAtrasadas }}</h4>
              <div class="progress w-75" style="height:4px">
                <div class="progress-bar bg-danger" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--/ Locações da Semana -->

  <!-- Status de Logística -->
  <div class="col-md-6 mb-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between pb-0">
        <div class="card-title mb-0">
          <h5 class="mb-0">Logística</h5>
          <small class="text-muted">Status das Entregas</small>
        </div>
        <div class="dropdown">
          <button class="btn p-0" type="button" id="logisticaMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="logisticaMenu">
            @pode('locacoes.visualizar')
              <a class="dropdown-item" href="{{ route('locacoes.index') }}">Ver Locações</a>
            @endpode
          </div>
        </div>
      </div>
      <div class="card-body">
        <div class="row">
          <div class="col-12 col-sm-4 col-md-12 col-lg-4">
            <div class="mt-lg-4 mt-lg-2 mb-lg-4 mb-2 pt-1">
              <h1 class="mb-0">{{ $logisticaParaSeparar + $logisticaProntoPatio + $logisticaEmRota + $logisticaEntregue + $logisticaAguardandoColeta }}</h1>
              <p class="mb-0">Total de Pedidos</p>
            </div>
            <ul class="p-0 m-0">
              <li class="d-flex gap-3 align-items-center mb-lg-3 pt-2 pb-1">
                <div class="badge rounded bg-label-warning p-1"><i class="ti ti-box ti-sm"></i></div>
                <div>
                  <h6 class="mb-0 text-nowrap">Para Separar</h6>
                  <small class="text-muted">{{ $logisticaParaSeparar }}</small>
                </div>
              </li>
              <li class="d-flex gap-3 align-items-center mb-lg-3 pb-1">
                <div class="badge rounded bg-label-info p-1"><i class="ti ti-building-warehouse ti-sm"></i></div>
                <div>
                  <h6 class="mb-0 text-nowrap">Pronto no Pátio</h6>
                  <small class="text-muted">{{ $logisticaProntoPatio }}</small>
                </div>
              </li>
              <li class="d-flex gap-3 align-items-center mb-lg-3 pb-1">
                <div class="badge rounded bg-label-primary p-1"><i class="ti ti-truck ti-sm"></i></div>
                <div>
                  <h6 class="mb-0 text-nowrap">Em Rota</h6>
                  <small class="text-muted">{{ $logisticaEmRota }}</small>
                </div>
              </li>
              <li class="d-flex gap-3 align-items-center pb-1">
                <div class="badge rounded bg-label-danger p-1"><i class="ti ti-truck-return ti-sm"></i></div>
                <div>
                  <h6 class="mb-0 text-nowrap">Aguardando Coleta</h6>
                  <small class="text-muted">{{ $logisticaAguardandoColeta }}</small>
                </div>
              </li>
            </ul>
          </div>
          <div class="col-12 col-sm-8 col-md-12 col-lg-8">
            <div id="logisticaChart"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--/ Status de Logística -->

  <!-- Top Clientes -->
  <div class="col-xl-4 col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <div class="card-title mb-0">
          <h5 class="m-0 me-2">Clientes que Mais Locam</h5>
          <small class="text-muted">Top 5 do Sistema</small>
        </div>
        <div class="dropdown">
          <button class="btn p-0" type="button" id="topClientesMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="topClientesMenu">
            @pode('clientes.visualizar')
              <a class="dropdown-item" href="{{ route('clientes.index') }}">Ver Todos</a>
            @endpode
          </div>
        </div>
      </div>
      <div class="card-body">
        <ul class="p-0 m-0">
          @forelse($topClientes as $index => $cliente)
          <li class="d-flex align-items-center {{ !$loop->last ? 'mb-4' : '' }}">
            <div class="avatar me-3">
              <span class="avatar-initial rounded-circle bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][$index % 5] }}">
                {{ strtoupper(substr($cliente->nome, 0, 2)) }}
              </span>
            </div>
            <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
              <div class="me-2">
                <div class="d-flex align-items-center">
                  <h6 class="mb-0 me-1">{{ Str::limit($cliente->nome, 20) }}</h6>
                </div>
                <small class="text-muted">{{ $cliente->total_locacoes }} locações</small>
              </div>
              <div class="user-progress">
                <p class="text-success fw-semibold mb-0">
                  R$ {{ number_format($cliente->valor_total / 1000, 1, ',', '.') }}k
                </p>
              </div>
            </div>
          </li>
          @empty
          <li class="text-center text-muted py-4">
            <i class="ti ti-users-off ti-lg mb-2"></i>
            <p class="mb-0">Nenhum cliente encontrado</p>
          </li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
  <!--/ Top Clientes -->

  <!-- Top Produtos -->
  <div class="col-12 col-xl-4 mb-4 col-md-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between pb-1">
        <h5 class="mb-0 card-title">Produtos Mais Locados</h5>
        <div class="dropdown">
          <button class="btn p-0" type="button" id="topProdutosMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="topProdutosMenu">
            @pode('produtos.visualizar')
              <a class="dropdown-item" href="{{ route('produtos.index') }}">Ver Todos</a>
            @endpode
          </div>
        </div>
      </div>
      <div class="card-body">
        @forelse($topProdutos as $index => $produto)
        <div class="d-flex align-items-start {{ !$loop->last ? 'mb-4' : '' }}">
          <div class="badge rounded bg-label-{{ ['primary', 'success', 'info', 'warning', 'danger'][$index % 5] }} p-2 me-3 rounded">
            <i class="ti ti-package ti-sm"></i>
          </div>
          <div class="d-flex justify-content-between w-100 gap-2 align-items-center">
            <div class="me-2">
              <h6 class="mb-0">{{ Str::limit($produto->nome, 25) }}</h6>
              <small class="text-muted">{{ $produto->codigo ?? 'Sem código' }}</small>
            </div>
            <div class="text-end">
              <p class="mb-0 text-success fw-semibold">{{ $produto->total_quantidade }} un</p>
              <small class="text-muted">{{ $produto->total_locacoes }} loc.</small>
            </div>
          </div>
        </div>
        @empty
        <div class="text-center text-muted py-4">
          <i class="ti ti-package-off ti-lg mb-2"></i>
          <p class="mb-0">Nenhum produto encontrado</p>
        </div>
        @endforelse
      </div>
    </div>
  </div>
  <!--/ Top Produtos -->

  <!-- Manutenções em Andamento -->
  <div class="col-xl-4 col-md-6 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between">
        <div class="card-title mb-0">
          <h5 class="mb-0">Manutenções em Andamento</h5>
          <small class="text-muted">{{ $totalManutencoesAndamento }} produto(s) em manutenção</small>
        </div>
        <div class="dropdown">
          <button class="btn p-0" type="button" id="manutencoesMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="manutencoesMenu">
            <a class="dropdown-item" href="{{ route('manutencoes.index') }}">Ver Todas</a>
          </div>
        </div>
      </div>
      <div class="card-body">
        <ul class="p-0 m-0">
          @forelse($manutencoesEmAndamento as $manutencao)
          <li class="mb-4 pb-1 d-flex justify-content-between align-items-center">
            <div class="badge bg-label-{{ $manutencao->tipo == 'emergencial' ? 'danger' : ($manutencao->tipo == 'corretiva' ? 'warning' : 'info') }} rounded p-2">
              <i class="ti ti-tool ti-sm"></i>
            </div>
            <div class="d-flex justify-content-between w-100 flex-wrap">
              <div class="ms-3">
                <h6 class="mb-0">{{ Str::limit($manutencao->produto->nome ?? 'Produto', 20) }}</h6>
                <small class="text-muted">{{ ucfirst($manutencao->tipo ?? 'Manutenção') }}</small>
              </div>
              <div class="d-flex flex-column align-items-end">
                <p class="mb-0 fw-semibold">{{ $manutencao->quantidade ?? 1 }} un</p>
                @if($manutencao->data_previsao)
                <small class="text-muted">Prev: {{ $manutencao->data_previsao->format('d/m') }}</small>
                @endif
              </div>
            </div>
          </li>
          @empty
          <li class="text-center text-muted py-4">
            <i class="ti ti-mood-happy ti-lg mb-2 d-block"></i>
            <p class="mb-0">Nenhum produto em manutenção</p>
          </li>
          @endforelse
        </ul>
      </div>
    </div>
  </div>
  <!--/ Manutenções em Andamento -->

  <!-- Contas Vencidas -->
  <div class="col-xl-4 col-md-6 order-2 order-lg-1 mb-4">
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <div class="card-title mb-0">
          <h5 class="mb-0">Contas Vencidas</h5>
          <small class="text-muted">Atenção Necessária</small>
        </div>
        <div class="dropdown">
          <button class="btn p-0" type="button" id="contasVencidasMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="ti ti-dots-vertical ti-sm text-muted"></i>
          </button>
          <div class="dropdown-menu dropdown-menu-end" aria-labelledby="contasVencidasMenu">
            @pode('financeiro.contas-receber.visualizar')
              <a class="dropdown-item" href="{{ route('financeiro.contas-a-receber.index') }}">Ver A Receber</a>
            @endpode
            @pode('financeiro.contas-pagar.visualizar')
              <a class="dropdown-item" href="{{ route('financeiro.index') }}">Ver A Pagar</a>
            @endpode
          </div>
        </div>
      </div>
      <div class="card-body">
        <ul class="list-unstyled mb-0">
          <li class="mb-3 pb-1">
            <div class="d-flex align-items-start">
              <div class="badge bg-label-success p-2 me-3 rounded"><i class="ti ti-arrow-up ti-sm"></i></div>
              <div class="d-flex justify-content-between w-100 flex-wrap gap-2">
                <div class="me-2">
                  <h6 class="mb-0">A Receber Vencidas</h6>
                  <small class="text-muted">Valores em atraso</small>
                </div>
                <div class="d-flex align-items-center">
                  <p class="mb-0 text-danger fw-semibold">R$ {{ number_format($contasReceberVencidas, 2, ',', '.') }}</p>
                </div>
              </div>
            </div>
          </li>
          <li class="mb-3 pb-1">
            <div class="d-flex align-items-start">
              <div class="badge bg-label-danger p-2 me-3 rounded"><i class="ti ti-arrow-down ti-sm"></i></div>
              <div class="d-flex justify-content-between w-100 flex-wrap gap-2">
                <div class="me-2">
                  <h6 class="mb-0">A Pagar Vencidas</h6>
                  <small class="text-muted">Contas em atraso</small>
                </div>
                <div class="d-flex align-items-center">
                  <p class="mb-0 text-danger fw-semibold">R$ {{ number_format($contasPagarVencidas, 2, ',', '.') }}</p>
                </div>
              </div>
            </div>
          </li>
          <li class="mb-3 pb-1">
            <div class="d-flex align-items-start">
              <div class="badge bg-label-primary p-2 me-3 rounded"><i class="ti ti-wallet ti-sm"></i></div>
              <div class="d-flex justify-content-between w-100 flex-wrap gap-2">
                <div class="me-2">
                  <h6 class="mb-0">Recebido este Mês</h6>
                  <small class="text-muted">Total recebido</small>
                </div>
                <div class="d-flex align-items-center">
                  <p class="mb-0 text-success fw-semibold">R$ {{ number_format($totalRecebidoMes, 2, ',', '.') }}</p>
                </div>
              </div>
            </div>
          </li>
          <li class="mb-2">
            <div class="d-flex align-items-start">
              <div class="badge bg-label-secondary p-2 me-3 rounded"><i class="ti ti-receipt ti-sm"></i></div>
              <div class="d-flex justify-content-between w-100 flex-wrap gap-2">
                <div class="me-2">
                  <h6 class="mb-0">Pago este Mês</h6>
                  <small class="text-muted">Total pago</small>
                </div>
                <div class="d-flex align-items-center">
                  <p class="mb-0">R$ {{ number_format($totalPagoMes, 2, ',', '.') }}</p>
                </div>
              </div>
            </div>
          </li>
        </ul>
      </div>
    </div>
  </div>
  <!--/ Contas Vencidas -->

  <!-- Resumo Rápido -->
  <div class="col-12 col-xl-8 col-sm-12 order-1 order-lg-2 mb-4">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">Resumo Rápido</h5>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3 col-6">
            <div class="d-flex align-items-center">
              <div class="badge rounded-pill bg-label-primary me-3 p-2">
                <i class="ti ti-calendar-stats ti-md"></i>
              </div>
              <div>
                <h6 class="mb-0">{{ $locacoesMes }}</h6>
                <small class="text-muted">Locações do Mês</small>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="d-flex align-items-center">
              <div class="badge rounded-pill bg-label-success me-3 p-2">
                <i class="ti ti-currency-real ti-md"></i>
              </div>
              <div>
                <h6 class="mb-0">R$ {{ number_format($faturamentoMes/1000, 1, ',', '.') }}k</h6>
                <small class="text-muted">Faturamento</small>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="d-flex align-items-center">
              <div class="badge rounded-pill bg-label-warning me-3 p-2">
                <i class="ti ti-tool ti-md"></i>
              </div>
              <div>
                <h6 class="mb-0">{{ $totalManutencoesAndamento }}</h6>
                <small class="text-muted">Em Manutenção</small>
              </div>
            </div>
          </div>
          <div class="col-md-3 col-6">
            <div class="d-flex align-items-center">
              <div class="badge rounded-pill bg-label-danger me-3 p-2">
                <i class="ti ti-alert-triangle ti-md"></i>
              </div>
              <div>
                <h6 class="mb-0">{{ $locacoesAtrasadas }}</h6>
                <small class="text-muted">Atrasadas</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--/ Resumo Rápido -->

  <!-- Informações da Empresa -->
  <div class="col-12 mb-4">
    <div class="card h-100">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="card-title mb-0">Minha Empresa</h5>
        <span class="badge bg-label-primary">{{ $empresa->status ?? 'ativo' }}</span>
      </div>
      <div class="card-body">
        <div class="row">
          <!-- Coluna da Logo e Nome -->
          <div class="col-md-4 col-12 mb-4 mb-md-0">
            <div class="d-flex flex-column align-items-center text-center">
              @if($logoUrl)
              <div class="avatar avatar-xl mb-3">
                <img src="{{ $logoUrl }}" alt="Logo" class="rounded" style="width: 80px; height: 80px; object-fit: contain;">
              </div>
              @else
              <div class="avatar avatar-xl mb-3">
                <span class="avatar-initial rounded bg-label-primary" style="width: 80px; height: 80px;">
                  <i class="ti ti-building ti-xl"></i>
                </span>
              </div>
              @endif
              <h5 class="mb-1">{{ $empresa->nome_empresa ?? $empresa->razao_social ?? 'Empresa' }}</h5>
              @if($empresa->razao_social && $empresa->nome_empresa)
              <small class="text-muted">{{ Str::limit($empresa->razao_social, 30) }}</small>
              @endif
            </div>
          </div>
          
          <!-- Coluna de Informações -->
          <div class="col-md-8 col-12">
            <div class="row g-3">
              @if($empresa->cnpj)
              <div class="col-md-6 col-12">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-secondary p-2 me-3 rounded">
                    <i class="ti ti-id ti-sm"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">CNPJ</small>
                    <span class="fw-semibold">{{ preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $empresa->cnpj) }}</span>
                  </div>
                </div>
              </div>
              @elseif($empresa->cpf)
              <div class="col-md-6 col-12">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-secondary p-2 me-3 rounded">
                    <i class="ti ti-id ti-sm"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">CPF</small>
                    <span class="fw-semibold">{{ preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $empresa->cpf) }}</span>
                  </div>
                </div>
              </div>
              @endif
              
              @if($empresa->telefone)
              <div class="col-md-6 col-12">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-secondary p-2 me-3 rounded">
                    <i class="ti ti-phone ti-sm"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">Telefone</small>
                    <span class="fw-semibold">{{ $empresa->telefone }}</span>
                  </div>
                </div>
              </div>
              @endif
              
              @if($empresa->email)
              <div class="col-md-6 col-12">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-secondary p-2 me-3 rounded">
                    <i class="ti ti-mail ti-sm"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">E-mail</small>
                    <span class="fw-semibold">{{ $empresa->email }}</span>
                  </div>
                </div>
              </div>
              @endif
              
              @if($empresa->endereco)
              <div class="col-12">
                <div class="d-flex align-items-center">
                  <div class="badge bg-label-secondary p-2 me-3 rounded">
                    <i class="ti ti-map-pin ti-sm"></i>
                  </div>
                  <div>
                    <small class="text-muted d-block">Endereço</small>
                    <span class="fw-semibold">{{ $empresa->endereco }}{{ $empresa->numero ? ', ' . $empresa->numero : '' }}</span>
                    @if($empresa->bairro || $empresa->cidade)
                    <span class="text-muted"> - {{ $empresa->bairro }}{{ $empresa->cidade ? ', ' . $empresa->cidade : '' }}{{ $empresa->uf ? '/' . $empresa->uf : '' }}</span>
                    @endif
                  </div>
                </div>
              </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!--/ Informações da Empresa -->
</div>

@endsection
