@extends('layouts.layoutMaster')

@section('title', 'Histórico de Vendas PDV')

@section('page-style')
<style>
    .vendas-table-wrapper {
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
    }

    .vendas-table {
        min-width: 700px;
    }

    @media (max-width: 767.98px) {
        .vendas-filtros-header {
            gap: .6rem;
            align-items: flex-start !important;
        }

        .vendas-filtros-header > div {
            width: 100%;
        }

        .vendas-filtros-header .btn {
            width: 100%;
        }
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="row">
                <!-- Cards de Estatísticas -->
                <div class="col-12">
                    <div class="row g-4 mb-4">
                        <div class="col-sm-6 col-xl-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2">{{ $stats['total_vendas'] ?? 0 }}</h4>
                                            </div>
                                            <span>Total de Vendas</span>
                                        </div>
                                        <span class="badge bg-label-primary rounded p-2">
                                            <i class="ti ti-receipt ti-sm"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-6 col-xl-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-start justify-content-between">
                                        <div class="content-left">
                                            <span>Valor Total</span>
                                            <div class="d-flex align-items-center my-1">
                                                <h4 class="mb-0 me-2 text-success">R$ {{ number_format($stats['valor_total'] ?? 0, 2, ',', '.') }}</h4>
                                            </div>
                                        </div>
                                        <span class="badge bg-label-success rounded p-2">
                                            <i class="ti ti-currency-dollar ti-sm"></i>
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
                        <div class="card-header d-flex justify-content-between align-items-center vendas-filtros-header flex-wrap gap-2">
                            <h5 class="mb-0">Histórico de Vendas PDV</h5>
                            <div class="d-flex gap-2">
                                @pode('pdv.relatorio')
                                    <a href="{{ route('pdv.relatorio-vendas', request()->query()) }}" class="btn btn-outline-info" title="Relatório Completo">
                                        <i class="ti ti-chart-bar me-1"></i>
                                        Relatório
                                    </a>
                                @endpode
                                <a href="{{ route('pdv.index') }}" class="btn btn-success">
                                    <i class="ti ti-shopping-cart me-1"></i>
                                    Abrir PDV
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
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
                                    <label class="form-label small mb-1">Nº da Venda</label>
                                    <input type="text" name="numero_venda" class="form-control" placeholder="Número" value="{{ $filters['numero_venda'] ?? '' }}">
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
                </div>

                <!-- Tabela de Vendas -->
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive vendas-table-wrapper">
                                <table class="table table-hover vendas-table">
                                    <thead>
                                        <tr>
                                            <th>Nº Venda</th>
                                            <th>Data/Hora</th>
                                            <th>Itens</th>
                                            <th>Total</th>
                                            <th>Forma Pgto</th>
                                            <th>Operador</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($vendas as $venda)
                                            <tr>
                                                <td>
                                                    <strong>#{{ $venda->numero_venda }}</strong>
                                                </td>
                                                <td>{{ $venda->data_venda->format('d/m/Y H:i') }}</td>
                                                <td>{{ $venda->itens->count() }}</td>
                                                <td>
                                                    <strong class="text-success">{{ $venda->total_formatado }}</strong>
                                                </td>
                                                <td>{{ $venda->formaPagamento->nome ?? '-' }}</td>
                                                <td>{{ $venda->usuario->nome ?? '-' }}</td>
                                                <td>
                                                    @if($venda->status === 'finalizada') 
                                                        <span class="badge bg-label-success">Finalizada</span>
                                                    @elseif($venda->status === 'cancelada')
                                                        <span class="badge bg-label-danger">Cancelada</span>
                                                    @else 
                                                        <span class="badge bg-label-warning">{{ ucfirst($venda->status) }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="dropdown">
                                                        <button class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                                            <i class="ti ti-dots-vertical"></i>
                                                        </button>
                                                        <div class="dropdown-menu dropdown-menu-end">
                                                            <a class="dropdown-item" href="{{ route('pdv.cupom', $venda->id_venda) }}" target="_blank">
                                                                <i class="ti ti-printer me-2"></i>Imprimir Cupom
                                                            </a>
                                                            <a class="dropdown-item btn-visualizar-venda" href="javascript:void(0)" data-id="{{ $venda->id_venda }}">
                                                                <i class="ti ti-eye me-2"></i>Visualizar Venda
                                                            </a>
                                                            @if($venda->status === 'finalizada')
                                                                @pode('pdv.cancelar-venda')
                                                                <div class="dropdown-divider"></div>
                                                                <a href="javascript:void(0)" class="dropdown-item text-danger btn-cancelar-venda" data-id="{{ $venda->id_venda }}" data-numero="{{ $venda->numero_venda }}">
                                                                    <i class="ti ti-x me-2"></i>Cancelar Venda
                                                                </a>
                                                                @endpode
                                                            @endif
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4">
                                                    <div class="text-muted">
                                                        <i class="ti ti-receipt-off ti-lg mb-2"></i>
                                                        <p class="mb-0">Nenhuma venda encontrada</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            @if(method_exists($vendas, 'links') && $vendas->total() > 0)
                                <div class="d-flex justify-content-between align-items-center mt-4">
                                    <div class="text-muted">
                                        Mostrando {{ $vendas->firstItem() }} até {{ $vendas->lastItem() }} de {{ $vendas->total() }} registros
                                    </div>
                                    <nav>
                                        {{ $vendas->appends(request()->query())->links() }}
                                    </nav>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Visualizar Venda -->
<div class="modal fade" id="modalVisualizarVenda" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">
                    <i class="ti ti-receipt me-2"></i>
                    Detalhes da Venda <span id="vendaNumero"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalVendaBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" class="btn btn-primary" id="btnImprimirCupomModal" target="_blank">
                    <i class="ti ti-printer me-1"></i> Imprimir Cupom
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
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
    // Visualizar venda
    $('.btn-visualizar-venda').on('click', function() {
        const id = $(this).data('id');
        
        $('#vendaNumero').text('');
        $('#modalVendaBody').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Carregando...</span></div></div>');
        $('#btnImprimirCupomModal').attr('href', '{{ url("pdv/cupom") }}/' + id);
        
        $('#modalVisualizarVenda').modal('show');
        
        $.ajax({
            url: '{{ url("pdv/cupom-dados") }}/' + id,
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    const venda = response.venda;
                    $('#vendaNumero').text('#' + venda.numero_venda);
                    
                    let statusBadge = '';
                    if (venda.status === 'finalizada') {
                        statusBadge = '<span class="badge bg-label-success">Finalizada</span>';
                    } else if (venda.status === 'cancelada') {
                        statusBadge = '<span class="badge bg-label-danger">Cancelada</span>';
                    } else {
                        statusBadge = '<span class="badge bg-label-warning">' + venda.status + '</span>';
                    }
                    
                    let html = `
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Data/Hora:</strong> ${venda.data_venda_formatada}</p>
                                <p class="mb-1"><strong>Operador:</strong> ${venda.operador || '-'}</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <p class="mb-1"><strong>Status:</strong> ${statusBadge}</p>
                                <p class="mb-1"><strong>Forma de Pagamento:</strong> ${venda.forma_pagamento || '-'}</p>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Produto</th>
                                        <th class="text-center" style="width: 70px;">Qtd</th>
                                        <th class="text-end" style="width: 100px;">Valor Unit.</th>
                                        <th class="text-end" style="width: 100px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>`;
                    
                    venda.itens.forEach(function(item) {
                        html += `
                            <tr>
                                <td>${item.nome_produto}</td>
                                <td class="text-center">${item.quantidade}</td>
                                <td class="text-end">R$ ${parseFloat(item.preco_unitario).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                <td class="text-end">R$ ${parseFloat(item.subtotal).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                            </tr>`;
                    });
                    
                    html += `
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6"></div>
                            <div class="col-md-6">
                                <table class="table table-sm mb-0">
                                    <tr>
                                        <td class="border-0"><strong>Subtotal:</strong></td>
                                        <td class="border-0 text-end">R$ ${parseFloat(venda.subtotal || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    </tr>`;
                    
                    if (venda.desconto && parseFloat(venda.desconto) > 0) {
                        html += `
                                    <tr>
                                        <td class="border-0 text-danger"><strong>Desconto:</strong></td>
                                        <td class="border-0 text-end text-danger">- R$ ${parseFloat(venda.desconto).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    </tr>`;
                    }
                    
                    html += `
                                    <tr class="table-light">
                                        <td class="border-0"><strong class="fs-5">Total:</strong></td>
                                        <td class="border-0 text-end"><strong class="fs-5 text-success">R$ ${parseFloat(venda.total).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</strong></td>
                                    </tr>`;
                    
                    if (venda.valor_pago && parseFloat(venda.valor_pago) > parseFloat(venda.total)) {
                        const troco = parseFloat(venda.valor_pago) - parseFloat(venda.total);
                        html += `
                                    <tr>
                                        <td class="border-0"><strong>Valor Pago:</strong></td>
                                        <td class="border-0 text-end">R$ ${parseFloat(venda.valor_pago).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    </tr>
                                    <tr>
                                        <td class="border-0"><strong>Troco:</strong></td>
                                        <td class="border-0 text-end text-info">R$ ${troco.toLocaleString('pt-BR', {minimumFractionDigits: 2})}</td>
                                    </tr>`;
                    }
                    
                    html += `
                                </table>
                            </div>
                        </div>
                    `;
                    
                    $('#modalVendaBody').html(html);
                }
            },
            error: function(xhr) {
                $('#modalVendaBody').html('<div class="alert alert-danger">Erro ao carregar dados da venda.</div>');
            }
        });
    });

    // Cancelar venda
    $('.btn-cancelar-venda').on('click', function() {
        const id = $(this).data('id');
        const numero = $(this).data('numero');

        Swal.fire({
            title: 'Cancelar venda?',
            html: `Deseja cancelar a venda <strong>#${numero}</strong>?<br><br>
                   <small class="text-warning">O estoque será restaurado e o registro no fluxo de caixa será removido.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sim, cancelar!',
            cancelButtonText: 'Não'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ url("pdv/cancelar") }}/' + id,
                    method: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Cancelada!', response.message, 'success').then(() => {
                                location.reload();
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao cancelar', 'error');
                    }
                });
            }
        });
    });
});
</script>

<style>
@media print {
    .card-header .d-flex.gap-2,
    .dropdown,
    nav,
    .vendas-filtros-header .btn,
    .btn-primary,
    form {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>
@endsection
