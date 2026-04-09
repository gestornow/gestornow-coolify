@extends('layouts.layoutMaster')

@section('title', 'Contratos em Medição')

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-style')
<style>
    .locacoes-abas-status {
        gap: .5rem;
        border-bottom: 0;
        width: 100%;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        padding-bottom: .2rem;
    }

    .locacoes-abas-status .nav-item {
        flex: 1 0 auto;
        min-width: 120px;
    }

    .locacoes-abas-status .nav-link {
        border: 1px solid #d9dee3;
        border-radius: .5rem;
        color: #566a7f;
        font-weight: 600;
        background: #fff;
        padding: .45rem .85rem;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .35rem;
    }

    .locacoes-abas-status .nav-link.active {
        background: rgba(105, 108, 255, .12);
        border-color: rgba(105, 108, 255, .28);
        color: #696cff;
    }

    .cards-util-medicoes .card {
        border: 1px solid #e9edf3;
        border-radius: .55rem;
    }

    .cards-util-medicoes .topo {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .75rem;
    }

    .cards-util-medicoes .titulo {
        font-size: .78rem;
        font-weight: 600;
        color: #697a8d;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .cards-util-medicoes .valor {
        font-size: 1rem;
        font-weight: 700;
        color: #566a7f;
    }

    .cards-util-medicoes .meta {
        font-size: .78rem;
        color: #8d95a5;
    }

    .cards-util-medicoes .icone {
        width: 2.4rem;
        height: 2.4rem;
        border-radius: .45rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .cards-util-medicoes .icone.icone-primary {
        color: #696cff;
        background: rgba(105, 108, 255, .16);
    }

    .cards-util-medicoes .icone.icone-success {
        color: #2a8a34;
        background: rgba(113, 221, 55, .20);
    }

    .cards-util-medicoes .icone.icone-warning {
        color: #c36e00;
        background: rgba(255, 171, 0, .20);
    }

    .cards-util-medicoes .icone.icone-info {
        color: #03a9c6;
        background: rgba(3, 195, 236, .18);
    }
</style>
@endsection

@section('content')
@php
    $podeCriarLocacao = \Perm::pode(auth()->user(), 'locacoes.criar');
    $podeEditarLocacao = \Perm::pode(auth()->user(), 'locacoes.editar');
    $podeContratoPdfLocacao = \Perm::pode(auth()->user(), 'locacoes.contrato-pdf');
    $podeAssinaturaDigitalLocacao = \Perm::pode(auth()->user(), 'locacoes.assinatura-digital');
    $podeMedicaoLocacao = \Perm::pode(auth()->user(), 'locacoes.medicao');
@endphp
<div class="container-xxl flex-grow-1">
    @php
        $abaMedicao = $abaMedicao ?? 'ativos';
        $abasContagemMedicoes = $abasContagemMedicoes ?? ['ativos' => 0, 'encerrados' => 0, 'todos' => 0];
        $abasValoresMedicoes = $abasValoresMedicoes ?? ['ativos' => 0, 'encerrados' => 0, 'todos' => 0];

        $qtdTotalMedicao = (int) ($abasContagemMedicoes['todos'] ?? 0);
        $qtdAtivosMedicao = (int) ($abasContagemMedicoes['ativos'] ?? 0);
        $qtdEncerradosMedicao = (int) ($abasContagemMedicoes['encerrados'] ?? 0);

        $valorTotalMedicao = (float) ($abasValoresMedicoes['todos'] ?? 0);
        $valorAtivosMedicao = (float) ($abasValoresMedicoes['ativos'] ?? 0);
        $valorEncerradosMedicao = (float) ($abasValoresMedicoes['encerrados'] ?? 0);
    @endphp

    <div class="row g-3 mb-3 cards-util-medicoes">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">R$ {{ number_format($valorTotalMedicao, 2, ',', '.') }}</div>
                            <div class="meta">{{ $qtdTotalMedicao }} contratos de medição</div>
                        </div>
                        <span class="icone icone-primary"><i class="ti ti-wallet"></i></span>
                    </div>
                    <div class="titulo">Carteira Medição</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">R$ {{ number_format($valorAtivosMedicao, 2, ',', '.') }}</div>
                            <div class="meta">{{ $qtdAtivosMedicao }} ativos</div>
                        </div>
                        <span class="icone icone-success"><i class="ti ti-activity"></i></span>
                    </div>
                    <div class="titulo">Operação Ativa</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">{{ number_format((int) ($resumoMedicoes['itens_ativos_total'] ?? 0), 0, ',', '.') }}</div>
                            <div class="meta">Itens ativos em medição</div>
                        </div>
                        <span class="icone icone-warning"><i class="ti ti-package"></i></span>
                    </div>
                    <div class="titulo">Itens em Campo</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">R$ {{ number_format($valorEncerradosMedicao, 2, ',', '.') }}</div>
                            <div class="meta">{{ $qtdEncerradosMedicao }} encerrados</div>
                        </div>
                        <span class="icone icone-info"><i class="ti ti-archive"></i></span>
                    </div>
                    <div class="titulo">Encerrados</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="d-flex gap-2">
                <a href="{{ route('locacoes.contratos') }}" class="btn btn-outline-secondary">Contratos</a>
                <a href="{{ route('locacoes.orcamentos') }}" class="btn btn-outline-secondary">Orçamentos</a>
                <a href="{{ route('locacoes.medicoes') }}" class="btn btn-primary">Medições</a>
            </div>
            <div>
                @if($podeCriarLocacao)
                    <a href="{{ route('locacoes.create', ['origem' => 'medicoes', 'status' => 'medicao']) }}" class="btn btn-primary">
                        <i class="ti ti-plus me-1"></i>Novo Contrato de Medição
                    </a>
                @endif
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Cliente</label>
                    <select name="id_cliente" class="form-select">
                        <option value="">Todos</option>
                        @foreach($clientes as $cliente)
                            <option value="{{ $cliente->id_clientes }}" {{ (string)($filters['id_cliente'] ?? '') === (string)$cliente->id_clientes ? 'selected' : '' }}>{{ $cliente->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Busca</label>
                    <input type="text" class="form-control" name="busca" value="{{ $filters['busca'] ?? '' }}" placeholder="Código/cliente">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mês relatório</label>
                    <input type="month" class="form-control" name="mes_movimento" value="{{ $mesMovimento }}">
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button class="btn btn-primary w-100" type="submit"><i class="ti ti-filter me-1"></i>Filtrar</button>
                </div>
            </form>

            <div class="mt-3" id="filtroClienteMedicaoInfoCard">
                <div class="border rounded p-3 bg-light d-none" id="filtroClienteMedicaoInfoContent">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div id="filtroClienteMedicaoFoto" class="flex-shrink-0">
                            <div class="avatar avatar-md">
                                <span class="avatar-initial rounded bg-label-primary">
                                    <i class="ti ti-user"></i>
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="fw-semibold" id="filtroClienteMedicaoNome">-</div>
                            <small class="text-muted" id="filtroClienteMedicaoDocumento">-</small>
                        </div>
                    </div>
                    <div class="small text-muted" id="filtroClienteMedicaoContato">-</div>
                    <div class="small text-muted" id="filtroClienteMedicaoEndereco">-</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body pb-1">
            <div class="row g-3 mb-1">
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small text-uppercase fw-semibold">Contratos em medição</div>
                                    <h4 class="mb-0">{{ number_format((int) ($resumoMedicoes['total_contratos'] ?? 0), 0, ',', '.') }}</h4>
                                </div>
                                <span class="badge bg-label-primary"><i class="ti ti-file-description"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small text-uppercase fw-semibold">Itens ativos</div>
                                    <h4 class="mb-0">{{ number_format((int) ($resumoMedicoes['itens_ativos_total'] ?? 0), 0, ',', '.') }}</h4>
                                </div>
                                <span class="badge bg-label-info"><i class="ti ti-package"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small text-uppercase fw-semibold">Valor previsto até hoje</div>
                                    <h4 class="mb-0">R$ {{ number_format((float) ($resumoMedicoes['valor_previsto_total'] ?? $resumoMedicoes['valor_aberto_total'] ?? 0), 2, ',', '.') }}</h4>
                                </div>
                                <span class="badge bg-label-warning"><i class="ti ti-currency-real"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="text-muted small text-uppercase fw-semibold">Faturados no mês</div>
                                    <h4 class="mb-0">{{ number_format((int) ($resumoMedicoes['faturados_mes'] ?? 0), 0, ',', '.') }}</h4>
                                </div>
                                <span class="badge bg-label-success"><i class="ti ti-file-invoice"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-header pb-0">
            <ul class="nav nav-pills nav-fill locacoes-abas-status">
                <li class="nav-item"><a class="nav-link {{ $abaMedicao === 'ativos' ? 'active' : '' }}" href="{{ route('locacoes.medicoes', array_merge(request()->except('page'), ['aba' => 'ativos'])) }}">Ativos <span class="badge bg-label-success ms-1">{{ $abasContagemMedicoes['ativos'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $abaMedicao === 'encerrados' ? 'active' : '' }}" href="{{ route('locacoes.medicoes', array_merge(request()->except('page'), ['aba' => 'encerrados'])) }}">Encerrados <span class="badge bg-label-info ms-1">{{ $abasContagemMedicoes['encerrados'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $abaMedicao === 'todos' ? 'active' : '' }}" href="{{ route('locacoes.medicoes', array_merge(request()->except('page'), ['aba' => 'todos'])) }}">Todos <span class="badge bg-label-secondary ms-1">{{ $abasContagemMedicoes['todos'] ?? 0 }}</span></a></li>
            </ul>
        </div>

        <div class="card-body pt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 52px;"></th>
                            <th style="width: 52px;"></th>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Início</th>
                            <th>Itens ativos</th>
                            <th>Últ. faturamento</th>
                            <th>Previsto até hoje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locacoes as $locacao)
                            <tr>
                                <td>
                                    <button
                                        class="btn btn-sm btn-outline-secondary btn-toggle-acoes"
                                        type="button"
                                        data-target="#acoes-locacao-{{ $locacao->id_locacao }}"
                                        title="Abrir ações"
                                        aria-label="Abrir ações"
                                    >
                                        <i class="ti ti-chevron-down"></i>
                                    </button>
                                </td>
                                <td>
                                    @if($podeEditarLocacao)
                                        <a href="{{ route('locacoes.edit', $locacao->id_locacao) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="ti ti-pencil"></i></a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><strong>{{ $locacao->codigo_display }}</strong></td>
                                <td>{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                                <td>{{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio,0,5) : '' }}</td>
                                <td><span class="badge bg-label-primary">{{ (int) ($locacao->itens_ativos_count ?? 0) }}</span></td>
                                <td>{{ $locacao->ultimo_faturamento ? $locacao->ultimo_faturamento->format('d/m/Y') : 'Nunca' }}</td>
                                <td>
                                    <strong class="{{ ((bool) ($locacao->limite_medicao_ultrapassado ?? false)) ? 'text-danger' : '' }}">
                                        R$ {{ number_format((float) ($locacao->valor_previsto_hoje ?? $locacao->valor_aberto_medicao ?? 0), 2, ',', '.') }}
                                    </strong>
                                    <div class="small text-muted">Previsto até hoje</div>
                                    @if((float) ($locacao->valor_limite_medicao ?? 0) > 0)
                                        <div class="small {{ ((float) ($locacao->valor_restante_limite_medicao ?? 0) <= 0) ? 'text-danger fw-semibold' : 'text-muted' }}">
                                            Limite disponível: R$ {{ number_format((float) ($locacao->valor_restante_limite_medicao ?? 0), 2, ',', '.') }}
                                        </div>
                                        @if((bool) ($locacao->limite_medicao_ultrapassado ?? false))
                                            <div class="small text-danger fw-semibold">
                                                Ultrapassou o limite em R$ {{ number_format((float) ($locacao->valor_excedente_limite_medicao ?? 0), 2, ',', '.') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-muted">Sem limite</span>
                                    @endif
                                    @if(($locacao->status ?? '') === 'medicao_finalizada')
                                        <div class="small"><span class="badge bg-label-success">Medição finalizada</span></div>
                                    @endif
                                </td>
                            </tr>
                            <tr id="acoes-locacao-{{ $locacao->id_locacao }}" class="d-none linha-acoes-locacao">
                                <td colspan="8">
                                    <div class="p-2 rounded border bg-lighter d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <div>
                                            <div class="fw-semibold">Contrato {{ $locacao->codigo_display }}</div>
                                            <small class="text-muted">{{ $locacao->cliente->nome ?? 'N/A' }} • Início {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio, 0, 5) : '' }} • Previsto até hoje R$ {{ number_format((float) ($locacao->valor_previsto_hoje ?? $locacao->valor_aberto_medicao ?? 0), 2, ',', '.') }} • Aberto atual R$ {{ number_format((float) ($locacao->valor_aberto_medicao ?? 0), 2, ',', '.') }}@if((bool) ($locacao->limite_medicao_ultrapassado ?? false)) • Ultrapassou limite em R$ {{ number_format((float) ($locacao->valor_excedente_limite_medicao ?? 0), 2, ',', '.') }}@endif</small>
                                            @if($locacao->assinaturaDigital)
                                                <div class="mt-1">
                                                    <span class="badge bg-label-{{ ($locacao->assinaturaDigital->status ?? '') === 'assinado' ? 'success' : 'warning' }}">
                                                        {{ ($locacao->assinaturaDigital->status ?? '') === 'assinado' ? 'Contrato assinado' : 'Contrato pendente de assinatura' }}
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="d-flex flex-wrap gap-1">
                                            @php
                                                $modelosMedicao = $modelosContratoMedicao ?? collect();
                                            @endphp
                                            @if($modelosMedicao->count() > 0)
                                                @if($podeContratoPdfLocacao || $podeAssinaturaDigitalLocacao)
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="ti ti-printer me-1"></i>Imprimir
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        @foreach($modelosMedicao as $modelo)
                                                            <li>
                                                                @if($podeContratoPdfLocacao)
                                                                    <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=medicao&id_modelo={{ $modelo->id_modelo }}">
                                                                        {{ $modelo->nome }}
                                                                        @if($modelo->padrao)
                                                                            <span class="badge bg-label-primary ms-1">Padrão</span>
                                                                        @endif
                                                                    </a>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                        @php
                                                            $modeloAssinaturaMedicao = $modelosMedicao->first();
                                                        @endphp
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            @if($podeAssinaturaDigitalLocacao)
                                                                <a class="dropdown-item" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}?tipo=medicao{{ $modeloAssinaturaMedicao ? ('&id_modelo=' . $modeloAssinaturaMedicao->id_modelo) : '' }}">
                                                                    Enviar pra assinatura
                                                                </a>
                                                            @endif
                                                        </li>
                                                    </ul>
                                                </div>
                                                @endif
                                            @else
                                                @if($podeContratoPdfLocacao || $podeAssinaturaDigitalLocacao)
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="ti ti-printer me-1"></i>Imprimir
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            @if($podeContratoPdfLocacao)
                                                                <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=medicao">
                                                                    Imprimir contrato
                                                                </a>
                                                            @endif
                                                        </li>
                                                        <li>
                                                            @if($podeAssinaturaDigitalLocacao)
                                                                <a class="dropdown-item" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}?tipo=medicao">
                                                                    Enviar pra assinatura
                                                                </a>
                                                            @endif
                                                        </li>
                                                    </ul>
                                                </div>
                                                @endif
                                            @endif

                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary btn-abrir-relatorio-medicao"
                                                data-id="{{ $locacao->id_locacao }}"
                                                data-codigo="{{ $locacao->codigo_display }}"
                                            >
                                                <i class="ti ti-chart-bar me-1"></i>Contrato
                                            </button>

                                            @if(($locacao->status ?? '') === 'medicao' && $podeMedicaoLocacao)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary btn-abrir-movimentacao-medicao"
                                                data-id="{{ $locacao->id_locacao }}"
                                                data-codigo="{{ $locacao->codigo_display }}"
                                                data-limite-medicao="{{ (float) ($locacao->valor_limite_medicao ?? 0) }}"
                                                data-saldo-limite-medicao="{{ (float) ($locacao->valor_restante_limite_medicao ?? 0) }}"
                                                data-limite-atingido="{{ ((bool) ($locacao->limite_medicao_atingido ?? false)) ? 1 : 0 }}"
                                            >
                                                <i class="ti ti-package me-1"></i>Enviar/Listar produtos
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary btn-abrir-faturamento-medicao"
                                                data-id="{{ $locacao->id_locacao }}"
                                                data-codigo="{{ $locacao->codigo_display }}"
                                                data-action="{{ route('financeiro.faturamento.faturar', $locacao->id_locacao) }}"
                                                data-preview-action="{{ route('financeiro.faturamento.preview', $locacao->id_locacao) }}"
                                                data-inicio-corte="{{ optional($locacao->inicio_corte_faturamento)->format('Y-m-d') }}"
                                                data-fim-corte="{{ now()->toDateString() }}"
                                                data-valor-aberto="{{ (float) ($locacao->valor_aberto_medicao ?? 0) }}"
                                            >
                                                <i class="ti ti-file-invoice me-1"></i>Faturar
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger btn-finalizar-contrato-medicao"
                                                data-id="{{ $locacao->id_locacao }}"
                                                data-codigo="{{ $locacao->codigo_display }}"
                                            >
                                                <i class="ti ti-check me-1"></i>Finalizar contrato
                                            </button>
                                            @else
                                                <span class="badge bg-label-success align-self-center">Encerrado</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum contrato em medição encontrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if(method_exists($locacoes, 'links'))
                <div class="mt-3">{{ $locacoes->links() }}</div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="modalRelatorioMedicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Contrato mensal de movimentações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="relatorioMedicaoLocacaoId" value="">
                    <div class="row g-2 align-items-end mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Período do contrato</label>
                            <input type="text" class="form-control" value="Início do contrato até hoje/data fim" readonly>
                        </div>
                        <div class="col-md-8 d-flex justify-content-md-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="btnAtualizarRelatorioMedicao"><i class="ti ti-refresh me-1"></i>Atualizar</button>
                            <a href="#" target="_blank" class="btn btn-outline-primary disabled" id="btnImprimirRelatorioMedicao"><i class="ti ti-printer me-1"></i>Imprimir contrato PDF</a>
                        </div>
                    </div>

                    <div id="relatorioMedicaoResumo" class="row g-2 mb-3 d-none">
                        <div class="col-md-3"><div class="alert alert-info mb-0">Entradas: <strong data-k="entradas_qtd">0</strong></div></div>
                        <div class="col-md-3"><div class="alert alert-primary mb-0">Itens entrada: <strong data-k="entradas_itens">0</strong></div></div>
                        <div class="col-md-3"><div class="alert alert-warning mb-0">Retornos: <strong data-k="retornos_qtd">0</strong></div></div>
                        <div class="col-md-3"><div class="alert alert-secondary mb-0">Itens retorno: <strong data-k="retornos_itens">0</strong></div></div>
                    </div>

                    <div id="relatorioMedicaoInfo" class="mb-2 text-muted small"></div>
                    <div id="relatorioMedicaoValorTotal" class="mb-2 fw-semibold text-primary"></div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="tabelaRelatorioMedicao">
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Tipo</th>
                                    <th>Produto</th>
                                    <th>Patrimônio</th>
                                    <th>Qtd</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Selecione um contrato para carregar os dados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <h6 class="mb-2">Financeiro por produto (período selecionado)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover" id="tabelaResumoProdutosRelatorioMedicao">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>Patrimônio</th>
                                        <th>Qtd</th>
                                        <th>Valor Unit.</th>
                                        <th>Dias</th>
                                        <th>Subtotal</th>
                                        <th>Período</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-3">Selecione um contrato para carregar o financeiro por produto.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h6 class="mb-2">Períodos já faturados</h6>
                        <div id="relatorioMedicaoPeriodosFaturados" class="small text-muted">Sem períodos faturados.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalMovimentacaoMedicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Movimentação de produtos (Medição)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="movMedicaoLocacaoId" value="">

                    <div class="alert alert-primary py-2 px-3 mb-3" id="movMedicaoContratoInfo">
                        Selecione um contrato para movimentar produtos.
                    </div>
                    <div class="alert alert-info py-2 px-3 mb-3 d-none" id="movMedicaoLimiteInfo"></div>

                    <div class="card border mb-3">
                        <div class="card-body">
                            <h6 class="mb-3">Adicionar produtos para envio em lote</h6>
                            <form id="formEnviarProdutoMedicao" class="row g-2" novalidate>
                                <div class="col-md-6">
                                    <label class="form-label">Produto</label>
                                    <select class="form-select" id="movMedicaoProduto">
                                        <option value="">Selecione...</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Qtd</label>
                                    <input type="number" min="1" class="form-control" id="movMedicaoQuantidade" value="1">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Preço unit.</label>
                                    <input type="text" class="form-control" id="movMedicaoPreco" value="0,00" inputmode="decimal" autocomplete="off">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Data envio</label>
                                    <input type="date" class="form-control" id="movMedicaoDataEnvio">
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-primary w-100" id="btnAdicionarLoteMedicao">
                                        <i class="ti ti-plus me-1"></i>Adicionar
                                    </button>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-primary w-100" id="btnEnviarLoteMedicao">
                                        <i class="ti ti-send me-1"></i>Enviar lote
                                    </button>
                                </div>
                            </form>

                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-hover mb-0" id="tabelaLoteEnvioMedicao">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Qtd</th>
                                            <th>Preço Unit.</th>
                                            <th>Data envio</th>
                                            <th style="width: 80px;">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-2">Nenhum item no lote.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Itens do contrato</h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" id="btnAbrirRetornoSelecionados" disabled>
                                <i class="ti ti-arrow-back-up me-1"></i>Retornar selecionados
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAtualizarItensMedicao">
                                <i class="ti ti-refresh me-1"></i>Atualizar
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover" id="tabelaItensMovimentacaoMedicao">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="chkSelecionarTodosRetornoMedicao" class="form-check-input">
                                    </th>
                                    <th>Produto</th>
                                    <th>Patrimônio</th>
                                    <th>Qtd</th>
                                    <th>Valor Unit.</th>
                                    <th>Dias (até hoje)</th>
                                    <th>Valor Previsto</th>
                                    <th>Valor Realizado</th>
                                    <th>Enviado em</th>
                                    <th>Retorno</th>
                                    <th>Status</th>
                                    <th style="width: 90px;">Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-3">Sem itens carregados.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3">
                        <h6 class="mb-2">Períodos já faturados</h6>
                        <div id="movMedicaoPeriodosFaturados" class="small text-muted">Sem períodos faturados.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalRetornoSelecionadosMedicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Retornar produtos selecionados</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 px-3 mb-3">
                        Defina a data de retorno para cada produto selecionado.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="tabelaRetornoSelecionadosMedicao">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd</th>
                                    <th>Qtd retorno</th>
                                    <th>Data envio</th>
                                    <th>Data retorno</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">Nenhum item selecionado.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarRetornoSelecionadosMedicao">
                        <i class="ti ti-check me-1"></i>Confirmar retornos
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalPatrimoniosLoteMedicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Selecionar patrimônio dos itens do lote</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 px-3 mb-3">
                        Para produtos com patrimônio, selecione exatamente a quantidade que será enviada.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0" id="tabelaPatrimoniosLoteMedicao">
                            <thead>
                                <tr>
                                    <th>Produto</th>
                                    <th>Qtd no lote</th>
                                    <th>Patrimônios disponíveis</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-3">Nenhum item com patrimônio no lote.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmarPatrimoniosLoteMedicao">
                        <i class="ti ti-check me-1"></i>Confirmar envio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFaturamentoMedicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Faturar contrato de medição</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <form id="formFaturamentoMedicao" class="row g-3">
                        <input type="hidden" id="fatMedicaoAction" value="">
                        <input type="hidden" id="fatMedicaoPreviewAction" value="">
                        <input type="hidden" id="fatMedicaoLocacaoId" value="">

                        <div class="col-12">
                            <div class="alert alert-primary py-2 px-3 mb-0" id="fatMedicaoContratoInfo">
                                Selecione um contrato para faturar.
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Período inicial</label>
                            <input type="date" class="form-control" id="fatMedicaoPeriodoInicio" required readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Período final</label>
                            <input type="date" class="form-control" id="fatMedicaoPeriodoFim" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Vencimento (fatura única)</label>
                            <input type="date" class="form-control" id="fatMedicaoDataVencimento" value="{{ now()->addDays(15)->toDateString() }}">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="fatMedicaoParcelar">
                                <label class="form-check-label" for="fatMedicaoParcelar">Parcelar faturamento</label>
                            </div>
                        </div>

                        <div class="col-md-4 d-none" id="fatMedicaoQtdParcelasWrap">
                            <label class="form-label">Qtd. parcelas</label>
                            <input type="number" min="2" max="24" class="form-control" id="fatMedicaoQtdParcelas" value="2">
                        </div>
                        <div class="col-md-8 d-none d-flex align-items-end" id="fatMedicaoGerarParcelasWrap">
                            <button type="button" class="btn btn-outline-secondary" id="fatMedicaoGerarParcelas">
                                <i class="ti ti-refresh me-1"></i>Gerar vencimentos
                            </button>
                        </div>

                        <div class="col-12 d-none" id="fatMedicaoParcelasTabelaWrap">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0" id="fatMedicaoParcelasTabela">
                                    <thead>
                                        <tr>
                                            <th>Parcela</th>
                                            <th>Vencimento</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea class="form-control" id="fatMedicaoObservacoes" rows="2">Faturamento de medição</textarea>
                        </div>

                        <div class="col-12">
                            <div class="small text-muted" id="fatMedicaoInfoValor">Valor em aberto atual: R$ 0,00</div>
                            <div class="small text-primary fw-semibold" id="fatMedicaoPreviewValor">Prévia do período: R$ 0,00</div>
                            <div class="small text-muted" id="fatMedicaoPreviewParcelas"></div>
                            <div class="small text-muted" id="fatMedicaoPeriodosFaturados">Sem períodos faturados.</div>
                        </div>

                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-file-invoice me-1"></i>Confirmar faturamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script>
    (function () {
        if (!document.getElementById('swal2-front-modal-zindex-medicoes')) {
            $('head').append('<style id="swal2-front-modal-zindex-medicoes">.swal2-container{z-index:200000 !important;}</style>');
        }

        const modalElement = document.getElementById('modalRelatorioMedicao');
        const modalRelatorio = modalElement ? new bootstrap.Modal(modalElement) : null;
        const modalMovElement = document.getElementById('modalMovimentacaoMedicao');
        const modalMovimentacao = modalMovElement ? new bootstrap.Modal(modalMovElement) : null;
        const modalRetornoSelecionadosElement = document.getElementById('modalRetornoSelecionadosMedicao');
        const modalRetornoSelecionados = modalRetornoSelecionadosElement ? new bootstrap.Modal(modalRetornoSelecionadosElement) : null;
        const modalPatrimoniosLoteElement = document.getElementById('modalPatrimoniosLoteMedicao');
        const modalPatrimoniosLote = modalPatrimoniosLoteElement ? new bootstrap.Modal(modalPatrimoniosLoteElement) : null;
        const modalFatElement = document.getElementById('modalFaturamentoMedicao');
        const modalFaturamento = modalFatElement ? new bootstrap.Modal(modalFatElement) : null;
        const $tbody = $('#tabelaRelatorioMedicao tbody');
        const $info = $('#relatorioMedicaoInfo');
        const $resumo = $('#relatorioMedicaoResumo');
        const $btnImprimir = $('#btnImprimirRelatorioMedicao');
        const $tbodyMov = $('#tabelaItensMovimentacaoMedicao tbody');
        const $tbodyResumoProdutosRelatorio = $('#tabelaResumoProdutosRelatorioMedicao tbody');
        const hasSwal = typeof window.Swal !== 'undefined';
        let itensLoteEnvioMedicao = [];
        let produtosDisponiveisMedicaoMap = {};

        function normalizarDataBackend(valor) {
            if (!valor) return '';
            const texto = String(valor).trim();
            if (!texto) return '';

            if (/^\d{4}-\d{2}-\d{2}$/.test(texto)) return texto;
            if (texto.includes('T')) return texto.split('T')[0];
            if (/^\d{4}-\d{2}-\d{2}\s/.test(texto)) return texto.split(' ')[0];
            return texto.slice(0, 10);
        }

        function alertaSimples(titulo, mensagem) {
            window.alert((titulo ? (titulo + ': ') : '') + (mensagem || ''));
        }

        @if (session('success'))
            if (hasSwal) {
                Swal.fire({
                    icon: 'success',
                    title: 'Sucesso',
                    text: @json(session('success')),
                    confirmButtonText: 'Ok'
                });
            }
        @endif

        @if (session('error'))
            if (hasSwal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: @json(session('error')),
                    confirmButtonText: 'Ok'
                });
            }
        @endif

        function formatarMoeda(valor) {
            const numero = Number(valor || 0);
            return 'R$ ' + numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseMoedaBr(valor) {
            const texto = String(valor || '').trim();
            if (!texto) return 0;

            const normalizado = texto.includes(',')
                ? texto.replace(/\./g, '').replace(',', '.')
                : texto;

            const numero = Number(normalizado);
            return Number.isFinite(numero) ? numero : 0;
        }

        function formatarCampoMoedaBr($input) {
            const numero = parseMoedaBr($input.val());
            $input.val(numero.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            return numero;
        }

        function carregarInfoClienteFiltroMedicao(clienteId) {
            const $content = $('#filtroClienteMedicaoInfoContent');

            if (!clienteId) {
                $content.addClass('d-none');
                return;
            }

            $.ajax({
                url: `/clientes/${clienteId}/json`,
                method: 'GET',
                success: function (cliente) {
                    const nome = String(cliente?.nome || '-').trim();
                    const documento = cliente?.documento || cliente?.cpf_cnpj || '-';
                    const telefone = cliente?.telefone || cliente?.celular || '-';
                    const email = cliente?.email || '-';

                    const partesEndereco = [];
                    if (cliente?.endereco) partesEndereco.push(cliente.endereco);
                    if (cliente?.numero) partesEndereco.push(cliente.numero);
                    if (cliente?.bairro) partesEndereco.push(cliente.bairro);
                    if (cliente?.cidade) partesEndereco.push(cliente.cidade);

                    const iniciais = nome
                        ? nome.split(/\s+/).slice(0, 2).map(function (parte) { return parte.charAt(0).toUpperCase(); }).join('')
                        : 'CL';

                    if (cliente?.foto_url) {
                        $('#filtroClienteMedicaoFoto').html(`<img src="${cliente.foto_url}" class="rounded" style="width:48px;height:48px;object-fit:cover;" alt="Foto do cliente">`);
                    } else {
                        $('#filtroClienteMedicaoFoto').html('<div class="avatar avatar-md"><span class="avatar-initial rounded bg-label-primary">' + iniciais + '</span></div>');
                    }

                    $('#filtroClienteMedicaoNome').text(nome || '-');
                    $('#filtroClienteMedicaoDocumento').text(documento);
                    $('#filtroClienteMedicaoContato').text(`Contato: ${telefone} • ${email}`);
                    $('#filtroClienteMedicaoEndereco').text(partesEndereco.join(', ') || '-');
                    $content.removeClass('d-none');
                },
                error: function () {
                    $content.addClass('d-none');
                }
            });
        }

        function fecharModalMovimentacao() {
            if (modalMovimentacao) {
                modalMovimentacao.hide();
            }
            $('#modalMovimentacaoMedicao').modal('hide');
        }

        function notificar(tipo, titulo, mensagem) {
            if (hasSwal) {
                return Swal.fire(titulo, mensagem, tipo);
            }
            alertaSimples(titulo, mensagem);
            return Promise.resolve();
        }

        function confirmarAcao(titulo, mensagem, textoBotaoConfirmar) {
            if (hasSwal) {
                return Swal.fire({
                    title: titulo,
                    text: mensagem,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: textoBotaoConfirmar || 'Confirmar',
                    cancelButtonText: 'Cancelar'
                }).then(function (r) { return !!r.isConfirmed; });
            }

            return Promise.resolve(window.confirm(mensagem || titulo || 'Confirmar ação?'));
        }

        function solicitarDatasRetorno(dataInicioAtual, dataRetornoAtual, podeEditarDatas, quantidadeMaxima, usaPatrimonio) {
            if (hasSwal) {
                const mostrarQuantidade = !usaPatrimonio && Number(quantidadeMaxima || 1) > 1;
                return Swal.fire({
                    title: 'Confirmar retorno',
                    text: 'Informe as datas para concluir o retorno.',
                    icon: 'question',
                    html: `
                        <div class="text-start">
                            ${mostrarQuantidade ? '<label class="form-label mb-1">Quantidade de retorno</label><input type="number" id="swalQuantidadeRetornoMedicao" class="swal2-input mt-0" min="1" max="' + Number(quantidadeMaxima || 1) + '" value="' + Number(quantidadeMaxima || 1) + '">' : ''}
                            <label class="form-label mb-1">Data de envio</label>
                            <input type="date" id="swalDataInicioMedicao" class="swal2-input mt-0" value="${dataInicioAtual || obterDataLocalHoje()}" ${podeEditarDatas ? '' : 'readonly'}>
                            <label class="form-label mb-1 mt-2">Data de retorno</label>
                            <input type="date" id="swalDataRetornoMedicao" class="swal2-input mt-0" value="${dataRetornoAtual || obterDataLocalHoje()}">
                        </div>
                    `,
                    focusConfirm: false,
                    showCancelButton: true,
                    confirmButtonText: 'Sim, retornar',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const quantidadeRetorno = mostrarQuantidade
                            ? Number(document.getElementById('swalQuantidadeRetornoMedicao')?.value || 0)
                            : 1;
                        const dataInicio = String(document.getElementById('swalDataInicioMedicao')?.value || '').trim();
                        const dataRetorno = String(document.getElementById('swalDataRetornoMedicao')?.value || '').trim();

                        if (mostrarQuantidade && (!Number.isFinite(quantidadeRetorno) || quantidadeRetorno < 1 || quantidadeRetorno > Number(quantidadeMaxima || 1))) {
                            Swal.showValidationMessage('Informe uma quantidade de retorno válida.');
                            return false;
                        }

                        if (!dataInicio) {
                            Swal.showValidationMessage('Informe a data de envio.');
                            return false;
                        }

                        if (!dataRetorno) {
                            Swal.showValidationMessage('Informe a data de retorno.');
                            return false;
                        }

                        if (dataRetorno < dataInicio) {
                            Swal.showValidationMessage('A data de retorno não pode ser anterior à data de envio.');
                            return false;
                        }

                        return {
                            data_inicio: dataInicio,
                            data_retorno: dataRetorno,
                            quantidade_retorno: mostrarQuantidade ? quantidadeRetorno : 1
                        };
                    }
                }).then(function (result) {
                    return result.isConfirmed ? (result.value || null) : null;
                });
            }

            const mostrarQuantidade = !usaPatrimonio && Number(quantidadeMaxima || 1) > 1;
            let quantidadeRetorno = 1;
            if (mostrarQuantidade) {
                const quantidadeInformada = window.prompt('Informe a quantidade de retorno', String(Number(quantidadeMaxima || 1)));
                if (!quantidadeInformada) return Promise.resolve(null);

                quantidadeRetorno = Number(quantidadeInformada);
                if (!Number.isFinite(quantidadeRetorno) || quantidadeRetorno < 1 || quantidadeRetorno > Number(quantidadeMaxima || 1)) {
                    return Promise.resolve(null);
                }
            }

            const dataInicio = window.prompt('Informe a data de envio (YYYY-MM-DD)', dataInicioAtual || obterDataLocalHoje());
            if (!dataInicio) return Promise.resolve(null);

            const dataRetorno = window.prompt('Informe a data de retorno (YYYY-MM-DD)', dataRetornoAtual || obterDataLocalHoje());
            if (!dataRetorno) return Promise.resolve(null);

            return Promise.resolve({
                data_inicio: String(dataInicio || '').trim(),
                data_retorno: String(dataRetorno || '').trim(),
                quantidade_retorno: quantidadeRetorno
            });
        }

        function urlRelatorio(idLocacao) {
            const base = `{{ url('locacoes/medicoes') }}/${idLocacao}/relatorio-movimentacoes`;
            return base;
        }

        function urlRelatorioPdf(idLocacao) {
            const base = `{{ url('locacoes/medicoes') }}/${idLocacao}/relatorio-movimentacoes-pdf`;
            return base;
        }

        function urlItensMovimentacao(idLocacao) {
            return `{{ url('locacoes/medicoes') }}/${idLocacao}/itens`;
        }

        function urlProdutosDisponiveisMovimentacao(idLocacao) {
            return `{{ url('locacoes/medicoes') }}/${idLocacao}/produtos-disponiveis`;
        }

        function urlEnviarProdutoMovimentacao(idLocacao) {
            return `{{ url('locacoes/medicoes') }}/${idLocacao}/enviar-produto`;
        }

        function urlRetornarItemMovimentacao(idLocacao, idProdutoLocacao) {
            return `{{ url('locacoes/medicoes') }}/${idLocacao}/itens/${idProdutoLocacao}/retornar`;
        }

        function urlEditarDatasItemMovimentacao(idLocacao, idProdutoLocacao) {
            return `{{ url('locacoes/medicoes') }}/${idLocacao}/itens/${idProdutoLocacao}/editar-datas`;
        }

        function urlFinalizarContratoMedicao(idLocacao) {
            return `{{ url('locacoes/medicoes') }}/${idLocacao}/finalizar`;
        }

        function obterDataLocalHoje() {
            const agora = new Date();
            const offset = agora.getTimezoneOffset();
            const local = new Date(agora.getTime() - (offset * 60000));
            return local.toISOString().slice(0, 10);
        }

        function limparTabela(texto) {
            $tbody.html(`<tr><td colspan="5" class="text-center text-muted py-3">${texto}</td></tr>`);
        }

        function aplicarResumo(resumo) {
            $resumo.find('[data-k="entradas_qtd"]').text(resumo.entradas_qtd ?? 0);
            $resumo.find('[data-k="entradas_itens"]').text(resumo.entradas_itens ?? 0);
            $resumo.find('[data-k="retornos_qtd"]').text(resumo.retornos_qtd ?? 0);
            $resumo.find('[data-k="retornos_itens"]').text(resumo.retornos_itens ?? 0);
            $resumo.removeClass('d-none');
        }

        function carregarRelatorioMedicao() {
            const idLocacao = $('#relatorioMedicaoLocacaoId').val();

            if (!idLocacao) {
                limparTabela('Selecione um contrato válido.');
                return;
            }

            limparTabela('Carregando...');
            $btnImprimir.addClass('disabled').attr('href', '#');

            $.get(urlRelatorio(idLocacao))
                .done(function (response) {
                    if (!response || !response.success) {
                        limparTabela('Não foi possível carregar o relatório.');
                        return;
                    }

                    const locacao = response.locacao || {};
                    const movimentacoes = response.movimentacoes || [];
                    const produtosResumo = response.produtos_resumo || [];
                    const periodosFaturados = response.periodos_faturados || [];
                    const resumo = response.resumo || {};

                    const periodo = response.periodo || {};
                    $info.html(`<strong>Contrato:</strong> ${locacao.codigo_display || idLocacao} <span class="mx-2">•</span><strong>Cliente:</strong> ${locacao.cliente_nome || 'N/A'} <span class="mx-2">•</span><strong>Período:</strong> ${periodo.inicio || '-'} até ${periodo.fim || '-'}`);
                    $('#relatorioMedicaoValorTotal').text(`Valor total do período medido: ${formatarMoeda(response.valor_total_periodo || 0)}`);
                    aplicarResumo(resumo);

                    if (!movimentacoes.length) {
                        limparTabela('Sem movimentações no período selecionado.');
                    } else {
                        const html = movimentacoes.map(function (mov) {
                            const badgeClass = mov.tipo === 'Entrada' ? 'bg-label-success' : 'bg-label-warning';
                            return `
                                <tr>
                                    <td>${mov.data_hora || '-'}</td>
                                    <td><span class="badge ${badgeClass}">${mov.tipo || '-'}</span></td>
                                    <td>${mov.produto || '-'}</td>
                                    <td>${mov.patrimonio || '-'}</td>
                                    <td>${mov.quantidade ?? 0}</td>
                                </tr>
                            `;
                        }).join('');
                        $tbody.html(html);
                    }

                    if (!produtosResumo.length) {
                        $tbodyResumoProdutosRelatorio.html('<tr><td colspan="7" class="text-center text-muted py-3">Sem financeiro de produtos para o período.</td></tr>');
                    } else {
                        const htmlProdutos = produtosResumo.map(function (item) {
                            return `
                                <tr>
                                    <td>${item.produto || '-'}</td>
                                    <td>${item.patrimonio || '-'}</td>
                                    <td>${item.quantidade ?? 1}</td>
                                    <td>${formatarMoeda(item.valor_unitario || 0)}</td>
                                    <td>${item.dias_periodo ?? 0}</td>
                                    <td>${formatarMoeda(item.valor_periodo || 0)}</td>
                                    <td>${item.inicio || '-'} até ${item.fim || '-'}</td>
                                </tr>
                            `;
                        }).join('');
                        $tbodyResumoProdutosRelatorio.html(htmlProdutos);
                    }

                    renderizarPeriodosFaturados($('#relatorioMedicaoPeriodosFaturados'), periodosFaturados);

                    $btnImprimir.removeClass('disabled').attr('href', urlRelatorioPdf(idLocacao));
                })
                .fail(function (xhr) {
                    const mensagem = xhr.responseJSON?.message || 'Erro ao carregar relatório.';
                    limparTabela(mensagem);
                    $('#relatorioMedicaoValorTotal').text('');
                    $tbodyResumoProdutosRelatorio.html('<tr><td colspan="7" class="text-center text-muted py-3">Erro ao carregar financeiro do período.</td></tr>');
                    $('#relatorioMedicaoPeriodosFaturados').html('Sem períodos faturados.');
                });
        }

        function limparItensMovimentacao(texto) {
            $tbodyMov.html(`<tr><td colspan="12" class="text-center text-muted py-3">${texto}</td></tr>`);
            $('#chkSelecionarTodosRetornoMedicao').prop('checked', false);
            atualizarBotaoRetornoSelecionados();
        }

        function atualizarBotaoRetornoSelecionados() {
            const totalSelecionados = $('.chk-retorno-medicao:checked').length;
            $('#btnAbrirRetornoSelecionados')
                .prop('disabled', totalSelecionados === 0)
                .html(`<i class="ti ti-arrow-back-up me-1"></i>Retornar selecionados${totalSelecionados > 0 ? ' (' + totalSelecionados + ')' : ''}`);
        }

        function coletarItensSelecionadosRetorno() {
            return $('.chk-retorno-medicao:checked').map(function () {
                const $el = $(this);
                return {
                    id_item: Number($el.data('item')),
                    produto: String($el.data('produto') || '-'),
                    quantidade: Number($el.data('quantidade') || 1),
                    usa_patrimonio: Number($el.data('usa-patrimonio') || 0) === 1,
                    data_inicio: normalizarDataBackend($el.data('inicio') || ''),
                    data_inicio_local: String($el.data('inicio-local') || '-'),
                };
            }).get();
        }

        function renderizarModalRetornoSelecionados(itensSelecionados) {
            const $tbody = $('#tabelaRetornoSelecionadosMedicao tbody');

            if (!itensSelecionados.length) {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted py-3">Nenhum item selecionado.</td></tr>');
                return;
            }

            const hoje = obterDataLocalHoje();
            const html = itensSelecionados.map(function (item) {
                const dataPadrao = item.data_inicio && item.data_inicio <= hoje ? hoje : (item.data_inicio || hoje);
                return `
                    <tr>
                        <td>${item.produto}</td>
                        <td>${item.quantidade}</td>
                        <td>
                            ${item.usa_patrimonio
                                ? '<span class="text-muted">1</span>'
                                : `<input type="number"
                                         class="form-control form-control-sm input-qtd-retorno-item-medicao"
                                         data-item="${item.id_item}"
                                         min="1"
                                         max="${item.quantidade}"
                                         value="${item.quantidade}">`
                            }
                        </td>
                        <td>${item.data_inicio_local || '-'}</td>
                        <td>
                            <input type="date"
                                   class="form-control form-control-sm input-retorno-item-medicao"
                                   data-item="${item.id_item}"
                                   data-inicio="${item.data_inicio || ''}"
                                data-qtd-max="${item.quantidade || 1}"
                                   value="${dataPadrao}"
                                   min="${item.data_inicio || ''}">
                        </td>
                    </tr>
                `;
            }).join('');

            $tbody.html(html);
        }

        function renderizarPeriodosFaturados($container, periodos) {
            if (!periodos || !periodos.length) {
                $container.html('Sem períodos faturados.');
                return;
            }

            const html = periodos.map(function (periodo) {
                const numero = periodo.numero_fatura ? `Fatura #${periodo.numero_fatura} • ` : '';
                const linkPdf = periodo.pdf_url
                    ? ` • <a href="${periodo.pdf_url}" target="_blank" rel="noopener">NF/PDF</a>`
                    : '';
                return `<div>${numero}${periodo.inicio} até ${periodo.fim} • ${formatarMoeda(periodo.valor || 0)}${linkPdf}</div>`;
            }).join('');

            $container.html(html);
        }

        function renderizarLoteEnvioMedicao() {
            const $tbody = $('#tabelaLoteEnvioMedicao tbody');

            if (!itensLoteEnvioMedicao.length) {
                $tbody.html('<tr><td colspan="5" class="text-center text-muted py-2">Nenhum item no lote.</td></tr>');
                return;
            }

            const html = itensLoteEnvioMedicao.map(function (item, index) {
                return `
                    <tr>
                        <td>${item.produto_nome || '-'}</td>
                        <td>${item.quantidade || 1}</td>
                        <td>${formatarMoeda(item.preco_unitario || 0)}</td>
                        <td>${item.data_envio_local || '-'}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remover-item-lote-medicao" data-index="${index}" title="Remover">
                                <i class="ti ti-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            $tbody.html(html);
        }

        function renderizarModalPatrimoniosLote(itensComPatrimonio) {
            const $tbody = $('#tabelaPatrimoniosLoteMedicao tbody');

            if (!itensComPatrimonio.length) {
                $tbody.html('<tr><td colspan="3" class="text-center text-muted py-3">Nenhum item com patrimônio no lote.</td></tr>');
                return;
            }

            const html = itensComPatrimonio.map(function (item) {
                const opcoes = (item.patrimonios_disponiveis || []).map(function (pat) {
                    return `
                        <label class="form-check d-flex align-items-center gap-2 mb-1">
                            <input type="checkbox" class="form-check-input chk-patrimonio-lote-medicao" data-lote-id="${item.lote_id}" value="${pat.id_patrimonio}">
                            <span>${pat.codigo || ('PAT-' + pat.id_patrimonio)}</span>
                        </label>
                    `;
                }).join('');

                return `
                    <tr>
                        <td>${item.produto_nome || '-'}</td>
                        <td>${item.quantidade || 1}</td>
                        <td>
                            <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                ${opcoes}
                            </div>
                            <small class="text-muted d-block mt-1">Selecione ${item.quantidade || 1} patrimônio(s).</small>
                        </td>
                    </tr>
                `;
            }).join('');

            $tbody.html(html);
        }

        function enviarLoteMedicao(itensPayload) {
            const idLocacao = $('#movMedicaoLocacaoId').val();

            if (!idLocacao) {
                notificar('warning', 'Atenção', 'Contrato inválido para envio.');
                return;
            }

            $.ajax({
                url: urlEnviarProdutoMovimentacao(idLocacao),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    itens: itensPayload
                },
                success: function (response) {
                    fecharModalMovimentacao();
                    notificar('success', 'Sucesso!', response.message || 'Produto enviado.').then(function () {
                        window.location.reload();
                    });
                },
                error: function (xhr) {
                    const erros = xhr.responseJSON?.errors || {};
                    const primeiroErro = Object.values(erros)[0]?.[0] || null;
                    const mensagem = primeiroErro || xhr.responseJSON?.message || 'Não foi possível enviar o produto.';
                    notificar('error', 'Erro!', mensagem);
                }
            });
        }

        function alternarParcelamentoFaturamento() {
            const parcelar = $('#fatMedicaoParcelar').is(':checked');
            $('#fatMedicaoQtdParcelasWrap').toggleClass('d-none', !parcelar);
            $('#fatMedicaoGerarParcelasWrap').toggleClass('d-none', !parcelar);
            $('#fatMedicaoParcelasTabelaWrap').toggleClass('d-none', !parcelar);
            $('#fatMedicaoDataVencimento').prop('disabled', parcelar);

            if (parcelar) {
                gerarParcelasFaturamento();
            }

            atualizarPreviaFaturamento();
        }

        function gerarParcelasFaturamento() {
            const parcelar = $('#fatMedicaoParcelar').is(':checked');
            if (!parcelar) {
                $('#fatMedicaoParcelasTabela tbody').empty();
                return;
            }

            const quantidade = Math.max(2, parseInt($('#fatMedicaoQtdParcelas').val() || '2', 10));
            $('#fatMedicaoQtdParcelas').val(quantidade);

            const vencBase = $('#fatMedicaoDataVencimento').val() || '{{ now()->addDays(15)->toDateString() }}';
            const dataBase = new Date(vencBase + 'T00:00:00');

            let html = '';
            for (let i = 0; i < quantidade; i++) {
                const data = new Date(dataBase);
                data.setMonth(data.getMonth() + i);
                const yyyy = data.getFullYear();
                const mm = String(data.getMonth() + 1).padStart(2, '0');
                const dd = String(data.getDate()).padStart(2, '0');
                const valorData = `${yyyy}-${mm}-${dd}`;

                html += `
                    <tr>
                        <td>${i + 1}/${quantidade}</td>
                        <td><input type="date" class="form-control form-control-sm fat-medicao-parcela-data" data-parcela="${i + 1}" value="${valorData}" required></td>
                    </tr>
                `;
            }

            $('#fatMedicaoParcelasTabela tbody').html(html);
            atualizarPreviaFaturamento();
        }

        function atualizarPreviaFaturamento() {
            const previewAction = $('#fatMedicaoPreviewAction').val();
            if (!previewAction) {
                return;
            }

            const payload = {
                _token: '{{ csrf_token() }}',
                periodo_inicio: $('#fatMedicaoPeriodoInicio').val(),
                periodo_fim: $('#fatMedicaoPeriodoFim').val(),
                parcelar: $('#fatMedicaoParcelar').is(':checked') ? 1 : 0
            };

            if (payload.parcelar) {
                payload.quantidade_parcelas = Math.max(2, parseInt($('#fatMedicaoQtdParcelas').val() || '2', 10));
            }

            $('#fatMedicaoPreviewValor').text('Prévia do período: calculando...').removeClass('text-danger').addClass('text-primary');

            $.ajax({
                url: previewAction,
                method: 'POST',
                data: payload,
                success: function (response) {
                    const valorTotal = Number(response?.valor_total || 0);
                    const dias = Number(response?.periodo?.dias || 0);
                    $('#fatMedicaoPreviewValor').text(`Prévia do período (${dias} dia(s)): ${formatarMoeda(valorTotal)}`).removeClass('text-danger').addClass('text-primary');

                    const parcelas = response?.valores_parcelas || [];
                    if (!parcelas.length) {
                        $('#fatMedicaoPreviewParcelas').text('');
                        return;
                    }

                    const parcelasTexto = parcelas.map(function (valor, index) {
                        return `${index + 1}/${parcelas.length}: ${formatarMoeda(valor)}`;
                    }).join(' • ');

                    $('#fatMedicaoPreviewParcelas').text(`Parcelas previstas: ${parcelasTexto}`);
                },
                error: function (xhr) {
                    const erros = xhr.responseJSON?.errors || {};
                    const primeiroErro = Object.values(erros)[0]?.[0] || null;
                    const mensagem = primeiroErro || xhr.responseJSON?.message || 'Não foi possível calcular a prévia.';
                    $('#fatMedicaoPreviewValor').text(`Prévia do período: ${mensagem}`).removeClass('text-primary').addClass('text-danger');
                    $('#fatMedicaoPreviewParcelas').text('');
                }
            });
        }

        function carregarProdutosDisponiveisMovimentacao(idLocacao) {
            const $select = $('#movMedicaoProduto');
            $select.html('<option value="">Carregando...</option>');
            produtosDisponiveisMedicaoMap = {};

            $.get(urlProdutosDisponiveisMovimentacao(idLocacao))
                .done(function (response) {
                    const produtos = response?.produtos || [];
                    if (!response?.success || !produtos.length) {
                        $select.html('<option value="">Nenhum produto disponível</option>');
                        return;
                    }

                    const options = ['<option value="">Selecione...</option>'];
                    produtos.forEach(function (produto) {
                        produtosDisponiveisMedicaoMap[String(produto.id_produto)] = produto;
                        const preco = Number(produto.preco_locacao || 0).toFixed(2).replace('.', ',');
                        const indicadorPatrimonio = (produto.usa_patrimonio || (produto.patrimonios || []).length > 0)
                            ? ' • com patrimônio'
                            : '';
                        options.push(`<option value="${produto.id_produto}" data-preco="${preco}">${produto.nome}${produto.codigo ? ' (' + produto.codigo + ')' : ''}${indicadorPatrimonio}</option>`);
                    });
                    $select.html(options.join(''));
                })
                .fail(function () {
                    $select.html('<option value="">Erro ao carregar produtos</option>');
                });
        }

        function carregarItensMovimentacao(idLocacao) {
            limparItensMovimentacao('Carregando itens...');

            $.get(urlItensMovimentacao(idLocacao))
                .done(function (response) {
                    if (!response?.success) {
                        limparItensMovimentacao('Não foi possível carregar os itens.');
                        return;
                    }

                    const itens = response.itens || [];
                    const periodosFaturados = response.periodos_faturados || [];
                    const limiteMedicao = response.limite_medicao || {};
                    const valorLimite = Number(limiteMedicao.valor_limite || 0);
                    const valorRestante = Number(limiteMedicao.valor_restante || 0);
                    const limiteAtingido = valorLimite > 0 && valorRestante <= 0;

                    if (valorLimite > 0) {
                        $('#movMedicaoLimiteInfo')
                            .removeClass('d-none alert-info alert-danger')
                            .addClass(limiteAtingido ? 'alert-danger' : 'alert-info')
                            .text(limiteAtingido
                                ? `Limite de gasto atingido (${formatarMoeda(valorLimite)}). Ajuste o limite para liberar novos envios.`
                                : `Limite de gasto: ${formatarMoeda(valorLimite)} • Saldo disponível: ${formatarMoeda(Math.max(0, valorRestante))}`
                            );
                    }

                    $('#btnAdicionarLoteMedicao, #btnEnviarLoteMedicao').prop('disabled', limiteAtingido);

                    const dataEnvioMinimaLocal = response.data_envio_minima_local || '';
                    if (dataEnvioMinimaLocal) {
                        const $campoEnvio = $('#movMedicaoDataEnvio');
                        $campoEnvio.attr('min', dataEnvioMinimaLocal);
                        if (!$campoEnvio.val() || $campoEnvio.val() < dataEnvioMinimaLocal) {
                            $campoEnvio.val(dataEnvioMinimaLocal);
                        }
                    }

                    if (!itens.length) {
                        limparItensMovimentacao('Nenhum item enviado para este contrato.');
                        renderizarPeriodosFaturados($('#movMedicaoPeriodosFaturados'), periodosFaturados);
                        return;
                    }

                    const html = itens.map(function (item) {
                        const enviado = `${item.data_inicio || '-'}`.trim();
                        const retorno = item.retornado ? `${item.data_fim || '-'}`.trim() : '-';
                        const badge = item.retornado
                            ? '<span class="badge bg-label-success">Devolvido</span>'
                            : '<span class="badge bg-label-warning">Em uso</span>';

                        const podeEditar = !!item.pode_editar_datas;
                        const btnEditar = podeEditar
                            ? `<button type="button" class="btn btn-sm btn-outline-primary btn-editar-datas-item-medicao" data-item="${item.id_produto_locacao}" data-inicio="${item.data_inicio_iso || ''}" data-retorno="${item.data_fim_iso || ''}" data-retornado="${item.retornado ? 1 : 0}" title="Editar datas"><i class="ti ti-pencil"></i></button>`
                            : '';
                        const podeSelecionarRetorno = !item.retornado && Number(item.estoque_status || 0) === 1;
                        const checkboxRetorno = podeSelecionarRetorno
                            ? `<input type="checkbox" class="form-check-input chk-retorno-medicao" data-item="${item.id_produto_locacao}" data-produto="${String(item.produto || '-').replace(/"/g, '&quot;')}" data-quantidade="${item.quantidade ?? 1}" data-usa-patrimonio="${item.usa_patrimonio ? 1 : 0}" data-inicio="${item.data_inicio_iso || ''}" data-inicio-local="${item.data_inicio || '-'}">`
                            : '<span class="text-muted">-</span>';

                        const acao = (btnEditar)
                            ? `<div class="d-flex gap-1">${btnEditar}</div>`
                            : '<span class="text-muted">-</span>';

                        return `
                            <tr>
                                <td>${checkboxRetorno}</td>
                                <td>${item.produto || '-'}</td>
                                <td>${item.patrimonio || '-'}</td>
                                <td>${item.quantidade ?? 1}</td>
                                <td>${formatarMoeda(item.preco_unitario || 0)}</td>
                                <td>${item.dias_previstos_hoje ?? 0}</td>
                                <td>${formatarMoeda(item.valor_previsto_hoje || 0)}</td>
                                <td>${formatarMoeda(item.valor_realizado || 0)}</td>
                                <td>${enviado}</td>
                                <td>${retorno}</td>
                                <td>${badge}</td>
                                <td>${acao}</td>
                            </tr>
                        `;
                    }).join('');

                    $tbodyMov.html(html);
                    $('#chkSelecionarTodosRetornoMedicao').prop('checked', false);
                    atualizarBotaoRetornoSelecionados();
                    renderizarPeriodosFaturados($('#movMedicaoPeriodosFaturados'), periodosFaturados);
                })
                .fail(function (xhr) {
                    limparItensMovimentacao(xhr.responseJSON?.message || 'Erro ao carregar itens.');
                    $('#movMedicaoPeriodosFaturados').html('Sem períodos faturados.');
                });
        }

        $(document).on('click', '.btn-toggle-acoes', function () {
            const target = $(this).data('target');
            if (!target) return;

            $('.linha-acoes-locacao').not(target).addClass('d-none');
            $(target).toggleClass('d-none');
        });

        $(document).on('click', '.btn-abrir-relatorio-medicao', function () {
            const idLocacao = $(this).data('id');
            const codigo = $(this).data('codigo') || '';

            $('#relatorioMedicaoLocacaoId').val(idLocacao);
            $info.text(codigo ? `Contrato ${codigo}` : '');
            $resumo.addClass('d-none');
            limparTabela('Carregando...');

            if (modalRelatorio) {
                modalRelatorio.show();
            }

            carregarRelatorioMedicao();
        });

        $(document).on('click', '.btn-abrir-movimentacao-medicao', function () {
            const idLocacao = $(this).data('id');
            const codigo = $(this).data('codigo') || '';
            const limiteMedicao = Number($(this).data('limite-medicao') || 0);
            const saldoLimiteMedicao = Number($(this).data('saldo-limite-medicao') || 0);
            const limiteAtingido = Number($(this).data('limite-atingido') || 0) === 1;

            $('#movMedicaoLocacaoId').val(idLocacao);
            $('#movMedicaoContratoInfo').html(`<strong>Contrato:</strong> ${codigo || idLocacao}`);

            if (limiteMedicao > 0) {
                const classe = limiteAtingido ? 'alert-danger' : 'alert-info';
                const mensagemLimite = limiteAtingido
                    ? `Limite de gasto atingido (${formatarMoeda(limiteMedicao)}). Ajuste o limite para liberar novos envios.`
                    : `Limite de gasto: ${formatarMoeda(limiteMedicao)} • Saldo disponível: ${formatarMoeda(Math.max(0, saldoLimiteMedicao))}`;

                $('#movMedicaoLimiteInfo')
                    .removeClass('d-none alert-info alert-danger')
                    .addClass(classe)
                    .text(mensagemLimite);
            } else {
                $('#movMedicaoLimiteInfo').addClass('d-none').text('');
            }

            $('#btnAdicionarLoteMedicao, #btnEnviarLoteMedicao').prop('disabled', limiteAtingido);
            $('#movMedicaoQuantidade').val(1);
            $('#movMedicaoPreco').val('0,00');
            $('#movMedicaoDataEnvio').val(obterDataLocalHoje());
            itensLoteEnvioMedicao = [];
            renderizarLoteEnvioMedicao();
            $('#chkSelecionarTodosRetornoMedicao').prop('checked', false);
            atualizarBotaoRetornoSelecionados();

            if (modalMovimentacao) {
                modalMovimentacao.show();
            }

            carregarProdutosDisponiveisMovimentacao(idLocacao);
            carregarItensMovimentacao(idLocacao);
        });

        $(document).on('click', '.btn-abrir-faturamento-medicao', function () {
            const action = $(this).data('action') || '';
            const previewAction = $(this).data('preview-action') || '';
            const idLocacao = $(this).data('id') || '';
            const codigo = $(this).data('codigo') || '';
            const inicioCorte = $(this).data('inicio-corte') || '{{ now()->toDateString() }}';
            const fimCorte = $(this).data('fim-corte') || '{{ now()->toDateString() }}';
            const valorAberto = Number($(this).data('valor-aberto') || 0);

            $('#fatMedicaoAction').val(action);
            $('#fatMedicaoPreviewAction').val(previewAction);
            $('#fatMedicaoLocacaoId').val(idLocacao);
            $('#fatMedicaoContratoInfo').html(`<strong>Contrato:</strong> ${codigo || idLocacao}`);
            $('#fatMedicaoPeriodoInicio').val(inicioCorte);
            $('#fatMedicaoPeriodoFim').val(fimCorte);
            $('#fatMedicaoDataVencimento').val('{{ now()->addDays(15)->toDateString() }}');
            $('#fatMedicaoObservacoes').val(`Faturamento de medição (${codigo || idLocacao})`);
            $('#fatMedicaoInfoValor').text(`Valor em aberto atual: ${formatarMoeda(valorAberto)}`);
            $('#fatMedicaoPreviewValor').text('Prévia do período: calculando...').removeClass('text-danger').addClass('text-primary');
            $('#fatMedicaoPreviewParcelas').text('');
            $('#fatMedicaoParcelar').prop('checked', false);
            $('#fatMedicaoQtdParcelas').val(2);
            alternarParcelamentoFaturamento();

            $.get(urlItensMovimentacao(idLocacao))
                .done(function (response) {
                    renderizarPeriodosFaturados($('#fatMedicaoPeriodosFaturados'), response?.periodos_faturados || []);
                })
                .fail(function () {
                    $('#fatMedicaoPeriodosFaturados').html('Sem períodos faturados.');
                });

            if (modalFaturamento) {
                modalFaturamento.show();
            }

            atualizarPreviaFaturamento();
        });

        $(document).on('click', '.btn-finalizar-contrato-medicao', function () {
            const idLocacao = $(this).data('id');
            const codigo = $(this).data('codigo') || idLocacao;

            confirmarAcao(
                'Finalizar contrato',
                `Deseja finalizar o contrato ${codigo}? O sistema irá bloquear se houver período pendente de faturamento.`,
                'Sim, finalizar'
            ).then(function (confirmado) {
                if (!confirmado) return;

                $.ajax({
                    url: urlFinalizarContratoMedicao(idLocacao),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}'
                    },
                    success: function (response) {
                        notificar('success', 'Sucesso!', response.message || 'Contrato finalizado.').then(function () {
                            window.location.reload();
                        });
                    },
                    error: function (xhr) {
                        const erros = xhr.responseJSON?.errors || {};
                        const primeiroErro = Object.values(erros)[0]?.[0] || null;
                        const mensagem = primeiroErro || xhr.responseJSON?.message || 'Não foi possível finalizar o contrato.';
                        notificar('error', 'Atenção', mensagem);
                    }
                });
            });
        });

        $('#fatMedicaoParcelar').on('change', alternarParcelamentoFaturamento);
        $('#fatMedicaoQtdParcelas').on('input', gerarParcelasFaturamento);
        $('#fatMedicaoGerarParcelas').on('click', gerarParcelasFaturamento);
        $('#fatMedicaoPeriodoInicio, #fatMedicaoPeriodoFim').on('change', atualizarPreviaFaturamento);
        $('#fatMedicaoParcelasTabela').on('change', '.fat-medicao-parcela-data', atualizarPreviaFaturamento);
        $('select[name="id_cliente"]').on('change', function () {
            carregarInfoClienteFiltroMedicao($(this).val());
        });

        carregarInfoClienteFiltroMedicao($('select[name="id_cliente"]').val());

        $('#movMedicaoProduto').on('change', function () {
            const preco = $(this).find('option:selected').data('preco');
            if (typeof preco !== 'undefined') {
                $('#movMedicaoPreco').val(preco);
                formatarCampoMoedaBr($('#movMedicaoPreco'));
            }
        });

        $('#movMedicaoPreco').on('blur', function () {
            formatarCampoMoedaBr($(this));
        });

        function adicionarItemLoteMedicao() {
            if ($('#movMedicaoLimiteInfo').hasClass('alert-danger')) {
                notificar('warning', 'Limite atingido', 'Ajuste o valor limite da medição para liberar novos envios.');
                return false;
            }

            const idProduto = $('#movMedicaoProduto').val();
            const nomeProduto = $('#movMedicaoProduto option:selected').text() || '';
            const quantidade = $('#movMedicaoQuantidade').val();
            const preco = $('#movMedicaoPreco').val();
            const dataEnvio = $('#movMedicaoDataEnvio').val();

            if (!idProduto) {
                notificar('warning', 'Atenção', 'Selecione o produto para adicionar ao lote.');
                return false;
            }

            if (!dataEnvio) {
                notificar('warning', 'Atenção', 'Informe a data de envio.');
                return false;
            }

            const dataMinima = $('#movMedicaoDataEnvio').attr('min') || '';
            if (dataMinima && dataEnvio < dataMinima) {
                notificar('warning', 'Atenção', 'Data de envio anterior ao próximo período faturável.');
                return false;
            }

            const quantidadeNumero = Math.max(1, parseInt(quantidade || '1', 10));
            const precoNumerico = parseMoedaBr(preco);
            const produtoSelecionado = produtosDisponiveisMedicaoMap[String(idProduto)] || {};
            const patrimoniosDisponiveis = Array.isArray(produtoSelecionado.patrimonios) ? produtoSelecionado.patrimonios : [];
            const usaPatrimonio = !!(produtoSelecionado.usa_patrimonio || patrimoniosDisponiveis.length > 0);

            if (usaPatrimonio && quantidadeNumero > patrimoniosDisponiveis.length) {
                notificar('warning', 'Atenção', `O produto ${nomeProduto} possui apenas ${patrimoniosDisponiveis.length} patrimônio(s) disponível(is).`);
                return false;
            }

            itensLoteEnvioMedicao.push({
                lote_id: `${Date.now()}_${Math.random().toString(36).slice(2, 8)}`,
                id_produto: Number(idProduto),
                produto_nome: nomeProduto,
                quantidade: quantidadeNumero,
                preco_unitario: precoNumerico,
                data_envio: normalizarDataBackend(dataEnvio),
                data_envio_local: (dataEnvio || '').split('-').reverse().join('/'),
                usa_patrimonio: usaPatrimonio,
                patrimonios_disponiveis: patrimoniosDisponiveis,
            });

            renderizarLoteEnvioMedicao();

            $('#movMedicaoProduto').val('');
            $('#movMedicaoQuantidade').val(1);
            $('#movMedicaoPreco').val('0,00');
            return true;
        }

        $('#btnAdicionarLoteMedicao').on('click', function () {
            adicionarItemLoteMedicao();
        });

        $('#formEnviarProdutoMedicao').on('submit', function (e) {
            e.preventDefault();
            adicionarItemLoteMedicao();
        });

        $(document).on('click', '.btn-remover-item-lote-medicao', function () {
            const index = Number($(this).data('index'));
            if (Number.isNaN(index) || index < 0 || index >= itensLoteEnvioMedicao.length) {
                return;
            }

            itensLoteEnvioMedicao.splice(index, 1);
            renderizarLoteEnvioMedicao();
        });

        $('#btnEnviarLoteMedicao').on('click', function () {
            if (!itensLoteEnvioMedicao.length) {
                notificar('warning', 'Atenção', 'Adicione pelo menos um item ao lote.');
                return;
            }

            const itensComPatrimonio = itensLoteEnvioMedicao.filter(function (item) {
                return !!item.usa_patrimonio;
            });

            if (!itensComPatrimonio.length) {
                enviarLoteMedicao(itensLoteEnvioMedicao);
                return;
            }

            renderizarModalPatrimoniosLote(itensComPatrimonio);
            if (modalPatrimoniosLote) {
                modalPatrimoniosLote.show();
            }
        });

        $('#btnConfirmarPatrimoniosLoteMedicao').on('click', function () {
            const itensPayload = itensLoteEnvioMedicao.map(function (item) {
                const payload = {
                    id_produto: item.id_produto,
                    quantidade: item.quantidade,
                    preco_unitario: item.preco_unitario,
                    data_envio: item.data_envio
                };

                if (item.usa_patrimonio) {
                    const selecionados = $(`.chk-patrimonio-lote-medicao[data-lote-id="${item.lote_id}"]:checked`).map(function () {
                        return $(this).val();
                    }).get();
                    payload.patrimonios = selecionados.map(function (idPatrimonio) {
                        return Number(idPatrimonio);
                    }).filter(function (idPatrimonio) {
                        return !Number.isNaN(idPatrimonio) && idPatrimonio > 0;
                    });
                }

                return payload;
            });

            const selecionadosGlobais = [];
            for (const item of itensPayload) {
                if (!Array.isArray(item.patrimonios) || !item.patrimonios.length) {
                    continue;
                }

                if (item.patrimonios.length !== Number(item.quantidade || 1)) {
                    notificar('warning', 'Atenção', 'Selecione a quantidade exata de patrimônios para cada produto com patrimônio.');
                    return;
                }

                selecionadosGlobais.push(...item.patrimonios);
            }

            const duplicados = selecionadosGlobais.filter(function (idPatrimonio, index, arr) {
                return arr.indexOf(idPatrimonio) !== index;
            });
            if (duplicados.length) {
                notificar('warning', 'Atenção', 'O mesmo patrimônio foi selecionado mais de uma vez no lote.');
                return;
            }

            const faltandoSelecao = itensPayload.some(function (item) {
                return (item.patrimonios !== undefined) && item.patrimonios.length === 0;
            });
            if (faltandoSelecao) {
                notificar('warning', 'Atenção', 'Selecione os patrimônios dos itens marcados.');
                return;
            }

            if (modalPatrimoniosLote) {
                modalPatrimoniosLote.hide();
            }
            enviarLoteMedicao(itensPayload);
        });

        $('#btnAtualizarItensMedicao').on('click', function () {
            const idLocacao = $('#movMedicaoLocacaoId').val();
            if (idLocacao) {
                carregarItensMovimentacao(idLocacao);
            }
        });

        $(document).on('change', '#chkSelecionarTodosRetornoMedicao', function () {
            const marcado = $(this).is(':checked');
            $('.chk-retorno-medicao').prop('checked', marcado);
            atualizarBotaoRetornoSelecionados();
        });

        $(document).on('change', '.chk-retorno-medicao', function () {
            const total = $('.chk-retorno-medicao').length;
            const selecionados = $('.chk-retorno-medicao:checked').length;
            $('#chkSelecionarTodosRetornoMedicao').prop('checked', total > 0 && total === selecionados);
            atualizarBotaoRetornoSelecionados();
        });

        $('#btnAbrirRetornoSelecionados').on('click', function () {
            const itensSelecionados = coletarItensSelecionadosRetorno();
            if (!itensSelecionados.length) {
                notificar('warning', 'Atenção', 'Selecione pelo menos um item para retorno.');
                return;
            }

            renderizarModalRetornoSelecionados(itensSelecionados);
            if (modalRetornoSelecionados) {
                modalRetornoSelecionados.show();
            }
        });

        $('#btnConfirmarRetornoSelecionadosMedicao').on('click', function () {
            const idLocacao = $('#movMedicaoLocacaoId').val();

            if (!idLocacao) {
                notificar('warning', 'Atenção', 'Contrato inválido para retorno.');
                return;
            }

            const itensRetorno = $('.input-retorno-item-medicao').map(function () {
                const $input = $(this);
                const idItem = Number($input.data('item'));
                const $inputQtd = $(`.input-qtd-retorno-item-medicao[data-item="${idItem}"]`);
                const quantidadeRetorno = $inputQtd.length
                    ? Number($inputQtd.val() || 0)
                    : 1;
                const quantidadeMaxima = Number($input.data('qtd-max') || 1);
                return {
                    id_item: idItem,
                    data_inicio: normalizarDataBackend($input.data('inicio') || ''),
                    data_retorno: normalizarDataBackend($input.val() || ''),
                    quantidade_retorno: quantidadeRetorno,
                    quantidade_maxima: quantidadeMaxima,
                };
            }).get();

            if (!itensRetorno.length) {
                notificar('warning', 'Atenção', 'Nenhum item selecionado para retorno.');
                return;
            }

            for (const item of itensRetorno) {
                if (!item.data_retorno) {
                    notificar('warning', 'Atenção', 'Informe a data de retorno para todos os itens.');
                    return;
                }

                if (item.data_inicio && item.data_retorno < item.data_inicio) {
                    notificar('warning', 'Atenção', 'A data de retorno não pode ser anterior à data de envio.');
                    return;
                }

                if (!Number.isFinite(item.quantidade_retorno) || item.quantidade_retorno < 1) {
                    notificar('warning', 'Atenção', 'Informe uma quantidade de retorno válida para todos os itens.');
                    return;
                }

                if (item.quantidade_retorno > Number(item.quantidade_maxima || 1)) {
                    notificar('warning', 'Atenção', 'A quantidade de retorno não pode ser maior que a enviada.');
                    return;
                }
            }

            const $btn = $(this);
            $btn.prop('disabled', true).html('<i class="ti ti-loader-2 me-1"></i>Processando...');

            const requisicoes = itensRetorno.map(function (item) {
                return $.ajax({
                    url: urlRetornarItemMovimentacao(idLocacao, item.id_item),
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        data_retorno: item.data_retorno,
                        quantidade_retorno: item.quantidade_retorno
                    }
                });
            });

            Promise.all(requisicoes)
                .then(function () {
                    if (modalRetornoSelecionados) {
                        modalRetornoSelecionados.hide();
                    }
                    fecharModalMovimentacao();
                    notificar('success', 'Sucesso!', 'Itens retornados com sucesso.').then(function () {
                        window.location.reload();
                    });
                })
                .catch(function (xhr) {
                    const erros = xhr?.responseJSON?.errors || {};
                    const primeiroErro = Object.values(erros)[0]?.[0] || null;
                    const mensagem = primeiroErro || xhr?.responseJSON?.message || 'Não foi possível retornar os itens selecionados.';
                    notificar('error', 'Erro!', mensagem);
                })
                .finally(function () {
                    $btn.prop('disabled', false).html('<i class="ti ti-check me-1"></i>Confirmar retornos');
                });
        });

        $(document).on('click', '.btn-editar-datas-item-medicao', function () {
            const idLocacao = $('#movMedicaoLocacaoId').val();
            const idItem = $(this).data('item');
            const dataInicioAtual = String($(this).data('inicio') || '');
            const dataRetornoAtual = String($(this).data('retorno') || '');
            const retornado = Number($(this).data('retornado') || 0) === 1;

            if (!idLocacao || !idItem) {
                return;
            }

            if (hasSwal) {
                const blocoRetorno = retornado
                    ? `
                            <label class="form-label mb-1 mt-2">Data de retorno</label>
                            <input type="date" id="swalEditarDataRetornoMedicao" class="swal2-input mt-0" value="${dataRetornoAtual || ''}">
                      `
                    : '';

                Swal.fire({
                    title: 'Editar datas do item',
                    icon: 'question',
                    html: `
                        <div class="text-start">
                            <label class="form-label mb-1">Data de envio</label>
                            <input type="date" id="swalEditarDataInicioMedicao" class="swal2-input mt-0" value="${dataInicioAtual || obterDataLocalHoje()}">
                            ${blocoRetorno}
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Salvar datas',
                    cancelButtonText: 'Cancelar',
                    preConfirm: () => {
                        const dataInicio = String(document.getElementById('swalEditarDataInicioMedicao')?.value || '').trim();
                        const dataRetorno = retornado
                            ? String(document.getElementById('swalEditarDataRetornoMedicao')?.value || '').trim()
                            : '';

                        if (!dataInicio) {
                            Swal.showValidationMessage('Informe a data de envio.');
                            return false;
                        }

                        if (retornado && !dataRetorno) {
                            Swal.showValidationMessage('Informe a data de retorno.');
                            return false;
                        }

                        if (retornado && dataRetorno < dataInicio) {
                            Swal.showValidationMessage('A data de retorno não pode ser anterior à data de envio.');
                            return false;
                        }

                        return {
                            data_inicio: dataInicio,
                            data_retorno: retornado ? dataRetorno : ''
                        };
                    }
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        return;
                    }

                    const payload = result.value || {};

                    $.ajax({
                        url: urlEditarDatasItemMovimentacao(idLocacao, idItem),
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            data_inicio: normalizarDataBackend(payload.data_inicio || ''),
                            data_retorno: normalizarDataBackend(payload.data_retorno || '')
                        },
                        success: function (response) {
                            fecharModalMovimentacao();
                            notificar('success', 'Sucesso!', response.message || 'Datas atualizadas.').then(function () {
                                window.location.reload();
                            });
                        },
                        error: function (xhr) {
                            const erros = xhr.responseJSON?.errors || {};
                            const primeiroErro = Object.values(erros)[0]?.[0] || null;
                            const mensagem = primeiroErro || xhr.responseJSON?.message || 'Não foi possível editar as datas.';
                            notificar('error', 'Erro!', mensagem);
                        }
                    });
                });
                return;
            }

            const dataInicio = window.prompt('Informe a data de envio (YYYY-MM-DD)', dataInicioAtual || obterDataLocalHoje());
            if (!dataInicio) return;

            let dataRetorno = '';
            if (retornado) {
                dataRetorno = window.prompt('Informe a data de retorno (YYYY-MM-DD)', dataRetornoAtual || '');
                if (!dataRetorno) return;
            }

            $.ajax({
                url: urlEditarDatasItemMovimentacao(idLocacao, idItem),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    data_inicio: normalizarDataBackend(dataInicio),
                    data_retorno: normalizarDataBackend(dataRetorno)
                },
                success: function (response) {
                    fecharModalMovimentacao();
                    notificar('success', 'Sucesso!', response.message || 'Datas atualizadas.').then(function () {
                        window.location.reload();
                    });
                },
                error: function (xhr) {
                    const erros = xhr.responseJSON?.errors || {};
                    const primeiroErro = Object.values(erros)[0]?.[0] || null;
                    const mensagem = primeiroErro || xhr.responseJSON?.message || 'Não foi possível editar as datas.';
                    notificar('error', 'Erro!', mensagem);
                }
            });
        });

        $('#btnAtualizarRelatorioMedicao').on('click', carregarRelatorioMedicao);

        $('#formFaturamentoMedicao').on('submit', function (e) {
            e.preventDefault();
            const action = $('#fatMedicaoAction').val();
            const idLocacao = $('#fatMedicaoLocacaoId').val();
            const codigo = $('#fatMedicaoContratoInfo').text().replace('Contrato:', '').trim() || idLocacao || 'este contrato';

            if (!action) {
                notificar('error', 'Erro!', 'Não foi possível identificar a rota de faturamento.');
                return;
            }

            const payload = {
                _token: '{{ csrf_token() }}',
                periodo_inicio: $('#fatMedicaoPeriodoInicio').val(),
                periodo_fim: $('#fatMedicaoPeriodoFim').val(),
                observacoes: $('#fatMedicaoObservacoes').val() || ''
            };

            const parcelar = $('#fatMedicaoParcelar').is(':checked');
            if (parcelar) {
                const quantidade = Math.max(2, parseInt($('#fatMedicaoQtdParcelas').val() || '2', 10));
                const parcelas = [];

                $('#fatMedicaoParcelasTabela tbody .fat-medicao-parcela-data').each(function () {
                    parcelas.push({ data_vencimento: $(this).val() || '' });
                });

                if (parcelas.length < quantidade || parcelas.some(function (p) { return !p.data_vencimento; })) {
                    notificar('warning', 'Atenção', 'Preencha as datas de vencimento de todas as parcelas.');
                    return;
                }

                payload.parcelar = 1;
                payload.quantidade_parcelas = quantidade;
                payload.parcelas = parcelas;
            } else {
                const dataVencimento = $('#fatMedicaoDataVencimento').val();
                if (!dataVencimento) {
                    notificar('warning', 'Atenção', 'Informe a data de vencimento.');
                    return;
                }
                payload.parcelar = 0;
                payload.data_vencimento = dataVencimento;
            }

            confirmarAcao('Confirmar faturamento', `Deseja faturar o período em aberto de ${codigo}?`, 'Sim, faturar').then(function (confirmado) {
                if (!confirmado) return;

                $.ajax({
                    url: action,
                    method: 'POST',
                    data: payload,
                    success: function (response) {
                        if (modalFaturamento) {
                            modalFaturamento.hide();
                        }
                        notificar('success', 'Sucesso!', response.message || 'Faturamento realizado.').then(function () {
                            window.location.reload();
                        });
                    },
                    error: function (xhr) {
                        const erros = xhr.responseJSON?.errors || {};
                        const primeiroErro = Object.values(erros)[0]?.[0] || null;
                        const mensagem = primeiroErro || xhr.responseJSON?.message || 'Não foi possível faturar agora.';
                        notificar('error', 'Erro!', mensagem);
                    }
                });
            });
        });
    })();
</script>
@endsection
