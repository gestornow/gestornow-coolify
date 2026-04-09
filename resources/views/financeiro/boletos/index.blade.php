@extends('layouts.layoutMaster')

@section('title', 'Boletos')

@php
    $podeOperarBoletos = \Perm::pode(auth()->user(), 'financeiro.boletos');
@endphp

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
.badge-status {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
}
.linha-digitavel {
    font-family: monospace;
    font-size: 0.75rem;
    word-break: break-all;
    color: #6c757d;
}
.btn-icon-action {
    border: none !important;
    background: transparent !important;
    padding: 0.25rem !important;
}
.btn-icon-action:hover {
    background: rgba(105, 108, 255, 0.1) !important;
}
.badge-banco {
    font-size: 0.7rem;
    padding: 0.4em 0.7em;
    font-weight: 500;
}
.badge-banco-inter {
    background: linear-gradient(135deg, #ff7a00 0%, #ff5500 100%);
    color: white;
}
.badge-banco-default {
    background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
    color: white;
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
                                    <span>Total de Boletos</span>
                                </div>
                                <span class="badge bg-label-primary rounded p-2">
                                    <i class="ti ti-file-barcode ti-sm"></i>
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
                                        <h4 class="mb-0 me-2">{{ $stats['gerados'] ?? 0 }}</h4>
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
                                    <span>Pagos</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['pagos'] ?? 0 }}</h4>
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
                                    <span>Vencidos</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">{{ $stats['vencidos'] ?? 0 }}</h4>
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
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Total Emitido</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_total'] ?? 0, 2, ',', '.') }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-primary rounded p-2">
                                    <i class="ti ti-cash ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Total Recebido</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_recebido'] ?? 0, 2, ',', '.') }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-success rounded p-2">
                                    <i class="ti ti-cash-off ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div class="content-left">
                                    <span>Pendente Recebimento</span>
                                    <div class="d-flex align-items-center my-1">
                                        <h4 class="mb-0 me-2">R$ {{ number_format($stats['valor_pendente'] ?? 0, 2, ',', '.') }}</h4>
                                    </div>
                                </div>
                                <span class="badge bg-label-warning rounded p-2">
                                    <i class="ti ti-hourglass ti-sm"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Filtros de Busca</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('financeiro.boletos.index') }}" id="formFiltros">
                        <div class="row g-3">
                            <div class="col-md-2">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    <option value="gerado" {{ request('status') == 'gerado' ? 'selected' : '' }}>Gerado</option>
                                    <option value="pago" {{ request('status') == 'pago' ? 'selected' : '' }}>Pago</option>
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Banco</label>
                                <select name="id_bancos" class="form-select" onchange="this.form.submit()">
                                    <option value="">Todos</option>
                                    @if(isset($bancos))
                                        @foreach($bancos as $banco)
                                            <option value="{{ $banco->id_bancos }}" {{ request('id_bancos') == $banco->id_bancos ? 'selected' : '' }}>
                                                {{ $banco->nome_banco ?? 'Banco #'.$banco->id_bancos }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Data Início</label>
                                <input type="date" name="data_inicio" class="form-control" 
                                    value="{{ request('data_inicio') }}">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Data Fim</label>
                                <input type="date" name="data_fim" class="form-control" 
                                    value="{{ request('data_fim') }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="busca" class="form-control" 
                                    placeholder="Nosso número, cliente..." 
                                    value="{{ request('busca') }}">
                            </div>

                            <div class="col-md-1 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="ti ti-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Boletos -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Boletos Gerados</h5>
                    @if(request()->hasAny(['status', 'id_bancos', 'data_inicio', 'data_fim', 'busca']))
                        <a href="{{ route('financeiro.boletos.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-x me-1"></i>
                            Limpar Filtros
                        </a>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nosso Número</th>
                                <th>Conta a Receber</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Banco</th>
                                <th>Status</th>
                                <th class="text-center">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($boletos as $boleto)
                                <tr>
                                    <td>
                                        <strong>{{ $boleto->nosso_numero ?? '-' }}</strong>
                                        @if($boleto->linha_digitavel)
                                            <br>
                                            <small class="linha-digitavel" title="Linha Digitável">
                                                {{ Str::limit($boleto->linha_digitavel, 25) }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        @if($boleto->contaAReceber)
                                            <a href="{{ route('financeiro.contas-a-receber.show', $boleto->contaAReceber->id_contas) }}" 
                                               class="text-primary fw-semibold">
                                                #{{ $boleto->contaAReceber->id_contas }}
                                            </a>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($boleto->contaAReceber->descricao ?? '', 25) }}</small>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $boleto->contaAReceber->cliente->nome ?? '-' }}</span>
                                    </td>
                                    <td>
                                        <strong class="text-dark">R$ {{ number_format($boleto->valor_nominal, 2, ',', '.') }}</strong>
                                        @if($boleto->status === 'pago' && $boleto->valor_pago)
                                            <br>
                                            <small class="text-success fw-medium">
                                                <i class="ti ti-check ti-xs"></i> R$ {{ number_format($boleto->valor_pago, 2, ',', '.') }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-medium">{{ $boleto->data_vencimento ? \Carbon\Carbon::parse($boleto->data_vencimento)->format('d/m/Y') : '-' }}</span>
                                        @if($boleto->isVencido())
                                            <br><span class="badge bg-label-danger badge-status"><i class="ti ti-alert-circle ti-xs"></i> Vencido</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $nomeBanco = $boleto->banco->nome_banco ?? null;
                                            $isInter = $nomeBanco && (stripos($nomeBanco, 'inter') !== false);
                                        @endphp
                                        @if($nomeBanco)
                                            <span class="badge badge-banco {{ $isInter ? 'badge-banco-inter' : 'badge-banco-default' }}">
                                                <i class="ti ti-building-bank ti-xs me-1"></i>
                                                {{ $nomeBanco }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-status {{ $boleto->getStatusBadgeClass() }}">
                                            {{ $boleto->status_label }}
                                        </span>
                                        @if($boleto->data_pagamento)
                                            <br>
                                            <small class="text-muted">
                                                {{ \Carbon\Carbon::parse($boleto->data_pagamento)->format('d/m/Y H:i') }}
                                            </small>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex align-items-center justify-content-center gap-1">
                                            @if($podeOperarBoletos)
                                                <!-- Ver PDF (não disponível para boletos cancelados) -->
                                                @if($boleto->status !== 'cancelado')
                                                    <a href="{{ route('financeiro.boletos.pdf', $boleto->id_boleto) }}" 
                                                       class="btn btn-sm btn-icon btn-outline-primary" 
                                                       target="_blank"
                                                       title="Ver PDF">
                                                        <i class="ti ti-file-text"></i>
                                                    </a>
                                                @endif

                                                <!-- Copiar Linha Digitável -->
                                                @if($boleto->linha_digitavel && $boleto->status !== 'cancelado')
                                                    <button type="button" 
                                                        class="btn btn-sm btn-icon btn-outline-secondary btn-copiar" 
                                                        data-linha="{{ $boleto->linha_digitavel }}"
                                                        title="Copiar Linha Digitável">
                                                        <i class="ti ti-copy"></i>
                                                    </button>
                                                @endif

                                                <!-- Consultar Situação -->
                                                <button type="button" 
                                                    class="btn btn-sm btn-icon btn-outline-info btn-consultar" 
                                                    data-id="{{ $boleto->id_boleto }}"
                                                    title="Consultar Situação">
                                                    <i class="ti ti-refresh"></i>
                                                </button>

                                                <!-- Alterar Vencimento (apenas para boletos não pagos/cancelados) -->
                                                @if(!in_array($boleto->status, ['pago', 'cancelado']))
                                                    <button type="button" 
                                                        class="btn btn-sm btn-icon btn-outline-warning btn-alterar-vencimento" 
                                                        data-id="{{ $boleto->id_boleto }}"
                                                        data-vencimento="{{ $boleto->data_vencimento ? \Carbon\Carbon::parse($boleto->data_vencimento)->format('Y-m-d') : '' }}"
                                                        data-valor="{{ $boleto->valor_nominal }}"
                                                        title="Alterar Vencimento">
                                                        <i class="ti ti-calendar-event"></i>
                                                    </button>
                                                @endif
                                            @endif

                                            <!-- Ver Histórico (apenas suporte) -->
                                            @if(auth()->user()->is_suporte ?? false)
                                                <button type="button" 
                                                    class="btn btn-sm btn-icon btn-outline-dark btn-historico" 
                                                    data-id="{{ $boleto->id_boleto }}"
                                                    title="Ver Histórico">
                                                    <i class="ti ti-history"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted">
                                            <i class="ti ti-file-barcode ti-xl mb-2 d-block"></i>
                                            Nenhum boleto encontrado
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($boletos->hasPages())
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                Mostrando {{ $boletos->firstItem() }} a {{ $boletos->lastItem() }} de {{ $boletos->total() }} registros
                            </small>
                            {{ $boletos->links() }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Modal Alterar Vencimento -->
<div class="modal fade" id="modalAlterarVencimento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-calendar-event me-2"></i>
                    Alterar Vencimento do Boleto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAlterarVencimento">
                <div class="modal-body">
                    <div class="alert alert-warning mb-3">
                        <i class="ti ti-alert-triangle me-1"></i>
                        <strong>Atenção:</strong> O boleto atual será cancelado e um novo será gerado com a nova data de vencimento.
                    </div>
                    
                    <input type="hidden" id="boleto_id" name="boleto_id">
                    
                    <div class="mb-3">
                        <label for="nova_data_vencimento" class="form-label">Nova Data de Vencimento</label>
                        <input type="date" 
                               class="form-control" 
                               id="nova_data_vencimento" 
                               name="data_vencimento" 
                               required
                               min="{{ now()->addDay()->format('Y-m-d') }}">
                    </div>
                    
                    <div class="mb-3">
                        <label for="novo_valor" class="form-label">Valor do Boleto</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" 
                                   class="form-control" 
                                   id="novo_valor" 
                                   name="valor" 
                                   placeholder="0,00">
                        </div>
                        <small class="text-muted">Deixe em branco para manter o valor atual</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnConfirmarAlteracao">
                        <i class="ti ti-check me-1"></i>
                        Confirmar Alteração
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Histórico -->
<div class="modal fade" id="modalHistorico" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-history me-2"></i>
                    Histórico do Boleto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="historicoContent">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copiar Linha Digitável
    document.querySelectorAll('.btn-copiar').forEach(btn => {
        btn.addEventListener('click', function() {
            const linha = this.dataset.linha;
            navigator.clipboard.writeText(linha).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'Copiado!',
                    text: 'Linha digitável copiada para a área de transferência.',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        });
    });

    // Consultar Situação
    document.querySelectorAll('.btn-consultar').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const btn = this;
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            fetch(`{{ url('financeiro/boletos') }}/${id}/consultar`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Situação do Boleto',
                            html: `
                                <div class="text-start">
                                    <p><strong>Status:</strong> ${data.boleto.status}</p>
                                    <p><strong>Situação Banco:</strong> ${data.boleto.situacao_banco || '-'}</p>
                                    ${data.boleto.data_pagamento ? `<p><strong>Data Pagamento:</strong> ${data.boleto.data_pagamento}</p>` : ''}
                                    ${data.boleto.valor_pago ? `<p><strong>Valor Pago:</strong> R$ ${parseFloat(data.boleto.valor_pago).toFixed(2).replace('.', ',')}</p>` : ''}
                                </div>
                            `,
                            confirmButtonText: 'Fechar'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Erro',
                            text: data.message || 'Erro ao consultar boleto.'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: 'Erro ao consultar boleto.'
                    });
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="ti ti-refresh"></i>';
                });
        });
    });

    // Alterar Vencimento - Abrir Modal
    document.querySelectorAll('.btn-alterar-vencimento').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const vencimento = this.dataset.vencimento;
            const valor = this.dataset.valor;
            
            document.getElementById('boleto_id').value = id;
            document.getElementById('nova_data_vencimento').value = '';
            document.getElementById('novo_valor').value = parseFloat(valor).toFixed(2).replace('.', ',');
            
            const modal = new bootstrap.Modal(document.getElementById('modalAlterarVencimento'));
            modal.show();
        });
    });

    // Alterar Vencimento - Submit Form
    document.getElementById('formAlterarVencimento').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const id = document.getElementById('boleto_id').value;
        const dataVencimento = document.getElementById('nova_data_vencimento').value;
        let valor = document.getElementById('novo_valor').value;
        
        // Converter valor para formato numérico
        if (valor) {
            valor = valor.replace(/\./g, '').replace(',', '.');
        }
        
        const btn = document.getElementById('btnConfirmarAlteracao');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processando...';
        
        fetch(`{{ url('financeiro/boletos') }}/${id}/alterar-vencimento`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                data_vencimento: dataVencimento,
                valor: valor || null
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso!',
                    text: data.message,
                    confirmButtonText: 'OK'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: data.message || 'Erro ao alterar vencimento.'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: 'Erro ao processar requisição.'
            });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-check me-1"></i> Confirmar Alteração';
            bootstrap.Modal.getInstance(document.getElementById('modalAlterarVencimento')).hide();
        });
    });

    // Ver Histórico
    document.querySelectorAll('.btn-historico').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const modal = new bootstrap.Modal(document.getElementById('modalHistorico'));
            const content = document.getElementById('historicoContent');
            
            content.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            fetch(`{{ url('financeiro/boletos') }}/${id}/historico`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.historicos.length > 0) {
                        let html = '<div class="list-group list-group-flush">';
                        data.historicos.forEach(h => {
                            const tipoClass = {
                                'webhook': 'primary',
                                'consulta': 'info',
                                'erro': 'danger',
                                'geracao': 'success',
                                'cancelamento': 'warning'
                            }[h.tipo] || 'secondary';
                            
                            html += `
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <span class="badge bg-${tipoClass} mb-2">${h.tipo.toUpperCase()}</span>
                                            <p class="mb-1 text-muted small">${h.created_at}</p>
                                        </div>
                                    </div>
                                    <pre class="bg-light p-2 rounded mb-0" style="font-size: 0.75rem; max-height: 150px; overflow: auto;">${JSON.stringify(h.conteudo_decodificado || h.conteudo, null, 2)}</pre>
                                </div>
                            `;
                        });
                        html += '</div>';
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-4 text-muted">
                                <i class="ti ti-history ti-xl mb-2 d-block"></i>
                                Nenhum histórico encontrado para este boleto.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="text-center py-4 text-danger">
                            <i class="ti ti-alert-circle ti-xl mb-2 d-block"></i>
                            Erro ao carregar histórico.
                        </div>
                    `;
                });
        });
    });
});
</script>
@endsection
