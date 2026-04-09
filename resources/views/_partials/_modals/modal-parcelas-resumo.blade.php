{{-- Resumo de Estatísticas das Parcelas --}}
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="badge bg-label-{{ $badgeColor ?? 'primary' }} rounded p-2 me-3">
                        <i class="ti ti-list ti-sm"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Total de Parcelas</small>
                        <h5 class="mb-0">{{ $totalParcelas }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="badge bg-label-success rounded p-2 me-3">
                        <i class="ti ti-check ti-sm"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">{{ $tipoConta === 'receber' ? 'Recebidas' : 'Pagas' }}</small>
                        <h5 class="mb-0">{{ $totalPagas }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="badge bg-label-warning rounded p-2 me-3">
                        <i class="ti ti-clock ti-sm"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Pendentes</small>
                        <h5 class="mb-0">{{ $totalPendentes }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="badge bg-label-info rounded p-2 me-3">
                        <i class="ti ti-cash ti-sm"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Valor Total</small>
                        <h5 class="mb-0">R$ {{ number_format($valorTotal, 2, ',', '.') }}</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
