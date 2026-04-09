@extends('layouts.layoutMaster')

@section('title', 'Faturamento de Locações')

@php
    $podeOperarFaturamento = \Perm::pode(auth()->user(), 'financeiro.faturamento');
    $podeVisualizarReceber = \Perm::pode(auth()->user(), 'financeiro.contas-receber.visualizar');
@endphp

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Faturamento de Locações</h4>
                    <p class="text-muted mb-0">Controle dos faturamentos gerados automaticamente no fechamento das locações.</p>
                </div>
                <div class="d-flex gap-2">
                    @if($podeOperarFaturamento)
                        <a href="{{ route('financeiro.faturamento.pendentes') }}" class="btn btn-primary">
                            <i class="ti ti-file-plus me-1"></i>
                            Faturar Locações
                        </a>
                    @endif
                    @if($podeVisualizarReceber)
                        <a href="{{ route('financeiro.contas-a-receber.index') }}" class="btn btn-outline-primary">
                            <i class="ti ti-cash me-1"></i>
                            Contas a Receber
                        </a>
                    @endif
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ti ti-check me-2"></i>
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ti ti-alert-triangle me-2"></i>
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if(!$tabelaDisponivel)
                <div class="alert alert-warning" role="alert">
                    A tabela <strong>faturamento_locacoes</strong> ainda não existe no banco. Execute o script SQL <strong>database/sql/create_faturamento_locacoes.sql</strong> para habilitar esta tela.
                </div>
            @endif

            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted">Total de Registros</span>
                            <h4 class="mb-0 mt-1">{{ $stats['total_registros'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted">Valor Faturado</span>
                            <h4 class="mb-0 mt-1 text-primary">R$ {{ number_format($stats['valor_total'] ?? 0, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted">Valor Recebido</span>
                            <h4 class="mb-0 mt-1 text-success">R$ {{ number_format($stats['valor_recebido'] ?? 0, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <span class="text-muted">Valor em Aberto</span>
                            <h4 class="mb-0 mt-1 text-warning">R$ {{ number_format($stats['valor_aberto'] ?? 0, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('financeiro.faturamento.index') }}">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Mês/Ano</label>
                                <input type="month" name="mes_filtro" class="form-control" value="{{ $mesFiltro ?? now()->format('Y-m') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status Financeiro</label>
                                <select name="status" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="pendente" {{ request('status') === 'pendente' ? 'selected' : '' }}>Pendente</option>
                                    <option value="recebido" {{ request('status') === 'recebido' ? 'selected' : '' }}>Recebido</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Busca</label>
                                <input type="text" name="busca" value="{{ request('busca') }}" class="form-control" placeholder="Contrato, cliente ou descrição">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th style="width: 40px;"></th>
                                    <th>Data</th>
                                    <th>Locação</th>
                                    <th>Cliente</th>
                                    <th>Descrição</th>
                                    <th class="text-end">Valor</th>
                                    <th>Vencimento</th>
                                    <th>Status</th>
                                    <th class="text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($faturamentosAgrupados as $item)
                                    @if($item['tipo'] === 'lote')
                                        @php
                                            $statusConta = $item['conta_receber']->status ?? null;
                                            $statusLabel = $statusConta === 'pago' ? 'Recebido' : 'Pendente';
                                            $statusClass = $statusConta === 'pago' ? 'bg-label-success' : 'bg-label-warning';
                                        @endphp
                                        <tr class="lote-row">
                                            <td>
                                                <button type="button" 
                                                    class="btn btn-sm btn-icon btn-outline-primary" 
                                                    onclick="toggleLote('{{ $item['id_grupo'] }}')"
                                                    title="Expandir Lote">
                                                    <i class="ti ti-chevron-right" id="icon-lote-{{ $item['id_grupo'] }}"></i>
                                                </button>
                                            </td>
                                            <td>{{ optional($item['data_faturamento'])->format('d/m/Y') }}</td>
                                            <td>
                                                <span class="badge bg-label-info">
                                                    <i class="ti ti-files"></i> {{ $item['quantidade'] }} Locações
                                                </span>
                                            </td>
                                            <td colspan="2">
                                                <strong>Faturamento em Lote</strong>
                                                <br>
                                                <small class="text-muted">{{ $item['id_grupo'] }}</small>
                                            </td>
                                            <td class="text-end fw-semibold">R$ {{ number_format((float) $item['valor_total'], 2, ',', '.') }}</td>
                                            <td>{{ optional($item['data_vencimento'])->format('d/m/Y') ?? '-' }}</td>
                                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    <button type="button" 
                                                        class="btn btn-sm btn-outline-info" 
                                                        onclick="window.open('/financeiro/faturamento/pdf/{{ $item['faturamentos']->first()->id_faturamento_locacao }}', '_blank')"
                                                        title="Gerar PDF do Lote Completo">
                                                        <i class="ti ti-file-text"></i>
                                                    </button>
                                                    @if($item['conta_receber'] && $podeVisualizarReceber)
                                                        <a href="{{ route('financeiro.contas-a-receber.edit', $item['conta_receber']->id_contas) }}" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           title="Ver Conta">
                                                            <i class="ti ti-cash"></i>
                                                        </a>
                                                    @endif
                                                    @if($statusConta !== 'pago' && $podeOperarFaturamento)
                                                        <button type="button" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            onclick="cancelarLote('{{ $item['id_grupo'] }}')"
                                                            title="Cancelar Lote">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Linha expansível para faturas do lote -->
                                        <tr id="lote-row-{{ $item['id_grupo'] }}" class="expanded-row" style="display: none;">
                                            <td colspan="9" class="p-0">
                                                <div class="bg-light p-3 expanded-content">
                                                    <h6 class="mb-3">
                                                        <i class="ti ti-files me-1"></i>
                                                        Faturas Individuais do Lote
                                                    </h6>
                                                    <table class="table table-sm table-bordered mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Locação</th>
                                                                <th>Cliente</th>
                                                                <th>Descrição</th>
                                                                <th class="text-end">Valor</th>
                                                                <th class="text-center">Ações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach($item['faturamentos'] as $fat)
                                                                <tr>
                                                                    <td>
                                                                        @if($fat->locacao)
                                                                            <a href="{{ route('locacoes.show', $fat->locacao->id_locacao) }}" class="text-primary" target="_blank">
                                                                                #{{ $fat->locacao->numero_contrato ?? $fat->locacao->id_locacao }}
                                                                            </a>
                                                                        @else
                                                                            -
                                                                        @endif
                                                                    </td>
                                                                    <td>{{ $fat->cliente->nome ?? $fat->cliente->razao_social ?? '-' }}</td>
                                                                    <td>{{ $fat->descricao }}</td>
                                                                    <td class="text-end">R$ {{ number_format((float) $fat->valor_total, 2, ',', '.') }}</td>
                                                                    <td class="text-center">
                                                                        <div class="d-flex gap-1 justify-content-center">
                                                                            @if($statusConta !== 'pago' && $podeOperarFaturamento)
                                                                                <button type="button" 
                                                                                    class="btn btn-sm btn-outline-danger" 
                                                                                    onclick="cancelarFatura({{ $fat->id_faturamento_locacao }})"
                                                                                    title="Cancelar esta fatura">
                                                                                    <i class="ti ti-x"></i>
                                                                                </button>
                                                                            @endif
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    @else
                                        @php
                                            $faturamento = $item['faturamento'];
                                            $statusConta = $faturamento->contaReceber->status ?? null;
                                            $statusLabel = $statusConta === 'pago' ? 'Recebido' : 'Pendente';
                                            $statusClass = $statusConta === 'pago' ? 'bg-label-success' : 'bg-label-warning';
                                        @endphp
                                        <tr>
                                            <td></td>
                                            <td>{{ optional($faturamento->data_faturamento)->format('d/m/Y') }}</td>
                                            <td>
                                                @if($faturamento->locacao)
                                                    <a href="{{ route('locacoes.show', $faturamento->locacao->id_locacao) }}" class="text-primary fw-semibold">
                                                        #{{ $faturamento->locacao->numero_contrato ?? $faturamento->locacao->id_locacao }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $faturamento->cliente->nome ?? $faturamento->cliente->razao_social ?? '-' }}</td>
                                            <td>{{ $faturamento->descricao }}</td>
                                            <td class="text-end fw-semibold">R$ {{ number_format((float) $faturamento->valor_total, 2, ',', '.') }}</td>
                                            <td>{{ optional($faturamento->data_vencimento)->format('d/m/Y') ?? '-' }}</td>
                                            <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                            <td class="text-center">
                                                <div class="d-flex gap-1 justify-content-center">
                                                    @if($podeOperarFaturamento)
                                                        <button type="button" 
                                                            class="btn btn-sm btn-outline-primary" 
                                                            onclick="visualizarFatura({{ $faturamento->id_faturamento_locacao }})"
                                                            title="Ver Fatura">
                                                            <i class="ti ti-eye"></i>
                                                        </button>
                                                    @endif
                                                    @if($faturamento->contaReceber && $podeVisualizarReceber)
                                                        <a href="{{ route('financeiro.contas-a-receber.edit', $faturamento->contaReceber->id_contas) }}" 
                                                           class="btn btn-sm btn-outline-info" 
                                                           title="Ver Conta">
                                                            <i class="ti ti-cash"></i>
                                                        </a>
                                                    @endif
                                                    @if($statusConta !== 'pago' && $podeOperarFaturamento)
                                                        <button type="button" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            onclick="cancelarFatura({{ $faturamento->id_faturamento_locacao }})"
                                                            title="Cancelar faturamento">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">Nenhum faturamento encontrado para os filtros selecionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if(method_exists($faturamentos, 'links'))
                        <div class="mt-3">
                            {{ $faturamentos->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Visualização de Fatura -->
<div class="modal fade" id="modalVisualizarFatura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-file-invoice me-2"></i>
                    Visualizar Fatura
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalFaturaContent">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="ti ti-x me-1"></i>
                    Fechar
                </button>
                @if($podeOperarFaturamento)
                    <button type="button" class="btn btn-danger" id="btnBaixarPDF">
                        <i class="ti ti-file-text me-1"></i>
                        Exibir PDF
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
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

.toggle-icon {
    transition: transform 0.3s ease;
}

.toggle-icon.rotated {
    transform: rotate(90deg);
}

.lote-row {
    background-color: rgba(105, 108, 255, 0.05);
}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
@endsection

@section('page-script')
<script>
const podeOperarFaturamento = @json($podeOperarFaturamento);
let expandedAtual = null;
let modalVisualizarFatura;
let faturaAtualId = null;

document.addEventListener('DOMContentLoaded', function() {
    modalVisualizarFatura = new bootstrap.Modal(document.getElementById('modalVisualizarFatura'));
});

function toggleLote(idGrupo) {
    const row = document.getElementById(`lote-row-${idGrupo}`);
    const icon = document.getElementById(`icon-lote-${idGrupo}`);

    if (!row) return;

    if (expandedAtual && expandedAtual !== idGrupo) {
        fecharExpandedAnterior(expandedAtual);
    }

    if (row.style.display === 'none' || row.style.display === '') {
        row.style.display = 'table-row';
        setTimeout(() => row.classList.add('show'), 10);
        if (icon) {
            icon.classList.add('rotated');
        }
        expandedAtual = idGrupo;
    } else {
        row.classList.remove('show');
        setTimeout(() => row.style.display = 'none', 300);
        if (icon) {
            icon.classList.remove('rotated');
        }
        expandedAtual = null;
    }
}

function fecharExpandedAnterior(idGrupo) {
    const row = document.getElementById(`lote-row-${idGrupo}`);
    const icon = document.getElementById(`icon-lote-${idGrupo}`);

    if (row) {
        row.classList.remove('show');
        setTimeout(() => row.style.display = 'none', 300);
    }
    if (icon) {
        icon.classList.remove('rotated');
    }
}

function cancelarFatura(idFaturamento) {
    if (!podeOperarFaturamento) {
        return;
    }

    Swal.fire({
        title: 'Cancelar Faturamento?',
        text: 'Esta ação irá cancelar apenas esta fatura. Se for parte de um lote, o valor total será recalculado.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Não',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch(`/financeiro/faturamento/cancelar/${idFaturamento}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Cancelado!',
                        text: data.message,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message,
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao cancelar faturamento.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
            }
        }
    });
}

function cancelarLote(idGrupo) {
    if (!podeOperarFaturamento) {
        return;
    }

    Swal.fire({
        title: 'Cancelar Lote Completo?',
        text: 'Esta ação irá cancelar TODAS as faturas deste lote e a conta a receber.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar tudo',
        cancelButtonText: 'Não',
        customClass: {
            confirmButton: 'btn btn-danger me-3',
            cancelButton: 'btn btn-label-secondary'
        },
        buttonsStyling: false
    }).then(async (result) => {
        if (result.isConfirmed) {
            try {
                const response = await fetch(`/financeiro/faturamento/cancelar-lote/${idGrupo}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    }
                });

                const data = await response.json();

                if (data.success) {
                    Swal.fire({
                        title: 'Cancelado!',
                        text: data.message,
                        icon: 'success',
                        customClass: {
                            confirmButton: 'btn btn-success'
                        },
                        buttonsStyling: false
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message,
                        icon: 'error',
                        customClass: {
                            confirmButton: 'btn btn-danger'
                        },
                        buttonsStyling: false
                    });
                }
            } catch (error) {
                console.error('Erro:', error);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao cancelar lote.',
                    icon: 'error',
                    customClass: {
                        confirmButton: 'btn btn-danger'
                    },
                    buttonsStyling: false
                });
            }
        }
    });
}

async function visualizarFatura(idFaturamento) {
    if (!podeOperarFaturamento) {
        return;
    }

    faturaAtualId = idFaturamento;
    const content = document.getElementById('modalFaturaContent');
    
    // Mostrar loading
    content.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
    
    // Abrir modal
    modalVisualizarFatura.show();
    
    try {
        const response = await fetch(`/financeiro/faturamento/visualizar/${idFaturamento}`, {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            renderizarFatura(data.faturamento, data.empresa);
        } else {
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ti ti-alert-triangle me-2"></i>
                    ${data.message}
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro ao carregar fatura:', error);
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="ti ti-alert-triangle me-2"></i>
                Erro ao carregar fatura. Tente novamente.
            </div>
        `;
    }
}

function renderizarFatura(faturamento, empresa) {
    const content = document.getElementById('modalFaturaContent');

    const descricaoFatura = String(faturamento?.descricao || '');
    const origemFatura = String(faturamento?.origem || '');
    const isMedicaoFatura = origemFatura === 'faturamento_medicao' || descricaoFatura.toLowerCase().includes('faturamento medição');

    const matchPeriodo = descricaoFatura.match(/\((\d{2}\/\d{2}\/\d{4})\s+[aàá]\s+(\d{2}\/\d{2}\/\d{4})\)/i);
    const parsePtBrDate = (v, endOfDay = false) => {
        if (!v || typeof v !== 'string') return null;
        const [d, m, y] = v.split('/');
        if (!d || !m || !y) return null;
        const iso = `${y}-${m}-${d}T${endOfDay ? '23:59:59' : '00:00:00'}`;
        const dt = new Date(iso);
        return Number.isNaN(dt.getTime()) ? null : dt;
    };

    const periodoInicioMedicao = matchPeriodo ? parsePtBrDate(matchPeriodo[1], false) : null;
    const periodoFimMedicao = matchPeriodo ? parsePtBrDate(matchPeriodo[2], true) : null;

    const diffDiasInclusivo = (inicio, fim) => {
        const i = new Date(inicio.getFullYear(), inicio.getMonth(), inicio.getDate());
        const f = new Date(fim.getFullYear(), fim.getMonth(), fim.getDate());
        const ms = f.getTime() - i.getTime();
        return Math.max(1, Math.floor(ms / 86400000) + 1);
    };

    const formatDateBr = (v) => {
        if (!v) return '-';
        if (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(v)) {
            const [year, month, day] = v.split('-');
            return `${day}/${month}/${year}`;
        }
        const d = (v instanceof Date) ? v : new Date(v);
        if (Number.isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('pt-BR');
    };

    const parcelasConta = Array.isArray(faturamento.parcelas_conta)
        ? [...faturamento.parcelas_conta].sort((a, b) => (Number(a.numero_parcela || 0) - Number(b.numero_parcela || 0)))
        : [];
    const primeiroVencimento = parcelasConta.length > 0
        ? parcelasConta[0].data_vencimento
        : faturamento.data_vencimento;
    const temParcelamento = parcelasConta.length > 1;

    let parcelasHtml = '';
    if (parcelasConta.length > 0) {
        const linhasParcelas = parcelasConta.map(parcela => {
            const numeroParcela = Number(parcela.numero_parcela || 1);
            const totalParcelas = Number(parcela.total_parcelas || parcelasConta.length || 1);
            const valorParcela = Number(parcela.valor_total || 0);

            return `
                <tr>
                    <td class="text-center">${numeroParcela}/${totalParcelas}</td>
                    <td>${parcela.documento || '-'}</td>
                    <td class="text-center">${formatDateBr(parcela.data_vencimento)}</td>
                    <td class="text-end">R$ ${valorParcela.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `;
        }).join('');

        const totalParcelas = parcelasConta.reduce((total, parcela) => total + Number(parcela.valor_total || 0), 0);

        parcelasHtml = `
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="ti ti-calendar-event me-1"></i>
                    Vencimentos das Parcelas
                </h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width: 15%;">Parcela</th>
                            <th style="width: 45%;">Documento/Referência</th>
                            <th class="text-center" style="width: 20%;">Vencimento</th>
                            <th class="text-end" style="width: 20%;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${linhasParcelas}
                        <tr>
                            <th colspan="3" class="text-end">Total das Parcelas:</th>
                            <th class="text-end">R$ ${totalParcelas.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</th>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    }

    const periodoExibicao = (isMedicaoFatura && periodoInicioMedicao && periodoFimMedicao)
        ? `${formatDateBr(periodoInicioMedicao)} até ${formatDateBr(periodoFimMedicao)}`
        : `${faturamento.locacao?.data_inicio ? new Date(faturamento.locacao.data_inicio).toLocaleDateString('pt-BR') : '-'} até ${faturamento.locacao?.data_fim ? new Date(faturamento.locacao.data_fim).toLocaleDateString('pt-BR') : '-'}`;

    const diasPeriodoExibicao = (isMedicaoFatura && periodoInicioMedicao && periodoFimMedicao)
        ? diffDiasInclusivo(periodoInicioMedicao, periodoFimMedicao)
        : null;
    
    let produtosHtml = '';
    if (faturamento.locacao && faturamento.locacao.produtos && faturamento.locacao.produtos.length > 0) {
        produtosHtml = `
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="ti ti-package me-1"></i>
                    Produtos da Locação
                </h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%;">Qtd</th>
                            <th style="width: ${isMedicaoFatura ? '38%' : '50%'};">Produto</th>
                            ${isMedicaoFatura ? '<th class="text-center" style="width: 12%;">Dias</th>' : ''}
                            <th class="text-end" style="width: 20%;">Valor Unit.</th>
                            <th class="text-end" style="width: 20%;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        faturamento.locacao.produtos.forEach(item => {
            const quantidade = Number(item.quantidade || 1);
            const valorUnitario = Number(item.preco_unitario || 0);
            let diasItem = null;
            let subtotal = Number(item.preco_total || (quantidade * valorUnitario));

            if (isMedicaoFatura && periodoInicioMedicao && periodoFimMedicao && item.data_inicio) {
                const inicioItem = new Date(`${item.data_inicio}T00:00:00`);
                const retornado = Number(item.estoque_status || 0) === 2 || ![null, '', 'pendente'].includes(item.status_retorno);
                const fimItemBase = (retornado && item.data_fim)
                    ? new Date(`${item.data_fim}T23:59:59`)
                    : new Date(periodoFimMedicao);

                const inicioCalc = new Date(Math.max(inicioItem.getTime(), periodoInicioMedicao.getTime()));
                const fimCalc = new Date(Math.min(fimItemBase.getTime(), periodoFimMedicao.getTime()));

                if (!Number.isNaN(inicioCalc.getTime()) && !Number.isNaN(fimCalc.getTime()) && fimCalc >= inicioCalc) {
                    diasItem = diffDiasInclusivo(inicioCalc, fimCalc);
                    subtotal = quantidade * valorUnitario * diasItem;
                } else {
                    subtotal = 0;
                }
            }

            produtosHtml += `
                <tr>
                    <td class="text-center">${quantidade}</td>
                    <td>${item.produto?.nome || item.produto?.descricao || 'Produto'}</td>
                    ${isMedicaoFatura ? `<td class="text-center">${diasItem ?? '-'}</td>` : ''}
                    <td class="text-end">R$ ${parseFloat(valorUnitario).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td class="text-end">R$ ${subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `;
        });
        
        produtosHtml += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    let servicosHtml = '';
    if (faturamento.locacao && faturamento.locacao.servicos && faturamento.locacao.servicos.length > 0) {
        servicosHtml = `
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="ti ti-tool me-1"></i>
                    Serviços da Locação
                </h6>
                <table class="table table-sm table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%;">Qtd</th>
                            <th style="width: 50%;">Serviço</th>
                            <th class="text-end" style="width: 20%;">Valor Unit.</th>
                            <th class="text-end" style="width: 20%;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        
        faturamento.locacao.servicos.forEach(servico => {
            const quantidade = servico.quantidade || 1;
            const valor = servico.valor || 0;
            const subtotal = quantidade * valor;
            servicosHtml += `
                <tr>
                    <td class="text-center">${quantidade}</td>
                    <td>${servico.descricao || 'Serviço'}</td>
                    <td class="text-end">R$ ${parseFloat(valor).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    <td class="text-end">R$ ${subtotal.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `;
        });
        
        servicosHtml += `
                    </tbody>
                </table>
            </div>
        `;
    }
    
    const statusLabel = faturamento.conta_receber?.status === 'pago' ? 'PAGO' : 'PENDENTE';
    const statusClass = faturamento.conta_receber?.status === 'pago' ? 'badge-success' : 'badge-warning';
    
    content.innerHTML = `
        <div class="container-fluid" id="faturaImpressao">
            <!-- Cabeçalho -->
            <div class="row mb-4 pb-3 border-bottom">
                <div class="col-md-6">
                    <h4 class="mb-2">${empresa?.nome_fantasia || empresa?.razao_social || 'Empresa'}</h4>
                    <p class="mb-0 text-muted small">
                        ${empresa?.endereco || ''}<br>
                        ${empresa?.telefone || ''} ${empresa?.email ? '| ' + empresa.email : ''}
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <h3 class="mb-2">FATURA</h3>
                    <p class="mb-0">
                        <strong>Nº:</strong> ${String(faturamento.id_faturamento_locacao).padStart(6, '0')}<br>
                        <strong>Data:</strong> ${new Date(faturamento.data_faturamento).toLocaleDateString('pt-BR')}
                    </p>
                </div>
            </div>
            
            <!-- Dados do Cliente -->
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="ti ti-user me-1"></i>
                    Dados do Cliente
                </h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Cliente:</strong> ${faturamento.locacao?.cliente?.nome || faturamento.locacao?.cliente?.razao_social || '-'}</p>
                        ${faturamento.locacao?.cliente?.cpf || faturamento.locacao?.cliente?.cnpj ? `<p class="mb-1"><strong>CPF/CNPJ:</strong> ${faturamento.locacao?.cliente?.cpf || faturamento.locacao?.cliente?.cnpj}</p>` : ''}
                    </div>
                    <div class="col-md-6">
                        ${faturamento.locacao?.cliente?.telefone ? `<p class="mb-1"><strong>Telefone:</strong> ${faturamento.locacao?.cliente?.telefone}</p>` : ''}
                        ${faturamento.locacao?.cliente?.endereco ? `<p class="mb-1"><strong>Endereço:</strong> ${faturamento.locacao?.cliente?.endereco}</p>` : ''}
                    </div>
                </div>
            </div>
            
            <!-- Dados da Locação -->
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="ti ti-calendar me-1"></i>
                    Dados da Locação
                </h6>
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Contrato:</strong> #${faturamento.locacao?.numero_contrato || faturamento.locacao?.id_locacao || '-'}</p>
                    </div>
                    <div class="col-md-8">
                        <p class="mb-1"><strong>Período:</strong> ${periodoExibicao}</p>
                    </div>
                </div>
                ${isMedicaoFatura ? `<p class="mb-1"><strong>Tipo de Fatura:</strong> Medição ${diasPeriodoExibicao ? `(${diasPeriodoExibicao} dia(s))` : ''}</p>` : ''}
                ${faturamento.locacao?.local_evento ? `<p class="mb-1"><strong>Local do Evento:</strong> ${faturamento.locacao.local_evento}</p>` : ''}
            </div>
            
            ${produtosHtml}
            ${servicosHtml}
            
            <!-- Totais -->
            <div class="row justify-content-end mb-4">
                <div class="col-md-5">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th class="text-end">Valor Total:</th>
                                <td class="text-end"><h5 class="mb-0">R$ ${parseFloat(faturamento.valor_total).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</h5></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Informações de Pagamento -->
            <div class="mb-4">
                <h6 class="border-bottom pb-2 mb-3">
                    <i class="ti ti-credit-card me-1"></i>
                    Informações de Pagamento
                </h6>
                <div class="row">
                    <div class="col-md-4">
                        <p class="mb-1"><strong>${temParcelamento ? '1º Vencimento' : 'Vencimento'}:</strong> ${formatDateBr(primeiroVencimento)}</p>
                    </div>
                    <div class="col-md-4">
                        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-label-${statusClass}">${statusLabel}</span></p>
                    </div>
                </div>
            </div>

            ${parcelasHtml}
            
            ${faturamento.observacoes ? `
            <div class="alert alert-info mb-0">
                <strong>Observações:</strong><br>
                ${faturamento.observacoes}
            </div>
            ` : ''}
        </div>
    `;
    
    // Atualizar botão de download
    const btnBaixarPDF = document.getElementById('btnBaixarPDF');
    if (btnBaixarPDF) {
        btnBaixarPDF.onclick = function() {
            window.open(`/financeiro/faturamento/pdf/${faturamento.id_faturamento_locacao}`, '_blank');
        };
    }
}
</script>
@endsection
