@extends('layouts.layoutMaster')

@section('title', 'Orçamentos de Locação')

@section('page-style')
<style>
    .cards-util-orcamentos .card,
    .cards-resumo-orcamentos .card {
        border: 1px solid #e9edf3;
        border-radius: .55rem;
    }

    .cards-util-orcamentos .topo {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .75rem;
    }

    .cards-util-orcamentos .label,
    .cards-resumo-orcamentos .label {
        font-size: .78rem;
        font-weight: 600;
        color: #697a8d;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .cards-util-orcamentos .valor,
    .cards-resumo-orcamentos .valor {
        font-size: 1rem;
        font-weight: 700;
        color: #566a7f;
    }

    .cards-util-orcamentos .meta,
    .cards-resumo-orcamentos .meta {
        font-size: .78rem;
        color: #8d95a5;
    }

    .cards-util-orcamentos .icone {
        width: 2.4rem;
        height: 2.4rem;
        border-radius: .45rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .cards-util-orcamentos .icone.icone-primary {
        color: #696cff;
        background: rgba(105, 108, 255, .16);
    }

    .cards-util-orcamentos .icone.icone-success {
        color: #2a8a34;
        background: rgba(113, 221, 55, .20);
    }

    .cards-util-orcamentos .icone.icone-info {
        color: #03a9c6;
        background: rgba(3, 195, 236, .18);
    }

    .cards-util-orcamentos .icone.icone-warning {
        color: #c36e00;
        background: rgba(255, 171, 0, .20);
    }

    .cards-resumo-orcamentos .card-total {
        border-color: rgba(113, 221, 55, .35);
        background: rgba(113, 221, 55, .06);
    }

    .orcamentos-table td.col-codigo,
    .orcamentos-table td.col-periodo,
    .orcamentos-table td.col-dias,
    .orcamentos-table td.col-valor,
    .orcamentos-table td.col-status,
    .orcamentos-table td.col-editar,
    .orcamentos-table td.col-acoes {
        white-space: nowrap;
        vertical-align: middle;
    }

    .orcamentos-table td.col-cliente {
        min-width: 260px;
    }

    .painel-acoes-linha {
        background: #f8f9fa;
        border: 1px solid #ebeef0;
        border-radius: .5rem;
        padding: .75rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .75rem;
        flex-wrap: wrap;
    }

    .painel-acoes-info {
        min-width: 280px;
    }

    .painel-acoes-info .titulo {
        font-weight: 600;
        color: #566a7f;
        margin-bottom: .15rem;
    }

    .painel-acoes-info .detalhes {
        color: #8a8d95;
        font-size: .82rem;
    }

    .painel-acoes-botoes {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .35rem;
        flex-wrap: wrap;
    }

    .btn-icon-acao {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    @media (max-width: 767.98px) {
        .orcamentos-nav-principal,
        .orcamentos-top-actions {
            width: 100%;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            padding-bottom: .2rem;
        }

        .orcamentos-nav-principal .btn,
        .orcamentos-top-actions .btn {
            flex: 0 0 auto;
            white-space: nowrap;
        }

        .painel-acoes-linha {
            align-items: flex-start;
        }

        .painel-acoes-botoes {
            justify-content: flex-start;
        }
    }

    html.dark-style .cards-util-orcamentos .card,
    html.dark-style .cards-resumo-orcamentos .card,
    html.dark-style .painel-acoes-linha {
        border-color: #444b6e;
    }

    html.dark-style .painel-acoes-linha {
        background: #2b3046;
    }

    html.dark-style .cards-util-orcamentos .label,
    html.dark-style .cards-util-orcamentos .valor,
    html.dark-style .cards-util-orcamentos .meta,
    html.dark-style .cards-resumo-orcamentos .label,
    html.dark-style .cards-resumo-orcamentos .valor,
    html.dark-style .cards-resumo-orcamentos .meta,
    html.dark-style .painel-acoes-info .titulo,
    html.dark-style .painel-acoes-info .detalhes {
        color: #d8deff;
    }

    html.dark-style .cards-resumo-orcamentos .card-total {
        border-color: rgba(113, 221, 55, .35);
        background: rgba(113, 221, 55, .14);
    }
</style>
@endsection

@section('content')
@php
    $podeCriarLocacao = \Perm::pode(auth()->user(), 'locacoes.criar');
    $podeEditarLocacao = \Perm::pode(auth()->user(), 'locacoes.editar');
    $podeContratoPdfLocacao = \Perm::pode(auth()->user(), 'locacoes.contrato-pdf');
    $podeAssinaturaDigitalLocacao = \Perm::pode(auth()->user(), 'locacoes.assinatura-digital');
    $podeAlterarStatusLocacao = \Perm::pode(auth()->user(), 'locacoes.alterar-status');
@endphp
<div class="container-xxl flex-grow-1">
    <div class="row g-3 mb-3 cards-util-orcamentos">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">{{ (int) ($resumoOrcamentos['quantidade_total'] ?? 0) }}</div>
                            <div class="meta">Registros conforme filtros aplicados</div>
                        </div>
                        <span class="icone icone-primary"><i class="ti ti-file-description"></i></span>
                    </div>
                    <div class="label">Total de Orçamentos</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">{{ (int) ($resumoOrcamentos['quantidade_mes_atual'] ?? 0) }}</div>
                            <div class="meta">Comparativo de volume atual</div>
                        </div>
                        <span class="icone icone-success"><i class="ti ti-calendar-stats"></i></span>
                    </div>
                    <div class="label">Criados no Mês</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">{{ (int) ($resumoOrcamentos['proximos_sete_dias'] ?? 0) }}</div>
                            <div class="meta">Prioridade de conversão</div>
                        </div>
                        <span class="icone icone-info"><i class="ti ti-calendar-time"></i></span>
                    </div>
                    <div class="label">Início em até 7 dias</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">{{ (int) ($resumoOrcamentos['inicio_atrasado'] ?? 0) }}</div>
                            <div class="meta">Vale revisar prazo ou status</div>
                        </div>
                        <span class="icone icone-warning"><i class="ti ti-alert-triangle"></i></span>
                    </div>
                    <div class="label">Com início passado</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="d-flex gap-2 orcamentos-nav-principal">
                <a href="{{ route('locacoes.contratos') }}" class="btn btn-outline-secondary">Contratos</a>
                <a href="{{ route('locacoes.orcamentos') }}" class="btn btn-primary">Orçamentos</a>
                <a href="{{ route('locacoes.medicoes') }}" class="btn btn-outline-secondary">Medições</a>
            </div>
            <div class="d-flex gap-2 orcamentos-top-actions">
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalFiltrosOrcamentos">
                    <i class="ti ti-filter me-1"></i>Filtros
                </button>
                @if($podeCriarLocacao)
                    <a href="{{ route('locacoes.create', ['origem' => 'orcamentos', 'status' => 'orcamento']) }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Novo Orçamento</a>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body pb-1">
            <div class="row g-3 cards-resumo-orcamentos">
                <div class="col-12 col-md-4">
                    <div class="card h-100 card-total">
                        <div class="card-body py-2 px-3">
                            <div class="label mb-1">Valor Total em Orçamentos</div>
                            <div class="valor">R$ {{ number_format((float) ($resumoOrcamentos['valor_total'] ?? 0), 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body py-2 px-3">
                            <div class="label mb-1">Ticket Médio</div>
                            <div class="valor">R$ {{ number_format((float) ($resumoOrcamentos['ticket_medio'] ?? 0), 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card h-100">
                        <div class="card-body py-2 px-3">
                            <div class="label mb-1">Conversão Potencial</div>
                            @php
                                $baseConversao = max(1, (int) ($resumoOrcamentos['quantidade_total'] ?? 0));
                                $valorConversao = ((int) ($resumoOrcamentos['proximos_sete_dias'] ?? 0) / $baseConversao) * 100;
                            @endphp
                            <div class="valor">{{ number_format($valorConversao, 2, ',', '.') }}%</div>
                            <div class="meta">Baseado nos inícios em até 7 dias</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover orcamentos-table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 85px">Ações</th>
                            <th style="width: 70px">Editar</th>
                            <th style="width: 95px">Código</th>
                            <th style="min-width: 260px">Cliente</th>
                            <th style="width: 165px">Data início</th>
                            <th style="width: 110px">Qde período</th>
                            <th style="width: 165px">Data fim</th>
                            <th style="width: 140px">Valor total</th>
                            <th style="width: 120px">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locacoes as $locacao)
                            @php
                                $qtdPeriodo = (int) ($locacao->quantidade_dias ?? 0);
                                $unidadePeriodo = 'dia(s)';
                                $dataInicioPeriodo = optional($locacao->data_inicio)->format('Y-m-d');
                                $dataFimPeriodo = optional($locacao->data_fim)->format('Y-m-d');

                                if (
                                    $dataInicioPeriodo
                                    && $dataFimPeriodo
                                    && $dataInicioPeriodo === $dataFimPeriodo
                                    && !empty($locacao->hora_inicio)
                                    && !empty($locacao->hora_fim)
                                ) {
                                    $inicioHora = \Carbon\Carbon::parse($dataInicioPeriodo . ' ' . $locacao->hora_inicio);
                                    $fimHora = \Carbon\Carbon::parse($dataFimPeriodo . ' ' . $locacao->hora_fim);
                                    if ($fimHora->gte($inicioHora)) {
                                        $qtdPeriodo = max(1, (int) ceil($inicioHora->diffInMinutes($fimHora) / 60));
                                        $unidadePeriodo = 'hora(s)';
                                    }
                                }
                            @endphp
                            <tr>
                                <td class="col-acoes">
                                    <button
                                        class="btn btn-sm btn-outline-secondary btn-toggle-acoes"
                                        type="button"
                                        data-target="#acoes-orcamento-{{ $locacao->id_locacao }}"
                                    >
                                        Ações
                                    </button>
                                </td>
                                <td class="col-editar">
                                    @if($podeEditarLocacao)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('locacoes.edit', $locacao->id_locacao) }}"><i class="ti ti-pencil"></i></a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="col-codigo"><strong>{{ $locacao->codigo_display }}</strong></td>
                                <td class="col-cliente">{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                                <td class="col-periodo">{{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio,0,5) : '' }}</td>
                                <td class="col-dias">{{ $qtdPeriodo }} {{ $unidadePeriodo }}</td>
                                <td class="col-periodo">{{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ? substr($locacao->hora_fim,0,5) : '' }}</td>
                                <td class="col-valor"><strong>R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}</strong></td>
                                <td class="col-status"><span class="badge bg-label-secondary">Orçamento</span></td>
                            </tr>
                            <tr id="acoes-orcamento-{{ $locacao->id_locacao }}" class="d-none linha-acoes-orcamento">
                                <td colspan="9">
                                    <div class="painel-acoes-linha">
                                        <div class="painel-acoes-info">
                                            <div class="titulo">Orçamento {{ $locacao->codigo_display }}</div>
                                            <div class="detalhes">
                                                {{ $locacao->cliente->nome ?? 'N/A' }}
                                                • {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio,0,5) : '' }}
                                                até {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ? substr($locacao->hora_fim,0,5) : '' }}
                                            </div>
                                        </div>

                                        <div class="painel-acoes-botoes">
                                            @if($podeContratoPdfLocacao)
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=orcamento" data-bs-toggle="tooltip" title="Imprimir Orçamento"><i class="ti ti-printer"></i></a>
                                            @endif
                                            @if($podeAssinaturaDigitalLocacao)
                                                <a class="btn btn-sm btn-outline-warning btn-icon-acao" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}" data-bs-toggle="tooltip" title="Enviar para Assinatura Digital"><i class="ti ti-signature"></i></a>
                                            @endif
                                            @if($podeAlterarStatusLocacao)
                                                <button type="button" class="btn btn-sm btn-outline-success btn-icon-acao btn-alterar-status" data-id="{{ $locacao->id_locacao }}" data-status="aprovado" data-bs-toggle="tooltip" title="Aprovar Locação"><i class="ti ti-circle-check"></i></button>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Nenhum orçamento encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($locacoes, 'links') && $locacoes->total() > 0)
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">Mostrando {{ $locacoes->firstItem() }} até {{ $locacoes->lastItem() }} de {{ $locacoes->total() }} registros</div>
                    {{ $locacoes->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modalFiltrosOrcamentos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('locacoes.orcamentos') }}">
                <div class="modal-header">
                    <h5 class="modal-title">Filtros de Orçamentos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Cliente</label>
                            <select name="id_cliente" class="form-select">
                                <option value="">Todos</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->id_clientes }}" {{ ((string)($filters['id_cliente'] ?? '') === (string)$cliente->id_clientes) ? 'selected' : '' }}>{{ $cliente->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Funcionário</label>
                            <select name="id_usuario" class="form-select">
                                <option value="">Todos</option>
                                @foreach(($usuarios ?? collect()) as $usuario)
                                    <option value="{{ $usuario->id_usuario }}" {{ ((string)($filters['id_usuario'] ?? '') === (string)$usuario->id_usuario) ? 'selected' : '' }}>{{ $usuario->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Código da Locação</label>
                            <input type="text" name="codigo" class="form-control" value="{{ $filters['codigo'] ?? '' }}" placeholder="Ex: 038">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nome do Cliente</label>
                            <input type="text" name="busca" class="form-control" value="{{ $filters['busca'] ?? '' }}" placeholder="Buscar por cliente">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data início</label>
                            <input type="date" name="data_inicio" class="form-control" value="{{ $filters['data_inicio'] ?? '' }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Data fim</label>
                            <input type="date" name="data_fim" class="form-control" value="{{ $filters['data_fim'] ?? '' }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('locacoes.orcamentos') }}" class="btn btn-label-secondary">Limpar</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>Aplicar Filtros</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script>
$(function () {
    @if (session('success'))
    Swal.fire({
        icon: 'success',
        title: 'Sucesso',
        text: @json(session('success')),
        timer: 1800,
        showConfirmButton: false
    });
    @endif

    @if (session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: @json(session('error'))
    });
    @endif

    const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    $(document).on('click', '.btn-toggle-acoes', function () {
        const target = $(this).data('target');
        if (!target) return;

        $('.linha-acoes-orcamento').not(target).addClass('d-none');
        $(target).toggleClass('d-none');
    });

    $(document).on('click', '.btn-alterar-status', function() {
        var id = $(this).data('id');
        var status = $(this).data('status');

        Swal.fire({
            title: 'Aprovar locação?',
            text: 'O orçamento será convertido para contrato aprovado.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim, aprovar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: `{{ url('locacoes') }}/${id}/status`,
                type: 'PATCH',
                data: { _token: '{{ csrf_token() }}', status: status },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', response.message, 'success').then(() => location.reload());
                    }
                },
                error: function(xhr) {
                    Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao alterar status.', 'error');
                }
            });
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
