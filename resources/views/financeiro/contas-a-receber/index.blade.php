@extends('layouts.layoutMaster')

@section('title', 'Contas a Receber')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
/* Animação suave para expanded rows */
.expanded-row {
    transition: all 0.3s ease-in-out;
    opacity: 0;
    max-height: 0;
    overflow: hidden;
}

.expanded-row.show {
    opacity: 1;
    max-height: 2000px;
}

.expanded-content {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Destaque elegante para conta atual */
.conta-atual-row {
    position: relative;
    background: linear-gradient(90deg, rgba(105, 108, 255, 0.08) 0%, rgba(105, 108, 255, 0.02) 100%) !important;
    border-left: 3px solid #696cff !important;
    box-shadow: 0 2px 8px rgba(105, 108, 255, 0.15);
}

.conta-atual-warning {
    position: relative;
    background: linear-gradient(90deg, rgba(255, 171, 0, 0.08) 0%, rgba(255, 171, 0, 0.02) 100%) !important;
    border-left: 3px solid #ffab00 !important;
    box-shadow: 0 2px 8px rgba(255, 171, 0, 0.15);
}

/* Botões de ação sem borda */
.btn-icon-action {
    border: none !important;
    background: transparent !important;
    padding: 0.25rem !important;
}

.btn-icon-action:hover {
    background: rgba(105, 108, 255, 0.1) !important;
}

.badge-atual {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

/* Animação do ícone de toggle */
.toggle-icon {
    transition: transform 0.3s ease;
}

.toggle-icon.rotated {
    transform: rotate(180deg);
}

/* Forçar coluna de descrição a quebrar linha */
table th:nth-child(2),
table td:nth-child(2) {
    width: 300px !important;
    max-width: 300px !important;
    min-width: 200px !important;
    word-wrap: break-word !important;
    word-break: break-word !important;
    white-space: normal !important;
    overflow-wrap: break-word !important;
}
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Cards de Estatísticas -->
            <div class="row g-4 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['total'] ?? 0 }}</h4>
                                    </div>
                                    <span>Total de Contas</span>
                                </div>
                                <span class="badge bg-label-primary rounded p-2">
                                    <i class="ti ti-file-invoice ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Pendentes</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['pendentes'] ?? 0 }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-warning rounded p-2">
                                    <i class="ti ti-clock ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Recebidas</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['recebidas'] ?? 0 }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-success rounded p-2">
                                    <i class="ti ti-check ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Vencidas</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['vencidas'] ?? 0 }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-danger rounded p-2">
                                    <i class="ti ti-alert-triangle ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Valores Totais -->
            <div class="row g-4 mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Total a Receber (Pendente)</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_total_pendente'] ?? 0, 2, ',', '.') }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-warning rounded p-2">
                                    <i class="ti ti-cash ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Total Recebido</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_total_recebido'] ?? 0, 2, ',', '.') }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-success rounded p-2">
                                    <i class="ti ti-cash-off ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros e Ações -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Filtros de Busca</h5>
                    <div>
                        <a href="{{ route('financeiro.relatorios.contas-receber') }}" class="btn btn-info me-2">
                            <i class="ti ti-file-chart me-1"></i>
                            Relatórios
                        </a>
                        @pode('financeiro.contas-receber.criar')
                            <a href="{{ route('financeiro.contas-a-receber.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>
                                Nova Conta a Receber
                            </a>
                        @endpode
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('financeiro.contas-a-receber.index') }}" id="formFiltros">
                        <div class="row g-3">
                            <!-- Filtro de Mês/Ano -->
                            <div class="col-md-3">
                                <label class="form-label">
                                    <i class="ti ti-calendar me-1"></i>
                                    Mês/Ano
                                </label>
                                <input type="month" 
                                    name="mes_filtro" 
                                    class="form-control" 
                                    value="{{ $mesFiltro ?? now()->format('Y-m') }}"
                                    onchange="document.getElementById('formFiltros').submit()">
                                <small class="text-muted">
                                    <a href="{{ route('financeiro.contas-a-receber.index', ['mes_filtro' => 'todos'] + request()->except('mes_filtro')) }}" class="text-decoration-none">
                                        Ver todos os meses
                                    </a>
                                </small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                    <option value="pago" {{ request('status') == 'pago' ? 'selected' : '' }}>Recebido</option>
                                    <option value="vencido" {{ request('status') == 'vencido' ? 'selected' : '' }}>Vencido</option>
                                    <option value="parcelado" {{ request('status') == 'parcelado' ? 'selected' : '' }}>Parcelado</option>
                                    <option value="cancelado" {{ request('status') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Cliente</label>
                                <select name="id_clientes" class="form-select select2">
                                    <option value="">Todos</option>
                                    @foreach($clientes as $cliente)
                                        <option value="{{ $cliente->id_clientes }}" 
                                            {{ request('id_clientes') == $cliente->id_clientes ? 'selected' : '' }}>
                                            {{ $cliente->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Vencimento Início</label>
                                <input type="date" name="data_vencimento_inicio" class="form-control" 
                                    value="{{ request('data_vencimento_inicio') }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Vencimento Fim</label>
                                <input type="date" name="data_vencimento_fim" class="form-control" 
                                    value="{{ request('data_vencimento_fim') }}">
                            </div>

                            <div class="col-md-9">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="busca" class="form-control" 
                                    placeholder="Descrição, Documento ou Boleto..." 
                                    value="{{ request('busca') }}">
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="ti ti-search me-1"></i>
                                    Filtrar
                                </button>
                                <a href="{{ route('financeiro.contas-a-receber.index') }}" class="btn btn-outline-secondary">
                                    <i class="ti ti-x me-1"></i>
                                    Limpar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Contas -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Contas a Receber</h5>
                    @pode('financeiro.contas-receber.excluir')
                        <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('financeiro.contas-a-receber.excluir-multiplos') }}" style="display: none;">
                            <i class="ti ti-trash me-1"></i>
                            Excluir Selecionados (<span id="countSelecionados">0</span>)
                        </button>
                    @endpode
                </div>
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    @pode('financeiro.contas-receber.excluir')
                                        <input type="checkbox" class="form-check-input" id="checkAll">
                                    @endpode
                                </th>
                                <th style="width: 300px; min-width: 250px;">Descrição</th>
                                <th>Cliente</th>
                                <th>Valor Total</th>
                                <th>Valor Recebido</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th style="width: 80px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contas as $conta)
                                <tr>
                                    <td>
                                        @pode('financeiro.contas-receber.excluir')
                                            <input type="checkbox" class="form-check-input check-item" value="{{ $conta->id_contas }}">
                                        @endpode
                                    </td>
                                    <td>
                                        <strong>{{ $conta->descricao }}</strong>
                                        <small class="text-muted ms-1">#{{ $conta->id_contas }}</small>
                                        <div class="mt-1">
                                            @if($conta->documento)
                                                <small class="text-muted me-2">Doc: {{ $conta->documento }}</small>
                                            @endif
                                            @if($conta->isParcelada())
                                                <span class="badge bg-label-info me-1">
                                                    <i class="ti ti-credit-card"></i> {{ $conta->numero_parcela }}/{{ $conta->total_parcelas }}
                                                </span>
                                            @endif
                                            @if($conta->is_recorrente)
                                                <span class="badge bg-label-warning">
                                                    <i class="ti ti-repeat"></i> {{ ucfirst($conta->tipo_recorrencia) }}
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $conta->cliente->nome ?? '-' }}</td>
                                    <td>
                                        <strong>R$ {{ number_format($conta->valor_total, 2, ',', '.') }}</strong>
                                    </td>
                                    <td>
                                        @if($conta->valor_pago > 0)
                                            <strong class="{{ $conta->valor_pago >= $conta->valor_total ? 'text-success' : 'text-primary' }}">
                                                R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}
                                            </strong>
                                            @if($conta->valor_pago < $conta->valor_total)
                                                <br>
                                                <small class="text-muted">
                                                    Restante: R$ {{ number_format($conta->valor_total - $conta->valor_pago, 2, ',', '.') }}
                                                </small>
                                            @endif
                                        @else
                                            <span class="text-muted">R$ 0,00</span>
                                        @endif
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($conta->data_vencimento)->format('d/m/Y') }}
                                        @if($conta->isVencida())
                                            <br><small class="text-danger"><i class="ti ti-alert-circle"></i> Vencida</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge {{ $conta->getStatusBadgeClass() }}">
                                            {{ $conta->status_label }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-2">
                                            @if($conta->isParcelada())
                                                <button type="button" 
                                                    class="btn btn-sm btn-icon btn-outline-primary" 
                                                    onclick="toggleParcelas({{ $conta->id_contas }}, '{{ $conta->id_parcelamento }}')"
                                                    title="Ver Parcelas"
                                                    id="btn-parcelas-{{ $conta->id_contas }}">
                                                    <i class="ti ti-list" id="icon-parcela-{{ $conta->id_contas }}"></i>
                                                </button>
                                            @endif

                                            @if($conta->is_recorrente)
                                                <button type="button" 
                                                    class="btn btn-sm btn-icon btn-outline-info" 
                                                    onclick="toggleRecorrencias({{ $conta->id_contas }}, '{{ $conta->id_recorrencia }}')"
                                                    title="Ver Recorrências"
                                                    id="btn-recorrencias-{{ $conta->id_contas }}">
                                                    <i class="ti ti-repeat" id="icon-recorrencia-{{ $conta->id_contas }}"></i>
                                                </button>
                                            @endif

                                            <div class="dropdown">
                                                <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                    <i class="ti ti-dots-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    @pode('financeiro.contas-receber.baixa')
                                                        @if($conta->status !== 'pago')
                                                            <a class="dropdown-item text-success" href="javascript:void(0)" onclick="abrirModalBaixa({{ $conta->id_contas }}, '{{ $conta->descricao }}', {{ $conta->valor_total }}, {{ $conta->valor_pago ?? 0 }})">
                                                                <i class="ti ti-cash me-2"></i>Dar Baixa / Receber
                                                            </a>
                                                        @endif
                                                    @endpode
                                                    @if($conta->status !== 'pago')
                                                        <a class="dropdown-item text-primary" href="javascript:void(0)" onclick="abrirModalBoleto({{ $conta->id_contas }}, '{{ $conta->descricao }}', {{ $conta->valor_total }})">
                                                            <i class="ti ti-file-invoice me-2"></i>Gerar Boleto
                                                        </a>
                                                    @endif
                                                    @if($conta->valor_pago > 0)
                                                        <a class="dropdown-item" href="javascript:void(0)" onclick="verHistoricoRecebimentos({{ $conta->id_contas }}, '{{ $conta->descricao }}')">
                                                            <i class="ti ti-receipt me-2"></i>Ver Histórico
                                                        </a>
                                                        <a class="dropdown-item" target="_blank" href="{{ route('financeiro.contas-a-receber.recibo', $conta->id_contas) }}">
                                                            <i class="ti ti-receipt-2 me-2"></i>Recibo
                                                        </a>
                                                    @endif
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="verLogAtividadesConta({{ $conta->id_contas }}, '{{ $conta->descricao }}')">
                                                        <i class="ti ti-activity me-2"></i>Log de Atividades
                                                    </a>
                                                    @pode('financeiro.contas-receber.editar')
                                                        <a class="dropdown-item" href="{{ route('financeiro.contas-a-receber.edit', $conta->id_contas) }}">
                                                            <i class="ti ti-edit me-2"></i>Editar
                                                        </a>
                                                    @endpode
                                                    @pode('financeiro.contas-receber.excluir')
                                                        <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="excluirConta({{ $conta->id_contas }}, '{{ $conta->descricao }}')">
                                                            <i class="ti ti-trash me-2"></i>Excluir
                                                        </a>
                                                    @endpode
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Linha expansível para parcelas -->
                                @if($conta->isParcelada())
                                    <tr id="parcelas-row-{{ $conta->id_contas }}" class="expanded-row" style="display: none;">
                                        <td colspan="8" class="p-0">
                                            <div class="bg-light p-3 expanded-content">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">
                                                        <i class="ti ti-credit-card me-1"></i>
                                                        Parcelas do Parcelamento
                                                    </h6>
                                                </div>
                                                <div id="parcelas-content-{{ $conta->id_contas }}">
                                                    <div class="text-center py-3">
                                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                            <span class="visually-hidden">Carregando...</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                <!-- Linha expansível para recorrências -->
                                @if($conta->is_recorrente)
                                    <tr id="recorrencias-row-{{ $conta->id_contas }}" class="expanded-row" style="display: none;">
                                        <td colspan="8" class="p-0">
                                            <div class="bg-light p-3 expanded-content">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0">
                                                        <i class="ti ti-repeat me-1"></i>
                                                        Contas Recorrentes
                                                    </h6>
                                                </div>
                                                <div id="recorrencias-content-{{ $conta->id_contas }}">
                                                    <div class="text-center py-3">
                                                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                            <span class="visually-hidden">Carregando...</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="ti ti-file-invoice ti-lg text-muted"></i>
                                        <p class="mt-2 mb-0">Nenhuma conta a receber encontrada</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                {{-- Paginação customizada --}}
                <x-pagination-info :paginator="$contas" />
            </div>
        </div>
    </div>
</div>

<!-- Modal para histórico de recebimentos -->
<div class="modal fade" id="modalHistoricoRecebimentos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Histórico de Recebimentos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6 id="historico_conta_descricao" class="mb-3"></h6>
                <div id="historico_content">
                    <div class="text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para gerar boleto -->
<div class="modal fade" id="modalBoleto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-file-invoice me-2"></i>Gerar Boleto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="boleto_id_conta">
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Conta:</label>
                    <p id="boleto_descricao_conta" class="mb-0"></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Valor:</label>
                    <p id="boleto_valor_conta" class="mb-0 text-primary"></p>
                </div>
                
                <div class="mb-3">
                    <label for="boleto_banco" class="form-label">Banco para Gerar Boleto <span class="text-danger">*</span></label>
                    <select class="form-select" id="boleto_banco" name="boleto_banco">
                        <option value="">Selecione o banco...</option>
                    </select>
                    <small class="text-muted">Somente bancos configurados para gerar boleto aparecem aqui</small>
                </div>
                
                <!-- Boletos já gerados para esta conta -->
                <div id="boletos_existentes" style="display: none;">
                    <hr>
                    <h6 class="mb-2"><i class="ti ti-file-invoice me-1"></i>Boletos Gerados</h6>
                    <div id="boletos_lista"></div>
                </div>
                
                <div id="boleto_msg"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGerarBoleto" onclick="gerarBoleto()">
                    <i class="ti ti-file-invoice me-1"></i>Gerar Boleto
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
<style>
    /* Fix para dropdown não ser cortado */
    .table-responsive {
        overflow-x: auto;
        overflow-y: visible !important;
    }
    
    /* Estilo para linhas expansíveis */
    .expanded-row {
        transition: all 0.3s ease;
    }
    
    .expanded-row.show {
        opacity: 1;
    }
    
    .expanded-content {
        animation: slideDown 0.3s ease;
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    /* Badge da parcela atual */
    .conta-atual-row {
        background-color: rgba(105, 108, 255, 0.08);
    }
    
    .badge-atual {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/money-helpers.js')}}"></script>
<script src="{{asset('assets/js/financeiro/contas-a-receber.js')}}"></script>
<script>
$(document).ready(function() {
    // Configurar CSRF token para todas as requisições AJAX
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Configuração inicial para o módulo
    window.initContasAReceber({
        csrfToken: $('meta[name="csrf-token"]').attr('content'),
        formasPagamento: @json($formasPagamento ?? []),
        bancos: @json($bancos ?? [])
    });

    // Exibir mensagens de sessão
    @if(session('success'))
        mostrarSucesso('{{ session('success') }}');
    @endif

    @if(session('error'))
        mostrarErro('{{ session('error') }}');
    @endif
});

// Funções para geração de boletos
function abrirModalBoleto(idConta, descricao, valor) {
    $('#boleto_id_conta').val(idConta);
    $('#boleto_descricao_conta').text(descricao);
    $('#boleto_valor_conta').text(formatarMoeda(valor));
    $('#boleto_banco').val('');
    $('#boleto_msg').html('');
    
    // Carregar bancos disponíveis
    carregarBancosDisponiveis();
    
    // Carregar boletos existentes
    carregarBoletosExistentes(idConta);
    
    $('#modalBoleto').modal('show');
}

function carregarBancosDisponiveis() {
    $.ajax({
        url: '{{ route("financeiro.boletos.bancos-disponiveis") }}',
        method: 'GET',
        success: function(response) {
            if (response.success && response.bancos) {
                let options = '<option value="">Selecione o banco...</option>';
                response.bancos.forEach(function(banco) {
                    const nomeBoleto = banco.boleto_config?.banco_boleto?.nome || '';
                    options += `<option value="${banco.id_bancos}">${banco.nome_banco} ${nomeBoleto ? '(' + nomeBoleto + ')' : ''}</option>`;
                });
                $('#boleto_banco').html(options);
                
                if (response.bancos.length === 0) {
                    $('#boleto_msg').html(`
                        <div class="alert alert-warning">
                            <i class="ti ti-alert-circle me-1"></i>
                            Nenhum banco configurado para gerar boletos. 
                            <a href="{{ route('financeiro.bancos.index') }}">Configure um banco</a> com a opção "Gera Boleto" ativa.
                        </div>
                    `);
                }
            } else {
                $('#boleto_msg').html(`
                    <div class="alert alert-danger">
                        <i class="ti ti-alert-circle me-1"></i>
                        ${response.message || 'Erro ao carregar bancos disponíveis.'}
                    </div>
                `);
            }
        },
        error: function(xhr) {
            const mensagem = xhr.responseJSON?.message || 'Erro ao carregar bancos disponíveis.';
            $('#boleto_msg').html(`
                <div class="alert alert-danger">
                    <i class="ti ti-alert-circle me-1"></i>
                    ${mensagem}
                </div>
            `);
        }
    });
}

function carregarBoletosExistentes(idConta) {
    $.ajax({
        url: `/financeiro/boletos/conta/${idConta}`,
        method: 'GET',
        success: function(response) {
            if (response.success && response.boletos && response.boletos.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Banco</th><th>Status</th><th>Vencimento</th><th>Valor</th><th>Ações</th></tr></thead><tbody>';
                
                response.boletos.forEach(function(boleto) {
                    const statusClass = getStatusBoletoClass(boleto.status);
                    const dataVenc = new Date(boleto.data_vencimento).toLocaleDateString('pt-BR');
                    const valor = parseFloat(boleto.valor_nominal).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
                    
                    html += `<tr>
                        <td>${boleto.banco?.nome_banco || '-'}</td>
                        <td><span class="badge ${statusClass}">${boleto.status}</span></td>
                        <td>${dataVenc}</td>
                        <td>${valor}</td>
                        <td>
                            <a href="/financeiro/boletos/${boleto.id_boleto}/pdf" target="_blank" class="btn btn-sm btn-icon btn-label-primary" title="Ver PDF">
                                <i class="ti ti-file-invoice"></i>
                            </a>
                        </td>
                    </tr>`;
                });
                
                html += '</tbody></table></div>';
                $('#boletos_lista').html(html);
                $('#boletos_existentes').show();
            } else {
                $('#boletos_existentes').hide();
            }
        }
    });
}

function getStatusBoletoClass(status) {
    switch(status) {
        case 'pago': return 'bg-success';
        case 'gerado':
        case 'pendente': return 'bg-warning';
        case 'vencido': return 'bg-danger';
        case 'cancelado': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}

function gerarBoleto() {
    const idConta = $('#boleto_id_conta').val();
    const idBanco = $('#boleto_banco').val();
    
    if (!idBanco) {
        $('#boleto_msg').html(`
            <div class="alert alert-danger">
                <i class="ti ti-alert-circle me-1"></i>
                Selecione um banco para gerar o boleto.
            </div>
        `);
        return;
    }
    
    const btn = $('#btnGerarBoleto');
    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Gerando...');
    $('#boleto_msg').html('');
    
    $.ajax({
        url: '{{ route("financeiro.boletos.gerar") }}',
        method: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            id_conta_receber: idConta,
            id_bancos: idBanco
        },
        success: function(response) {
            if (response.success) {
                $('#boleto_msg').html(`
                    <div class="alert alert-success">
                        <i class="ti ti-check me-1"></i>
                        ${response.message}
                    </div>
                `);
                
                // Recarregar lista de boletos
                carregarBoletosExistentes(idConta);
                
                // Abrir PDF do boleto em nova aba
                if (response.boleto && response.boleto.id_boleto) {
                    window.open(`/financeiro/boletos/${response.boleto.id_boleto}/pdf`, '_blank');
                }
            } else {
                $('#boleto_msg').html(`
                    <div class="alert alert-danger">
                        <i class="ti ti-alert-circle me-1"></i>
                        ${response.message || 'Erro ao gerar boleto.'}
                    </div>
                `);
            }
        },
        error: function(xhr) {
            let errorMsg = 'Erro ao gerar boleto.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            $('#boleto_msg').html(`
                <div class="alert alert-danger">
                    <i class="ti ti-alert-circle me-1"></i>
                    ${errorMsg}
                </div>
            `);
        },
        complete: function() {
            btn.prop('disabled', false).html('<i class="ti ti-file-invoice me-1"></i>Gerar Boleto');
        }
    });
}

function formatarMoeda(valor) {
    return parseFloat(valor).toLocaleString('pt-BR', {style: 'currency', currency: 'BRL'});
}
</script>
@endsection
