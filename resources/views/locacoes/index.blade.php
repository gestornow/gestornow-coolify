@extends('layouts.layoutMaster')

@section('title', 'Gerenciamento de Locações')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('content')
@php
    $podeCriarLocacao = \Perm::pode(auth()->user(), 'locacoes.criar');
    $podeEditarLocacao = \Perm::pode(auth()->user(), 'locacoes.editar');
    $podeAlterarStatusLocacao = \Perm::pode(auth()->user(), 'locacoes.alterar-status');
    $podeRetornarLocacaoPerm = \Perm::pode(auth()->user(), 'locacoes.retornar');
    $podeRenovarLocacao = \Perm::pode(auth()->user(), 'locacoes.renovar');
    $podeContratoPdfLocacao = \Perm::pode(auth()->user(), 'locacoes.contrato-pdf');
@endphp
<style>
    .btn-doc-action {
        width: 40px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    @media (max-width: 767.98px) {
        .locacoes-filtros-header {
            gap: .6rem;
            align-items: flex-start !important;
        }

        .locacoes-filtros-header > * {
            width: 100%;
        }

        .locacoes-filtros-header .btn {
            width: 100%;
        }
    }

    html.dark-style .table-responsive {
        scrollbar-color: #5a6288 #2f3349;
    }
</style>
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="row">
                <!-- Cards de Estatísticas -->
                <div class="col-12">
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-3">
                            <div class="card">
                                <div class="card-body">
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
                                            <span>Em Andamento</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['em_andamento'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-info rounded p-2">
                                            <i class="ti ti-truck-delivery ti-sm"></i>
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
                                            <span>Atrasadas</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['atrasadas'] ?? 0 }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-danger rounded p-2">
                                            <i class="ti ti-alert-triangle ti-sm"></i>
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
                                            <span>Valor Total</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_total'] ?? 0, 2, ',', '.') }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-success rounded p-2">
                                            <i class="ti ti-currency-real ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="col-lg-12">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center locacoes-filtros-header">
                            <h5 class="mb-0">Filtros de Busca</h5>
                            @if($podeCriarLocacao)
                                <a href="{{ route('locacoes.create') }}" class="btn btn-primary">
                                    <i class="ti ti-plus me-1"></i>
                                    Nova Locação
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label small mb-1">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="">Todos</option>
                                        @foreach(\App\Domain\Locacao\Models\Locacao::statusList() as $value => $label)
                                            <option value="{{ $value }}" {{ (($filters['status'] ?? '') == $value) ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-3">
                                    <label class="form-label small mb-1">Período</label>
                                    <select name="periodo" class="form-select">
                                        <option value="">Todos</option>
                                        <option value="hoje" {{ (($filters['periodo'] ?? '') == 'hoje') ? 'selected' : '' }}>Hoje</option>
                                        <option value="semana" {{ (($filters['periodo'] ?? '') == 'semana') ? 'selected' : '' }}>Esta Semana</option>
                                        <option value="mes" {{ (($filters['periodo'] ?? '') == 'mes') ? 'selected' : '' }}>Este Mês</option>
                                        <option value="atrasadas" {{ (($filters['periodo'] ?? '') == 'atrasadas') ? 'selected' : '' }}>Atrasadas</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label small mb-1">Buscar</label>
                                    <input type="text" name="busca" class="form-control" placeholder="Nº Contrato, cliente ou produto" value="{{ $filters['busca'] ?? '' }}">
                                </div>
                                <div class="col-12 col-md-2">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="ti ti-search me-1"></i>
                                        Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Locações -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Locações</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive" style="overflow: visible;">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th class="text-center" style="width: 48px;"></th>
                                            <th class="text-center" style="width: 72px;">Editar</th>
                                            <th>Contrato</th>
                                            <th>Cliente</th>
                                            <th>Período</th>
                                            <th>Produtos</th>
                                            <th>Valor Total</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($locacoes as $locacao)
                                            <tr class="{{ $locacao->estaAtrasada() ? 'table-danger bg-opacity-10' : '' }}">
                                                <td class="text-center align-middle">
                                                    <button class="btn btn-sm btn-icon btn-outline-secondary btn-expandir-locacao" type="button" data-locacao="{{ $locacao->id_locacao }}" data-bs-toggle="collapse" data-bs-target="#detalhes-locacao-{{ $locacao->id_locacao }}" aria-expanded="false" aria-controls="detalhes-locacao-{{ $locacao->id_locacao }}">
                                                        <i class="ti ti-chevron-down"></i>
                                                    </button>
                                                </td>
                                                <td class="text-center align-middle">
                                                    @if($podeEditarLocacao && !in_array($locacao->status, ['encerrado', 'cancelado']))
                                                        <a class="btn btn-sm btn-icon btn-outline-primary btn-editar-inline" data-locacao="{{ $locacao->id_locacao }}" href="{{ route('locacoes.edit', $locacao->id_locacao) }}" title="Editar locação">
                                                            <i class="ti ti-pencil"></i>
                                                        </a>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <strong>#{{ $locacao->numero_contrato }}</strong>
                                                        <small class="text-muted">{{ $locacao->created_at->format('d/m/Y') }}</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span>{{ $locacao->cliente->nome ?? 'N/A' }}</span>
                                                        @if($locacao->cliente && $locacao->cliente->celular)
                                                            <small class="text-muted">{{ $locacao->cliente->celular }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex flex-column">
                                                        <span>{{ optional($locacao->data_inicio)->format('d/m/Y') }} - {{ optional($locacao->data_fim)->format('d/m/Y') }}</span>
                                                        <small class="text-muted">{{ $locacao->total_dias }} dias</small>
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($locacao->produtos->count() > 0)
                                                        <span class="badge bg-label-info">{{ $locacao->produtos->count() }} produto(s)</span>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <strong>R$ {{ number_format($locacao->valor_total, 2, ',', '.') }}</strong>
                                                </td>
                                                <td>
                                                    @php
                                                        $statusColors = [
                                                            'medicao' => 'warning',
                                                            'orcamento' => 'secondary',
                                                            'aprovado' => 'primary',
                                                            'encerrado' => 'success',
                                                            'cancelado' => 'danger',
                                                            'retirada' => 'info',
                                                            'em_andamento' => 'info',
                                                            'atrasada' => 'danger'
                                                        ];
                                                        $statusLabels = \App\Domain\Locacao\Models\Locacao::statusList();
                                                    @endphp
                                                    <span class="badge bg-label-{{ $statusColors[$locacao->status] ?? 'secondary' }}">
                                                        {{ $statusLabels[$locacao->status] ?? $locacao->status }}
                                                    </span>
                                                    @if($locacao->estaAtrasada())
                                                        <span class="badge bg-danger">Atrasada</span>
                                                    @endif
                                                </td>
                                            </tr>
                                            <tr class="collapse" id="detalhes-locacao-{{ $locacao->id_locacao }}">
                                                <td colspan="8" class="bg-body-tertiary">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 p-2">
                                                        <div>
                                                            <div class="fw-semibold mb-1">Informações do Contrato</div>
                                                            <div class="small text-muted">Cliente: {{ $locacao->cliente->nome ?? 'N/A' }}</div>
                                                            <div class="small text-muted">Período: {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ?? '' }} até {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ?? '' }}</div>
                                                            <div class="small text-muted">Valor: R$ {{ number_format($locacao->valor_total, 2, ',', '.') }}</div>
                                                        </div>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            @php
                                                                $modelosContratoDisponiveis = ($modelosContratoAtivos ?? collect());
                                                                $podeAcionarLocacao = !in_array($locacao->status, ['encerrado', 'cancelado'], true);
                                                                $podeRetornarLocacao = in_array($locacao->status, ['aprovado', 'em_andamento', 'atrasada', 'retirada'], true);
                                                                $temPatrimonioPendente = $locacao->produtos
                                                                    ->whereNotNull('id_patrimonio')
                                                                    ->filter(fn($item) => in_array($item->status_retorno, [null, 'pendente'], true))
                                                                    ->isNotEmpty();
                                                            @endphp
                                                            @if($locacao->status === 'orcamento')
                                                                <a class="btn btn-sm btn-outline-secondary btn-doc-action" data-bs-toggle="tooltip" title="Visualizar" href="{{ route('locacoes.show', $locacao->id_locacao) }}"><i class="ti ti-eye"></i></a>
                                                                @if($podeContratoPdfLocacao)
                                                                    <a class="btn btn-sm btn-outline-dark btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Orçamento" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=orcamento"><i class="ti ti-file-text"></i></a>
                                                                    @forelse($modelosContratoDisponiveis as $modeloContrato)
                                                                        <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Imprimir {{ $modeloContrato->nome }}" aria-label="Imprimir {{ $modeloContrato->nome }}" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato&id_modelo={{ $modeloContrato->id_modelo }}"><i class="ti ti-file-description"></i></a>
                                                                    @empty
                                                                        <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Contrato" aria-label="Imprimir Contrato" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato"><i class="ti ti-file-description"></i></a>
                                                                    @endforelse
                                                                    <a class="btn btn-sm btn-outline-info btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Checklist" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=checklist"><i class="ti ti-clipboard-list"></i></a>
                                                                    <a class="btn btn-sm btn-outline-success btn-doc-action" data-bs-toggle="tooltip" title="Comprovante de Entrega" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=entrega"><i class="ti ti-truck-delivery"></i></a>
                                                                    <a class="btn btn-sm btn-outline-warning btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Romaneio" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=romaneio"><i class="ti ti-package-export"></i></a>
                                                                    <a class="btn btn-sm btn-outline-secondary btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Recibo de Locação" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=recibo"><i class="ti ti-receipt-2"></i></a>
                                                                @endif
                                                                @if($podeRenovarLocacao)
                                                                    <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Renovar Locação" href="{{ route('locacoes.edit', $locacao->id_locacao) }}?renovar=1"><i class="ti ti-refresh"></i></a>
                                                                @endif
                                                                @if($podeAlterarStatusLocacao)
                                                                    <button type="button" class="btn btn-sm btn-outline-success btn-doc-action btn-alterar-status" data-bs-toggle="tooltip" title="Aprovar Orçamento" data-id="{{ $locacao->id_locacao }}" data-status="aprovado"><i class="ti ti-check"></i></button>
                                                                @endif
                                                            @elseif($locacao->status === 'aprovado')
                                                                <a class="btn btn-sm btn-outline-secondary btn-doc-action" data-bs-toggle="tooltip" title="Visualizar" href="{{ route('locacoes.show', $locacao->id_locacao) }}"><i class="ti ti-eye"></i></a>
                                                                @if($podeContratoPdfLocacao)
                                                                    @forelse($modelosContratoDisponiveis as $modeloContrato)
                                                                        <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Imprimir {{ $modeloContrato->nome }}" aria-label="Imprimir {{ $modeloContrato->nome }}" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato&id_modelo={{ $modeloContrato->id_modelo }}"><i class="ti ti-file-description"></i></a>
                                                                    @empty
                                                                        <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Contrato" aria-label="Imprimir Contrato" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato"><i class="ti ti-file-description"></i></a>
                                                                    @endforelse
                                                                    <a class="btn btn-sm btn-outline-info btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Checklist" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=checklist"><i class="ti ti-clipboard-list"></i></a>
                                                                    <a class="btn btn-sm btn-outline-success btn-doc-action" data-bs-toggle="tooltip" title="Comprovante de Entrega" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=entrega"><i class="ti ti-truck-delivery"></i></a>
                                                                    <a class="btn btn-sm btn-outline-warning btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Romaneio" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=romaneio"><i class="ti ti-package-export"></i></a>
                                                                    <a class="btn btn-sm btn-outline-secondary btn-doc-action" data-bs-toggle="tooltip" title="Imprimir Recibo de Locação" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=recibo"><i class="ti ti-receipt-2"></i></a>
                                                                @endif
                                                                @if($podeRenovarLocacao)
                                                                    <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Renovar Locação" href="{{ route('locacoes.edit', $locacao->id_locacao) }}?renovar=1"><i class="ti ti-refresh"></i></a>
                                                                @endif
                                                                @if($podeRetornarLocacaoPerm)
                                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-doc-action btn-retornar-locacao" data-bs-toggle="tooltip" title="Retornar Locação" data-id="{{ $locacao->id_locacao }}" data-tem-patrimonio="{{ $temPatrimonioPendente ? 1 : 0 }}"><i class="ti ti-arrow-back-up"></i></button>
                                                                @endif
                                                            @else
                                                                <a class="btn btn-sm btn-outline-secondary btn-doc-action" data-bs-toggle="tooltip" title="Visualizar" href="{{ route('locacoes.show', $locacao->id_locacao) }}"><i class="ti ti-eye"></i></a>
                                                                @if($podeAcionarLocacao && $podeRenovarLocacao)
                                                                    <a class="btn btn-sm btn-outline-primary btn-doc-action" data-bs-toggle="tooltip" title="Renovar Locação" href="{{ route('locacoes.edit', $locacao->id_locacao) }}?renovar=1"><i class="ti ti-refresh"></i></a>
                                                                @endif
                                                                @if($podeRetornarLocacao && $podeRetornarLocacaoPerm)
                                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-doc-action btn-retornar-locacao" data-bs-toggle="tooltip" title="Retornar Locação" data-id="{{ $locacao->id_locacao }}" data-tem-patrimonio="{{ $temPatrimonioPendente ? 1 : 0 }}"><i class="ti ti-arrow-back-up"></i></button>
                                                                @endif
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-file-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhuma locação encontrada</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($locacoes, 'links') && $locacoes->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $locacoes->firstItem() }} até {{ $locacoes->lastItem() }} de {{ $locacoes->total() }} registros
                                    </div>
                                    {{ $locacoes->appends(request()->query())->links() }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetornoPatrimonios" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Retorno de Patrimônios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Marque o status de retorno dos patrimônios e confirme para concluir a devolução da locação.</p>
                <div class="table-responsive">
                    <table class="table table-sm" id="tabelaRetornoPatrimonios">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Patrimônio</th>
                                <th style="width: 170px;">Status</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarRetornoModal">Retornar Locação</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script>
$(document).ready(function() {
    @if(session('success'))
    Swal.fire('Sucesso!', @json(session('success')), 'success');
    @endif

    let locacaoRetornoAtual = null;
    const modalRetornoElement = document.getElementById('modalRetornoPatrimonios');
    const modalRetorno = modalRetornoElement ? new bootstrap.Modal(modalRetornoElement) : null;

    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    function executarRetornoLocacao(idLocacao, retornos = []) {
        $.ajax({
            url: `{{ url('locacoes') }}/${idLocacao}/retornar`,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                retornos: retornos
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', response.message, 'success').then(() => location.reload());
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON || {};

                if (xhr.status === 422 && response.requires_patrimonio_return && Array.isArray(response.patrimonios_pendentes)) {
                    locacaoRetornoAtual = idLocacao;
                    const $tbody = $('#tabelaRetornoPatrimonios tbody');
                    $tbody.empty();

                    response.patrimonios_pendentes.forEach((item) => {
                        $tbody.append(`
                            <tr data-id-produto-locacao="${item.id_produto_locacao}">
                                <td>${item.produto_nome || '-'}</td>
                                <td>${item.patrimonio_codigo || '-'}</td>
                                <td>
                                    <select class="form-select form-select-sm retorno-status">
                                        <option value="devolvido">Devolvido</option>
                                        <option value="avariado">Avariado</option>
                                        <option value="extraviado">Extraviado</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm retorno-observacoes" placeholder="Observações (opcional)">
                                </td>
                            </tr>
                        `);
                    });

                    if (modalRetorno) {
                        modalRetorno.show();
                    }
                    return;
                }

                Swal.fire('Erro!', response.message || 'Não foi possível retornar a locação.', 'error');
            }
        });
    }

    // Alterar status
    $(document).on('click', '.btn-alterar-status', function() {
        var id = $(this).data('id');
        var status = $(this).data('status');
        var labels = {
            'aprovado': 'aprovar',
            'em_andamento': 'iniciar',
            'encerrado': 'encerrar',
            'cancelada': 'cancelar'
        };
        
        Swal.fire({
            title: 'Confirmar ação',
            text: `Deseja realmente ${labels[status]} esta locação?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, confirmar!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('locacoes') }}/${id}/status`,
                    type: 'PATCH',
                    data: { 
                        _token: '{{ csrf_token() }}',
                        status: status
                    },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao alterar status.', 'error');
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-retornar-locacao', function() {
        const idLocacao = $(this).data('id');

        Swal.fire({
            title: 'Confirmar retorno',
            text: 'Deseja realmente retornar esta locação?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, retornar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                executarRetornoLocacao(idLocacao);
            }
        });
    });

    $('#btnConfirmarRetornoModal').on('click', function() {
        if (!locacaoRetornoAtual) {
            return;
        }

        const retornos = [];
        $('#tabelaRetornoPatrimonios tbody tr').each(function() {
            retornos.push({
                id_produto_locacao: $(this).data('id-produto-locacao'),
                status: $(this).find('.retorno-status').val(),
                observacoes: $(this).find('.retorno-observacoes').val()
            });
        });

        if (modalRetorno) {
            modalRetorno.hide();
        }

        executarRetornoLocacao(locacaoRetornoAtual, retornos);
    });

    // Excluir locação
    $('.btn-excluir-locacao').on('click', function() {
        var id = $(this).data('id');
        
        Swal.fire({
            title: 'Confirmar exclusão',
            text: 'Deseja realmente excluir esta locação?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, excluir!',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('locacoes') }}/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Sucesso!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao excluir.', 'error');
                    }
                });
            }
        });
    });
});
</script>
<script>
document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href*="tipo="]');
    if (!link) return;
    if (!link.href.includes('contrato-pdf')) return;

    var url = new URL(link.href, window.location.origin);
    var tipo = String(url.searchParams.get('tipo') || '').toLowerCase();
    if (!['orcamento', 'checklist', 'entrega'].includes(tipo)) return;
    if (url.searchParams.has('com_foto')) return;

    event.preventDefault();

    Swal.fire({
        title: 'Impressão de Produtos',
        text: 'Deseja imprimir com foto dos produtos?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Com foto',
        cancelButtonText: 'Sem foto',
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d'
    }).then(function (result) {
        url.searchParams.set('com_foto', result.isConfirmed ? '1' : '0');
        window.open(url.toString(), link.target || '_blank');
    });
});
</script>
@endsection
