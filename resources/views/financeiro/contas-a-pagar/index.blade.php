@extends('layouts.layoutMaster')

@section('title', 'Contas a Pagar')

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

/* Fix para dropdown não ser cortado */
.table-responsive {
    overflow-x: auto;
    overflow-y: visible !important;
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
                                    <span>Pagas</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['pagas'] ?? 0 }}</h4>
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
                                    <span>Total a Pagar (Pendente)</span>
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
                                    <span>Total Pago</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_total_pago'] ?? 0, 2, ',', '.') }}</h4>
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
                        <a href="{{ route('financeiro.relatorios.contas-pagar') }}" class="btn btn-info me-2">
                            <i class="ti ti-file-chart me-1"></i>
                            Relatórios
                        </a>
                        @pode('financeiro.contas-pagar.criar')
                            <a href="{{ route('financeiro.create') }}" class="btn btn-primary">
                                <i class="ti ti-plus me-1"></i>
                                Nova Conta a Pagar
                            </a>
                        @endpode
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('financeiro.index') }}" id="formFiltros">
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
                                    <a href="{{ route('financeiro.index', ['mes_filtro' => 'todos'] + request()->except('mes_filtro')) }}" class="text-decoration-none">
                                        Ver todos os meses
                                    </a>
                                </small>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="pendente" {{ request('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                    <option value="pago" {{ request('status') == 'pago' ? 'selected' : '' }}>Pago</option>
                                    <option value="vencido" {{ request('status') == 'vencido' ? 'selected' : '' }}>Vencido</option>
                                    <option value="parcelado" {{ request('status') == 'parcelado' ? 'selected' : '' }}>Parcelado</option>
                                    <option value="cancelado" {{ request('status') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Fornecedor</label>
                                <select name="id_fornecedores" class="form-select select2">
                                    <option value="">Todos</option>
                                    @foreach($fornecedores as $fornecedor)
                                        <option value="{{ $fornecedor->id_fornecedores }}" 
                                            {{ request('id_fornecedores') == $fornecedor->id_fornecedores ? 'selected' : '' }}>
                                            {{ $fornecedor->nome }}
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
                                <a href="{{ route('financeiro.index') }}" class="btn btn-outline-secondary">
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
                    <h5 class="mb-0">Contas a Pagar</h5>
                    @pode('financeiro.contas-pagar.excluir')
                        <button type="button" class="btn btn-danger btn-sm" id="btnExcluirSelecionados" data-url="{{ route('financeiro.excluir-multiplos') }}" style="display: none;">
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
                                    <input type="checkbox" class="form-check-input" id="checkAll">
                                </th>
                                <th style="width: 300px; min-width: 250px;">Descrição</th>
                                <th>Fornecedor</th>
                                <th>Valor Total</th>
                                <th>Valor Pago</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th style="width: 80px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($contas as $conta)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input check-item" value="{{ $conta->id_contas }}">
                                    </td>
                                    <td>
                                        <strong>{{ $conta->descricao }}</strong>
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
                                    <td>{{ $conta->fornecedor->nome ?? '-' }}</td>
                                    <td>
                                        @if($conta->isParcelada())
                                            <strong>R$ {{ number_format($conta->valor_total * $conta->total_parcelas, 2, ',', '.') }}</strong>
                                            <br>
                                            <small class="text-muted">Parcela: R$ {{ number_format($conta->valor_total, 2, ',', '.') }}</small>
                                        @else
                                            <strong>R$ {{ number_format($conta->valor_total, 2, ',', '.') }}</strong>
                                        @endif
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
                                                    @if($conta->status !== 'pago')
                                                        @pode('financeiro.contas-pagar.baixa')
                                                            <a class="dropdown-item text-success" href="javascript:void(0)" onclick="abrirModalBaixa({{ $conta->id_contas }}, '{{ $conta->descricao }}', {{ $conta->valor_total }}, {{ $conta->valor_pago ?? 0 }})">
                                                                <i class="ti ti-cash me-2"></i>Dar Baixa / Pagar
                                                            </a>
                                                        @endpode
                                                    @endif
                                                    @if($conta->valor_pago > 0)
                                                        <a class="dropdown-item" href="javascript:void(0)" onclick="verHistoricoPagamentos({{ $conta->id_contas }}, '{{ $conta->descricao }}')">
                                                            <i class="ti ti-receipt me-2"></i>Ver Histórico
                                                        </a>
                                                        <a class="dropdown-item" target="_blank" href="{{ route('financeiro.contas-a-pagar.recibo', $conta->id_contas) }}">
                                                            <i class="ti ti-receipt-2 me-2"></i>Recibo
                                                        </a>
                                                    @endif
                                                    <a class="dropdown-item" href="javascript:void(0)" onclick="verLogAtividadesConta({{ $conta->id_contas }}, '{{ $conta->descricao }}')">
                                                        <i class="ti ti-activity me-2"></i>Log de Atividades
                                                    </a>
                                                    @pode('financeiro.contas-pagar.editar')
                                                        <a class="dropdown-item" href="{{ route('financeiro.edit', $conta->id_contas) }}">
                                                            <i class="ti ti-edit me-2"></i>Editar
                                                        </a>
                                                    @endpode
                                                    @pode('financeiro.contas-pagar.excluir')
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
                                        <p class="mt-2 mb-0">Nenhuma conta a pagar encontrada</p>
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
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/money-helpers.js')}}"></script>
<script src="{{asset('assets/js/financeiro/contas-a-pagar.js')}}"></script>
<script>
$(document).ready(function() {
    // Configurar CSRF token para todas as requisições AJAX (pegar sempre da meta tag)
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Configuração inicial para o módulo
    window.initContasAPagar({
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
</script>
@endsection
