@extends('layouts.layoutMaster')

@section('title', 'Relatório de Vendas PDV')

@section('page-style')
<style>
    .stat-card {
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .chart-container {
        position: relative;
        height: 300px;
    }
    @media print {
        .no-print {
            display: none !important;
        }
        .card {
            break-inside: avoid;
        }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Relatório de Vendas PDV</h5>
                        <small class="text-muted">
                            @if(!empty($filters['data_inicio']) && !empty($filters['data_fim']))
                                Período: {{ \Carbon\Carbon::parse($filters['data_inicio'])->format('d/m/Y') }} até {{ \Carbon\Carbon::parse($filters['data_fim'])->format('d/m/Y') }}
                            @else
                                Período: Todo o histórico
                            @endif
                        </small>
                    </div>
                    <div class="d-flex gap-2 no-print">
                        <a href="{{ route('pdv.relatorio-vendas-pdf', request()->query()) }}" class="btn btn-danger" target="_blank">
                            <i class="ti ti-file-type-pdf me-1"></i>
                            Gerar PDF
                        </a>
                        <a href="{{ route('pdv.historico') }}" class="btn btn-secondary">
                            <i class="ti ti-arrow-left me-1"></i>
                            Voltar
                        </a>
                    </div>
                </div>
                <div class="card-body no-print">
                    <form method="GET" class="row g-3 align-items-end">
                        <div class="col-12 col-md-3">
                            <label class="form-label small mb-1">Data Início</label>
                            <input type="date" name="data_inicio" class="form-control" value="{{ $filters['data_inicio'] ?? '' }}">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small mb-1">Data Fim</label>
                            <input type="date" name="data_fim" class="form-control" value="{{ $filters['data_fim'] ?? '' }}">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small mb-1">Forma de Pagamento</label>
                            <select name="forma_pagamento" class="form-select">
                                <option value="">Todas</option>
                                @foreach($formasPagamento as $forma)
                                    <option value="{{ $forma->id_forma_pagamento }}" {{ ($filters['forma_pagamento'] ?? '') == $forma->id_forma_pagamento ? 'selected' : '' }}>
                                        {{ $forma->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="ti ti-search me-1"></i>
                                Filtrar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cards de Estatísticas Principais -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span class="text-muted small">Total de Vendas</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h3 class="mb-0">{{ $stats['total_vendas'] }}</h3>
                                    </div>
                                    <small class="text-success">
                                        <i class="ti ti-receipt"></i> vendas realizadas
                                    </small>
                                </div>
                                <span class="badge bg-label-primary rounded p-2">
                                    <i class="ti ti-receipt ti-md"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span class="text-muted small">Faturamento Total</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h3 class="mb-0 text-success">R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}</h3>
                                    </div>
                                    <small class="text-muted">
                                        <i class="ti ti-trending-up"></i> receita bruta
                                    </small>
                                </div>
                                <span class="badge bg-label-success rounded p-2">
                                    <i class="ti ti-currency-dollar ti-md"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span class="text-muted small">Ticket Médio</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h3 class="mb-0 text-info">R$ {{ number_format($stats['ticket_medio'], 2, ',', '.') }}</h3>
                                    </div>
                                    <small class="text-muted">
                                        <i class="ti ti-chart-bar"></i> por venda
                                    </small>
                                </div>
                                <span class="badge bg-label-info rounded p-2">
                                    <i class="ti ti-chart-dots ti-md"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span class="text-muted small">Total de Itens</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h3 class="mb-0 text-warning">{{ $stats['total_itens'] }}</h3>
                                    </div>
                                    <small class="text-muted">
                                        <i class="ti ti-package"></i> produtos vendidos
                                    </small>
                                </div>
                                <span class="badge bg-label-warning rounded p-2">
                                    <i class="ti ti-packages ti-md"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row g-4 mb-4">
                <!-- Vendas por Dia -->
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Vendas por Dia</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="chartVendasDia"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vendas por Forma de Pagamento -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Por Forma de Pagamento</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="chartFormaPagamento"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabelas de Detalhes -->
            <div class="row g-4 mb-4">
                <!-- Top Produtos -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ti ti-trophy me-2 text-warning"></i>
                                Top 10 Produtos Mais Vendidos
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Produto</th>
                                            <th class="text-center">Qtd</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($topProdutos as $index => $produto)
                                            <tr>
                                                <td>
                                                    @if($index < 3)
                                                        <span class="badge bg-{{ $index == 0 ? 'warning' : ($index == 1 ? 'secondary' : 'danger') }}">
                                                            {{ $index + 1 }}º
                                                        </span>
                                                    @else
                                                        {{ $index + 1 }}º
                                                    @endif
                                                </td>
                                                <td>{{ $produto->nome_produto }}</td>
                                                <td class="text-center">{{ intval($produto->total_quantidade) }}</td>
                                                <td class="text-end text-success">R$ {{ number_format($produto->total_valor, 2, ',', '.') }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">
                                                    Nenhum produto vendido
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vendas por Forma de Pagamento (Tabela) -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ti ti-credit-card me-2 text-info"></i>
                                Vendas por Forma de Pagamento
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Forma de Pagamento</th>
                                            <th class="text-center">Vendas</th>
                                            <th class="text-end">Total</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($vendasPorFormaPagamento as $item)
                                            <tr>
                                                <td>{{ $item->forma_pagamento }}</td>
                                                <td class="text-center">{{ $item->total_vendas }}</td>
                                                <td class="text-end text-success">R$ {{ number_format($item->total_valor, 2, ',', '.') }}</td>
                                                <td class="text-end">
                                                    <span class="badge bg-label-primary">
                                                        {{ $stats['valor_total'] > 0 ? number_format(($item->total_valor / $stats['valor_total']) * 100, 1) : 0 }}%
                                                    </span>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">
                                                    Nenhuma venda registrada
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if($vendasPorFormaPagamento->count() > 0)
                                    <tfoot class="table-light">
                                        <tr>
                                            <th>Total</th>
                                            <th class="text-center">{{ $stats['total_vendas'] }}</th>
                                            <th class="text-end text-success">R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}</th>
                                            <th class="text-end">100%</th>
                                        </tr>
                                    </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vendas por Operador -->
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="ti ti-users me-2 text-primary"></i>
                                Vendas por Operador
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Operador</th>
                                            <th class="text-center">Total Vendas</th>
                                            <th class="text-end">Valor Total</th>
                                            <th class="text-end">Ticket Médio</th>
                                            <th class="text-end">% do Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($vendasPorOperador as $operador)
                                            <tr>
                                                <td>
                                                    <i class="ti ti-user me-2 text-muted"></i>
                                                    {{ $operador->nome_operador ?? 'Não identificado' }}
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-label-primary">{{ $operador->total_vendas }}</span>
                                                </td>
                                                <td class="text-end text-success fw-bold">
                                                    R$ {{ number_format($operador->total_valor, 2, ',', '.') }}
                                                </td>
                                                <td class="text-end">
                                                    R$ {{ number_format($operador->total_vendas > 0 ? $operador->total_valor / $operador->total_vendas : 0, 2, ',', '.') }}
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                                        <div class="progress" style="width: 60px; height: 6px;">
                                                            <div class="progress-bar bg-primary" style="width: {{ $stats['valor_total'] > 0 ? ($operador->total_valor / $stats['valor_total']) * 100 : 0 }}%"></div>
                                                        </div>
                                                        <span class="small">{{ $stats['valor_total'] > 0 ? number_format(($operador->total_valor / $stats['valor_total']) * 100, 1) : 0 }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">
                                                    Nenhuma venda registrada
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Vendas Canceladas (se houver) -->
            @if($stats['vendas_canceladas'] > 0)
            <div class="row g-4 mb-4">
                <div class="col-12">
                    <div class="card border-danger">
                        <div class="card-header bg-label-danger">
                            <h5 class="card-title mb-0 text-danger">
                                <i class="ti ti-alert-circle me-2"></i>
                                Vendas Canceladas no Período
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-label-danger rounded text-center">
                                        <h4 class="mb-1 text-danger">{{ $stats['vendas_canceladas'] }}</h4>
                                        <small>Vendas Canceladas</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-label-warning rounded text-center">
                                        <h4 class="mb-1 text-warning">R$ {{ number_format($stats['valor_cancelado'], 2, ',', '.') }}</h4>
                                        <small>Valor Cancelado</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-label-secondary rounded text-center">
                                        <h4 class="mb-1">{{ $stats['total_vendas'] > 0 ? number_format(($stats['vendas_canceladas'] / ($stats['total_vendas'] + $stats['vendas_canceladas'])) * 100, 1) : 0 }}%</h4>
                                        <small>Taxa de Cancelamento</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    // Dados para os gráficos
    const vendasPorDia = @json($vendasPorDia);
    const vendasPorFormaPgto = @json($vendasPorFormaPagamento);

    // Gráfico de Vendas por Dia
    if (vendasPorDia && vendasPorDia.length > 0) {
        const ctxDia = document.getElementById('chartVendasDia').getContext('2d');
        new Chart(ctxDia, {
            type: 'bar',
            data: {
                labels: vendasPorDia.map(item => {
                    const d = new Date(item.data + 'T12:00:00');
                    return d.toLocaleDateString('pt-BR', {day: '2-digit', month: '2-digit'});
                }),
                datasets: [{
                    label: 'Faturamento (R$)',
                    data: vendasPorDia.map(item => item.total_valor),
                    backgroundColor: 'rgba(115, 103, 240, 0.5)',
                    borderColor: 'rgba(115, 103, 240, 1)',
                    borderWidth: 2,
                    borderRadius: 5,
                    yAxisID: 'y'
                }, {
                    label: 'Qtd. Vendas',
                    data: vendasPorDia.map(item => item.total_vendas),
                    type: 'line',
                    borderColor: 'rgba(40, 199, 111, 1)',
                    backgroundColor: 'rgba(40, 199, 111, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return 'Faturamento: R$ ' + context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2});
                                }
                                return 'Vendas: ' + context.raw;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    } else {
        $('#chartVendasDia').parent().html('<div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="ti ti-chart-bar ti-lg me-2"></i>Sem dados para exibir</div>');
    }

    // Gráfico por Forma de Pagamento (Doughnut)
    if (vendasPorFormaPgto && vendasPorFormaPgto.length > 0) {
        const ctxForma = document.getElementById('chartFormaPagamento').getContext('2d');
        const colors = [
            'rgba(115, 103, 240, 0.8)',
            'rgba(40, 199, 111, 0.8)',
            'rgba(255, 159, 67, 0.8)',
            'rgba(234, 84, 85, 0.8)',
            'rgba(0, 207, 232, 0.8)',
            'rgba(168, 170, 206, 0.8)'
        ];

        new Chart(ctxForma, {
            type: 'doughnut',
            data: {
                labels: vendasPorFormaPgto.map(item => item.forma_pagamento),
                datasets: [{
                    data: vendasPorFormaPgto.map(item => item.total_valor),
                    backgroundColor: colors.slice(0, vendasPorFormaPgto.length),
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return context.label + ': R$ ' + context.raw.toLocaleString('pt-BR', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });
    } else {
        $('#chartFormaPagamento').parent().html('<div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="ti ti-chart-pie ti-lg me-2"></i>Sem dados para exibir</div>');
    }
});
</script>
@endsection
