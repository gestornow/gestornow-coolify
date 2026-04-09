@extends('layouts.layoutMaster')

@section('title', 'Dashboard de Gestão de Locações')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/apex-charts/apex-charts.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <!-- Cabeçalho -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="ti ti-dashboard ti-sm me-2"></i>
                Dashboard de Gestão de Locações
            </h4>
            <p class="text-muted mb-0">Visão completa do desempenho financeiro e operacional</p>
        </div>
        <div>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary" onclick="atualizarDashboard()">
                    <i class="ti ti-refresh me-1"></i>
                    Atualizar
                </button>
                <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('relatorios.fluxo-caixa') }}"><i class="ti ti-chart-line me-2"></i>Fluxo de Caixa</a></li>
                    <li><a class="dropdown-item" href="{{ route('relatorios.recebimentos-status') }}"><i class="ti ti-file-invoice me-2"></i>Recebimentos</a></li>
                    <li><a class="dropdown-item" href="{{ route('relatorios.analise-propriedade') }}"><i class="ti ti-building me-2"></i>Análise por Propriedade</a></li>
                    <li><a class="dropdown-item" href="{{ route('relatorios.projecao-fluxo') }}"><i class="ti ti-chart-arrows me-2"></i>Projeção de Fluxo</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Filtros Globais -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="formFiltros">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Período</label>
                        <select class="form-select" id="periodo" name="periodo">
                            <option value="hoje">Hoje</option>
                            <option value="semana">Esta Semana</option>
                            <option value="mes" selected>Este Mês</option>
                            <option value="trimestre">Este Trimestre</option>
                            <option value="ano">Este Ano</option>
                            <option value="personalizado">Personalizado</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="divDataInicio" style="display: none;">
                        <label class="form-label">Data Início</label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio">
                    </div>
                    <div class="col-md-3" id="divDataFim" style="display: none;">
                        <label class="form-label">Data Fim</label>
                        <input type="date" class="form-control" id="data_fim" name="data_fim">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Propriedade</label>
                        <select class="form-select select2" id="id_propriedade" name="id_propriedade">
                            <option value="">Todas</option>
                            <!-- Será preenchido dinamicamente -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-primary w-100" onclick="atualizarDashboard()">
                            <i class="ti ti-filter me-1"></i>
                            Aplicar Filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Cards de KPIs Principais -->
    <div class="row g-4 mb-4">
        <!-- Taxa de Ocupação -->
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">Taxa de Ocupação</span>
                            <div class="d-flex align-items-center">
                                <h4 class="mb-0 me-2" id="kpi-taxa-ocupacao">--</h4>
                                <p class="text-success mb-0" id="kpi-taxa-ocupacao-variacao">(--)</p>
                            </div>
                            <small class="text-muted">vs. mês anterior</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="ti ti-home-check ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Taxa de Inadimplência -->
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">Taxa de Inadimplência</span>
                            <div class="d-flex align-items-center">
                                <h4 class="mb-0 me-2" id="kpi-inadimplencia">--</h4>
                                <p class="mb-0" id="kpi-inadimplencia-variacao">(--)</p>
                            </div>
                            <small class="text-muted">vs. mês anterior</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-danger">
                                <i class="ti ti-alert-circle ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Receita Mensal -->
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">Receita Mensal</span>
                            <div class="d-flex align-items-center">
                                <h4 class="mb-0 me-2" id="kpi-receita">R$ --</h4>
                                <p class="text-success mb-0" id="kpi-receita-variacao">(--)</p>
                            </div>
                            <small class="text-muted">vs. mês anterior</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="ti ti-currency-dollar ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ROI Médio -->
        <div class="col-xl-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="content-left">
                            <span class="text-muted d-block mb-1">ROI Médio</span>
                            <div class="d-flex align-items-center">
                                <h4 class="mb-0 me-2" id="kpi-roi">--</h4>
                                <p class="text-success mb-0" id="kpi-roi-variacao">(--)</p>
                            </div>
                            <small class="text-muted">Retorno sobre investimento</small>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="ti ti-trending-up ti-md"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Gráfico de Evolução do Fluxo de Caixa -->
        <div class="col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Fluxo de Caixa</h5>
                        <small class="text-muted">Evolução mensal de entradas e saídas</small>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-text-secondary rounded-pill" type="button" id="dropdownFluxo" data-bs-toggle="dropdown">
                            <i class="ti ti-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="{{ route('relatorios.fluxo-caixa') }}">Ver Relatório Completo</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0);">Exportar PDF</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0);">Exportar Excel</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div id="chartFluxoCaixa"></div>
                </div>
            </div>
        </div>

        <!-- Cards de Totais Rápidos -->
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Resumo Financeiro</h5>
                    <small class="text-muted">Período selecionado</small>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Total Recebido</span>
                            <span class="badge bg-label-success">Entradas</span>
                        </div>
                        <h4 class="mb-0 text-success" id="total-recebido">R$ 0,00</h4>
                    </div>
                    <hr class="my-4">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Total Pago</span>
                            <span class="badge bg-label-danger">Saídas</span>
                        </div>
                        <h4 class="mb-0 text-danger" id="total-pago">R$ 0,00</h4>
                    </div>
                    <hr class="my-4">
                    <div class="mb-4">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">Saldo Período</span>
                            <span class="badge bg-label-primary">Líquido</span>
                        </div>
                        <h4 class="mb-0 text-primary" id="saldo-periodo">R$ 0,00</h4>
                    </div>
                    <hr class="my-4">
                    <div>
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="text-muted">A Receber</span>
                            <span class="badge bg-label-warning">Pendente</span>
                        </div>
                        <h4 class="mb-0 text-warning" id="total-pendente">R$ 0,00</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Status de Recebimentos -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Status de Recebimentos</h5>
                        <small class="text-muted">Distribuição por situação</small>
                    </div>
                    <a href="{{ route('relatorios.recebimentos-status') }}" class="btn btn-sm btn-text-primary">
                        Ver todos <i class="ti ti-chevron-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div id="chartRecebimentos"></div>
                    <div class="mt-4">
                        <div class="d-flex justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-success badge-dot me-2"></div>
                                <span>Recebido</span>
                            </div>
                            <span class="fw-semibold" id="status-recebido">R$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-info badge-dot me-2"></div>
                                <span>A Vencer (30 dias)</span>
                            </div>
                            <span class="fw-semibold" id="status-a-vencer">R$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-warning badge-dot me-2"></div>
                                <span>Vencido (1-30 dias)</span>
                            </div>
                            <span class="fw-semibold" id="status-vencido">R$ 0,00</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="badge bg-danger badge-dot me-2"></div>
                                <span>Inadimplente (>30 dias)</span>
                            </div>
                            <span class="fw-semibold" id="status-inadimplente">R$ 0,00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top 5 Propriedades por Rentabilidade -->
        <div class="col-xl-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Top Propriedades</h5>
                        <small class="text-muted">Por rentabilidade</small>
                    </div>
                    <a href="{{ route('relatorios.analise-propriedade') }}" class="btn btn-sm btn-text-primary">
                        Ver análise <i class="ti ti-chevron-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <div id="listaTopPropriedades">
                        <!-- Será preenchido dinamicamente -->
                        <div class="text-center text-muted py-5">
                            <i class="ti ti-loader ti-lg mb-3"></i>
                            <p>Carregando dados...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas e Notificações -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Alertas e Pendências</h5>
                </div>
                <div class="card-body">
                    <div id="listaAlertas">
                        <!-- Será preenchido dinamicamente -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/apex-charts/apexcharts.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Inicializar Select2
    $('.select2').select2({
        placeholder: 'Selecione...',
        allowClear: true
    });

    // Controle do período personalizado
    $('#periodo').on('change', function() {
        if ($(this).val() === 'personalizado') {
            $('#divDataInicio, #divDataFim').show();
        } else {
            $('#divDataInicio, #divDataFim').hide();
        }
    });

    // Carregar dados iniciais
    atualizarDashboard();
});

let chartFluxoCaixa, chartRecebimentos;

function atualizarDashboard() {
    const formData = new FormData(document.getElementById('formFiltros'));
    
    // Mostrar loading
    showLoading();
    
    // Buscar dados
    fetch("{{ route('relatorios.dashboard-kpis.dados') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        atualizarKPIs(data.kpis);
        atualizarResumoFinanceiro(data.resumo);
        atualizarGraficoFluxoCaixa(data.fluxo_caixa);
        atualizarGraficoRecebimentos(data.recebimentos);
        atualizarTopPropriedades(data.top_propriedades);
        atualizarAlertas(data.alertas);
        hideLoading();
    })
    .catch(error => {
        console.error('Erro ao carregar dashboard:', error);
        hideLoading();
        alert('Erro ao carregar dados do dashboard');
    });
}

function atualizarKPIs(kpis) {
    $('#kpi-taxa-ocupacao').text(kpis.taxa_ocupacao + '%');
    $('#kpi-taxa-ocupacao-variacao').text(formatarVariacao(kpis.taxa_ocupacao_variacao));
    
    $('#kpi-inadimplencia').text(kpis.taxa_inadimplencia + '%');
    $('#kpi-inadimplencia-variacao').text(formatarVariacao(kpis.taxa_inadimplencia_variacao))
        .removeClass('text-success text-danger')
        .addClass(kpis.taxa_inadimplencia_variacao <= 0 ? 'text-success' : 'text-danger');
    
    $('#kpi-receita').text('R$ ' + formatarMoeda(kpis.receita_mensal));
    $('#kpi-receita-variacao').text(formatarVariacao(kpis.receita_variacao));
    
    $('#kpi-roi').text(kpis.roi_medio + '%');
    $('#kpi-roi-variacao').text(formatarVariacao(kpis.roi_variacao));
}

function atualizarResumoFinanceiro(resumo) {
    $('#total-recebido').text('R$ ' + formatarMoeda(resumo.total_recebido));
    $('#total-pago').text('R$ ' + formatarMoeda(resumo.total_pago));
    $('#saldo-periodo').text('R$ ' + formatarMoeda(resumo.saldo_periodo));
    $('#total-pendente').text('R$ ' + formatarMoeda(resumo.total_pendente));
}

function atualizarGraficoFluxoCaixa(dados) {
    const options = {
        series: [
            {
                name: 'Entradas',
                data: dados.entradas
            },
            {
                name: 'Saídas',
                data: dados.saidas
            },
            {
                name: 'Saldo',
                data: dados.saldo
            }
        ],
        chart: {
            height: 350,
            type: 'line',
            toolbar: {
                show: false
            }
        },
        colors: ['#28a745', '#dc3545', '#0d6efd'],
        dataLabels: {
            enabled: false
        },
        stroke: {
            curve: 'smooth',
            width: 3
        },
        xaxis: {
            categories: dados.meses
        },
        yaxis: {
            labels: {
                formatter: function(value) {
                    return 'R$ ' + formatarMoeda(value);
                }
            }
        },
        legend: {
            position: 'top'
        },
        tooltip: {
            y: {
                formatter: function(value) {
                    return 'R$ ' + formatarMoeda(value);
                }
            }
        }
    };

    if (chartFluxoCaixa) {
        chartFluxoCaixa.destroy();
    }
    chartFluxoCaixa = new ApexCharts(document.querySelector("#chartFluxoCaixa"), options);
    chartFluxoCaixa.render();
}

function atualizarGraficoRecebimentos(dados) {
    $('#status-recebido').text('R$ ' + formatarMoeda(dados.recebido));
    $('#status-a-vencer').text('R$ ' + formatarMoeda(dados.a_vencer));
    $('#status-vencido').text('R$ ' + formatarMoeda(dados.vencido));
    $('#status-inadimplente').text('R$ ' + formatarMoeda(dados.inadimplente));

    const options = {
        series: [dados.recebido, dados.a_vencer, dados.vencido, dados.inadimplente],
        chart: {
            type: 'donut',
            height: 250
        },
        colors: ['#28a745', '#17a2b8', '#ffc107', '#dc3545'],
        labels: ['Recebido', 'A Vencer', 'Vencido', 'Inadimplente'],
        legend: {
            show: false
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            formatter: function (w) {
                                const total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                return 'R$ ' + formatarMoeda(total);
                            }
                        }
                    }
                }
            }
        },
        tooltip: {
            y: {
                formatter: function(value) {
                    return 'R$ ' + formatarMoeda(value);
                }
            }
        }
    };

    if (chartRecebimentos) {
        chartRecebimentos.destroy();
    }
    chartRecebimentos = new ApexCharts(document.querySelector("#chartRecebimentos"), options);
    chartRecebimentos.render();
}

function atualizarTopPropriedades(propriedades) {
    let html = '';
    propriedades.forEach((prop, index) => {
        html += `
            <div class="d-flex align-items-center mb-3">
                <div class="avatar flex-shrink-0 me-3">
                    <span class="avatar-initial rounded bg-label-primary">${index + 1}</span>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-0">${prop.nome}</h6>
                    <small class="text-muted">${prop.endereco}</small>
                </div>
                <div class="text-end">
                    <h6 class="mb-0 text-success">R$ ${formatarMoeda(prop.lucro)}</h6>
                    <small class="text-muted">ROI: ${prop.roi}%</small>
                </div>
            </div>
        `;
    });
    $('#listaTopPropriedades').html(html);
}

function atualizarAlertas(alertas) {
    let html = '';
    if (alertas.length === 0) {
        html = '<div class="alert alert-success mb-0"><i class="ti ti-check me-2"></i>Nenhuma pendência no momento</div>';
    } else {
        alertas.forEach(alerta => {
            const iconMap = {
                'danger': 'alert-circle',
                'warning': 'alert-triangle',
                'info': 'info-circle'
            };
            html += `
                <div class="alert alert-${alerta.tipo} d-flex align-items-center mb-2">
                    <i class="ti ti-${iconMap[alerta.tipo]} me-2"></i>
                    <div class="flex-grow-1">${alerta.mensagem}</div>
                    ${alerta.link ? `<a href="${alerta.link}" class="btn btn-sm btn-${alerta.tipo}">Ver</a>` : ''}
                </div>
            `;
        });
    }
    $('#listaAlertas').html(html);
}

function formatarVariacao(valor) {
    const sinal = valor >= 0 ? '+' : '';
    return sinal + valor.toFixed(1) + '%';
}

function formatarMoeda(valor) {
    return parseFloat(valor).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function showLoading() {
    // Implementar loading overlay se necessário
}

function hideLoading() {
    // Remover loading overlay
}
</script>
@endsection
