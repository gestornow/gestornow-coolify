@props([
    'stats' => [],
    'type' => 'pagar' // 'pagar' ou 'receber'
])

@php
    $bgColor = $type === 'pagar' ? 'bg-danger' : 'bg-success';
    $iconColor = $type === 'pagar' ? 'text-danger' : 'text-success';
    $titulo = $type === 'pagar' ? 'Contas a Pagar' : 'Contas a Receber';
@endphp

<div class="row mb-3">
    <!-- Total de Contas -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded-circle bg-primary">
                            <i class="ti ti-file-text text-white" style="font-size: 24px; padding: 10px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <p class="text-muted mb-1">Total</p>
                        <h4 class="mb-0">{{ $stats['total'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pendentes -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded-circle bg-warning">
                            <i class="ti ti-clock text-white" style="font-size: 24px; padding: 10px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <p class="text-muted mb-1">Pendentes</p>
                        <h4 class="mb-0">{{ $stats['pendentes'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagas -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded-circle bg-success">
                            <i class="ti ti-check text-white" style="font-size: 24px; padding: 10px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <p class="text-muted mb-1">{{ $type === 'pagar' ? 'Pagas' : 'Recebidas' }}</p>
                        <h4 class="mb-0">{{ $stats['pagas'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Vencidas -->
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card border-danger">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="avatar-sm rounded-circle bg-danger">
                            <i class="ti ti-alert-triangle text-white" style="font-size: 24px; padding: 10px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <p class="text-muted mb-1">Vencidas</p>
                        <h4 class="mb-0">{{ $stats['vencidas'] ?? 0 }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Valores -->
<div class="row mb-3">
    <div class="col-md-6">
        <div class="card {{ $bgColor }} text-white">
            <div class="card-body">
                <h5 class="card-title text-white">
                    <i class="ti ti-currency-dollar me-2"></i>
                    {{ $type === 'pagar' ? 'Total a Pagar' : 'Total a Receber' }}
                </h5>
                <h3 class="mb-0">R$ {{ number_format($stats['valor_total_pendente'] ?? 0, 2, ',', '.') }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h5 class="card-title text-white">
                    <i class="ti ti-check me-2"></i>
                    {{ $type === 'pagar' ? 'Total Pago' : 'Total Recebido' }}
                </h5>
                <h3 class="mb-0">R$ {{ number_format($stats['valor_total_pago'] ?? 0, 2, ',', '.') }}</h3>
            </div>
        </div>
    </div>
</div>
