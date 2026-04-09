@extends('layouts.layoutMaster')

@section('title', 'Contratos de Locação')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('page-style')
<style>
    .locacoes-nav-principal .btn {
        min-width: 120px;
    }

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

    .locacoes-abas-status .nav-link.aba-ativos.active {
        background: rgba(113, 221, 55, .15);
        border-color: rgba(113, 221, 55, .35);
        color: #2d7a1f;
    }

    .locacoes-abas-status .badge {
        font-size: .72rem;
    }

    .locacoes-table th {
        white-space: nowrap;
    }

    .cards-resumo-contratos .card {
        border: 1px solid #e9edf3;
        transition: all .15s ease;
    }

    .cards-util-contratos .card {
        border: 1px solid #e9edf3;
        border-radius: .55rem;
    }

    .cards-util-contratos .topo {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: .75rem;
    }

    .cards-util-contratos .titulo {
        font-size: .78rem;
        font-weight: 600;
        color: #697a8d;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .cards-util-contratos .valor {
        font-size: 1rem;
        font-weight: 700;
        color: #566a7f;
    }

    .cards-util-contratos .meta {
        font-size: .78rem;
        color: #8d95a5;
    }

    .cards-util-contratos .icone {
        width: 2.4rem;
        height: 2.4rem;
        border-radius: .45rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .cards-util-contratos .icone.icone-primary {
        color: #696cff;
        background: rgba(105, 108, 255, .16);
    }

    .cards-util-contratos .icone.icone-success {
        color: #2a8a34;
        background: rgba(113, 221, 55, .20);
    }

    .cards-util-contratos .icone.icone-warning {
        color: #c36e00;
        background: rgba(255, 171, 0, .20);
    }

    .cards-util-contratos .icone.icone-info {
        color: #03a9c6;
        background: rgba(3, 195, 236, .18);
    }

    .cards-resumo-contratos .card.card-aba-ativa {
        border-color: rgba(105, 108, 255, .35);
        box-shadow: 0 0.2rem 0.65rem rgba(67, 89, 113, .12);
    }

    .cards-resumo-contratos .titulo {
        font-size: .78rem;
        font-weight: 600;
        color: #697a8d;
        text-transform: uppercase;
        letter-spacing: .3px;
    }

    .cards-resumo-contratos .valor {
        font-size: 1rem;
        font-weight: 700;
        color: #566a7f;
    }

    .cards-resumo-contratos .meta {
        font-size: .78rem;
        color: #8d95a5;
    }

    .locacoes-table td.col-codigo,
    .locacoes-table td.col-valor,
    .locacoes-table td.col-status,
    .locacoes-table td.col-editar,
    .locacoes-table td.col-acoes {
        white-space: nowrap;
        vertical-align: middle;
    }

    .locacoes-table td.col-periodo-resumo {
        min-width: 220px;
        vertical-align: middle;
        line-height: 1.25;
    }

    .locacoes-table td.col-periodo-resumo .linha {
        white-space: nowrap;
        font-size: .8rem;
    }

    .locacoes-table td.col-periodo-resumo .linha.meta {
        color: #8a8d95;
        font-weight: 600;
    }

    .locacoes-table td.col-cliente {
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

    .tabela-itens-locacao {
        border: 1px solid #ebeef0;
        border-radius: .5rem;
        overflow: hidden;
        margin-top: .6rem;
    }

    .tabela-itens-locacao table {
        margin-bottom: 0;
    }

    .tabela-itens-locacao th {
        background: #f3f5f8;
        font-size: .72rem;
        text-transform: uppercase;
        color: #697a8d;
        white-space: nowrap;
    }

    .tabela-itens-locacao td {
        font-size: .82rem;
        vertical-align: middle;
    }

    .resumo-financeiro-locacao {
        border: 1px solid #ebeef0;
        border-radius: .5rem;
        padding: .65rem .75rem;
        margin-top: .55rem;
        background: #fbfcfe;
    }

    .resumo-financeiro-locacao .linha {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: .82rem;
        color: #697a8d;
        margin-bottom: .2rem;
    }

    .resumo-financeiro-locacao .linha:last-child {
        margin-bottom: 0;
    }

    .resumo-financeiro-locacao .linha.total {
        font-weight: 700;
        color: #566a7f;
        border-top: 1px solid #e4e8ed;
        padding-top: .35rem;
        margin-top: .25rem;
    }

    .btn-icon-acao {
        width: 34px;
        height: 34px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    #modalDefeitoRetorno .table-responsive {
        overflow-x: auto;
    }

    #modalDefeitoRetorno #tabelaDefeitoRetorno {
        min-width: 1400px;
    }

    #modalDefeitoRetorno #tabelaDefeitoRetorno th {
        white-space: normal;
        line-height: 1.15;
        vertical-align: middle;
    }

    #modalDefeitoRetorno #tabelaDefeitoRetorno td {
        vertical-align: top;
        white-space: normal;
    }

    #modalDefeitoRetorno #tabelaDefeitoRetorno .form-control,
    #modalDefeitoRetorno #tabelaDefeitoRetorno .form-select {
        min-width: 130px;
    }

    @media (max-width: 767.98px) {
        .locacoes-nav-principal,
        .locacoes-top-actions {
            width: 100%;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            padding-bottom: .2rem;
        }

        .locacoes-nav-principal .btn,
        .locacoes-top-actions .btn {
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

    html.dark-style .locacoes-abas-status .nav-link {
        background: #2f3349;
        border-color: #444b6e;
        color: #d8deff;
    }

    html.dark-style .locacoes-abas-status .nav-link.active {
        background: rgba(105, 108, 255, .22);
        border-color: rgba(105, 108, 255, .42);
        color: #a7b3ff;
    }

    html.dark-style .locacoes-abas-status .nav-link.aba-ativos.active {
        background: rgba(113, 221, 55, .22);
        border-color: rgba(113, 221, 55, .42);
        color: #9ce685;
    }

    html.dark-style .cards-util-contratos .card,
    html.dark-style .cards-resumo-contratos .card,
    html.dark-style .painel-acoes-linha,
    html.dark-style .tabela-itens-locacao,
    html.dark-style .resumo-financeiro-locacao {
        border-color: #444b6e;
    }

    html.dark-style .painel-acoes-linha,
    html.dark-style .resumo-financeiro-locacao {
        background: #2b3046;
    }

    html.dark-style .tabela-itens-locacao th {
        background: #25293c;
        color: #d8deff;
    }

    html.dark-style .cards-util-contratos .titulo,
    html.dark-style .cards-util-contratos .valor,
    html.dark-style .cards-util-contratos .meta,
    html.dark-style .cards-resumo-contratos .titulo,
    html.dark-style .cards-resumo-contratos .valor,
    html.dark-style .cards-resumo-contratos .meta,
    html.dark-style .painel-acoes-info .titulo,
    html.dark-style .painel-acoes-info .detalhes,
    html.dark-style .resumo-financeiro-locacao .linha {
        color: #d8deff;
    }
</style>
@endsection

@section('content')
@php
    $podeCriarLocacao = \Perm::pode(auth()->user(), 'locacoes.criar');
    $podeEditarLocacao = \Perm::pode(auth()->user(), 'locacoes.editar');
    $podeExpedicaoLocacao = \Perm::pode(auth()->user(), 'expedicao.logistica.visualizar');
    $podeContratoPdfLocacao = \Perm::pode(auth()->user(), 'locacoes.contrato-pdf');
    $podeAssinaturaDigitalLocacao = \Perm::pode(auth()->user(), 'locacoes.assinatura-digital');
    $podeAlterarStatusLocacao = \Perm::pode(auth()->user(), 'locacoes.alterar-status');
    $podeRetornarLocacao = \Perm::pode(auth()->user(), 'locacoes.retornar');
    $podeRenovarLocacao = \Perm::pode(auth()->user(), 'locacoes.renovar');
@endphp
<div class="container-xxl flex-grow-1">
    @php
        $qtdTotal = (int) ($abasContagem['todos'] ?? 0);
        $qtdAtivos = (int) ($abasContagem['ativos'] ?? 0);
        $qtdVencidos = (int) ($abasContagem['vencidos'] ?? 0);
        $qtdFuturos = (int) ($abasContagem['futuros'] ?? 0);
        $qtdEncerrados = (int) ($abasContagem['encerrados'] ?? 0);

        $valorTotalCarteira = (float) ($abasValores['todos'] ?? 0);
        $valorAtivos = (float) ($abasValores['ativos'] ?? 0);
        $valorEncerrados = (float) ($abasValores['encerrados'] ?? 0);

        $percentualAtivos = $qtdTotal > 0 ? ($qtdAtivos / $qtdTotal) * 100 : 0;
        $percentualEncerrados = $qtdTotal > 0 ? ($qtdEncerrados / $qtdTotal) * 100 : 0;
    @endphp

    <div class="row g-3 mb-3 cards-util-contratos">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">R$ {{ number_format($valorTotalCarteira, 2, ',', '.') }}</div>
                            <div class="meta">{{ $qtdTotal }} contratos no total</div>
                        </div>
                        <span class="icone icone-primary"><i class="ti ti-wallet"></i></span>
                    </div>
                    <div class="titulo">Carteira Total</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">R$ {{ number_format($valorAtivos, 2, ',', '.') }}</div>
                            <div class="meta">{{ $qtdAtivos }} ativos ({{ number_format($percentualAtivos, 2, ',', '.') }}%)</div>
                        </div>
                        <span class="icone icone-success"><i class="ti ti-check"></i></span>
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
                            <div class="valor">{{ $qtdVencidos + $qtdFuturos }}</div>
                            <div class="meta">{{ $qtdVencidos }} vencidos • {{ $qtdFuturos }} futuros</div>
                        </div>
                        <span class="icone icone-warning"><i class="ti ti-alert-triangle"></i></span>
                    </div>
                    <div class="titulo">Atenção de Prazo</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card h-100">
                <div class="card-body py-2 px-3">
                    <div class="topo mb-1">
                        <div>
                            <div class="valor">R$ {{ number_format($valorEncerrados, 2, ',', '.') }}</div>
                            <div class="meta">{{ $qtdEncerrados }} encerrados ({{ number_format($percentualEncerrados, 2, ',', '.') }}%)</div>
                        </div>
                        <span class="icone icone-info"><i class="ti ti-archive"></i></span>
                    </div>
                    <div class="titulo">Encerrados</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="d-flex gap-2 locacoes-nav-principal">
                <a href="{{ route('locacoes.contratos') }}" class="btn btn-primary">Contratos</a>
                <a href="{{ route('locacoes.orcamentos') }}" class="btn btn-outline-secondary">Orçamentos</a>
                <a href="{{ route('locacoes.medicoes') }}" class="btn btn-outline-secondary">Medições</a>
            </div>
            <div class="d-flex gap-2 locacoes-top-actions">
                @if($podeContratoPdfLocacao)
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-file-analytics me-1"></i>Relatórios PDF
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contratos.relatorio-pdf', array_merge(request()->query(), ['aba' => $aba, 'tipo' => 'carteira'])) }}">
                                <i class="ti ti-layout-list me-2"></i>Carteira de Contratos
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contratos.relatorio-pdf', array_merge(request()->query(), ['aba' => $aba, 'tipo' => 'agenda'])) }}">
                                <i class="ti ti-calendar-time me-2"></i>Agenda de Vencimentos
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contratos.relatorio-pdf', array_merge(request()->query(), ['aba' => $aba, 'tipo' => 'lucratividade'])) }}">
                                <i class="ti ti-chart-line me-2"></i>Lucratividade por Contrato
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contratos.relatorio-pdf', array_merge(request()->query(), ['aba' => $aba, 'tipo' => 'filtros'])) }}">
                                <i class="ti ti-filter-search me-2"></i>Locações por Filtros
                            </a>
                        </li>
                    </ul>
                </div>
                @endif
                <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#modalFiltrosContratos">
                    <i class="ti ti-filter me-1"></i>Filtros
                </button>
                @if($podeExpedicaoLocacao)
                    <a href="{{ route('locacoes.expedicao') }}" class="btn btn-outline-primary">
                        <i class="ti ti-truck-delivery me-1"></i>Expedição
                    </a>
                @endif
                @if($podeCriarLocacao)
                    <a href="{{ route('locacoes.create', ['aba' => $aba, 'origem' => 'contratos', 'status' => 'aprovado']) }}" class="btn btn-primary"><i class="ti ti-plus me-1"></i>Novo Contrato</a>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        @php
            $totalGeral = (float) ($abasValores['todos'] ?? 0);
            $totalBase = $totalGeral > 0 ? $totalGeral : 1;
            $cardsAba = [
                'ativos' => ['label' => 'Ativos', 'badge' => 'success'],
                'vencidos' => ['label' => 'Vencidos', 'badge' => 'danger'],
                'futuros' => ['label' => 'Futuros', 'badge' => 'warning'],
                'encerrados' => ['label' => 'Encerrados', 'badge' => 'info'],
                'todos' => ['label' => 'Todos', 'badge' => 'secondary'],
            ];
        @endphp

        <div class="card-body pb-1">
            <div class="row g-3 cards-resumo-contratos">
                @foreach($cardsAba as $key => $card)
                    @php
                        $valorAba = (float) ($abasValores[$key] ?? 0);
                        $qtdAba = (int) ($abasContagem[$key] ?? 0);
                        $percentual = min(100, max(0, round(($valorAba / $totalBase) * 100, 2)));
                    @endphp
                    <div class="col-12 col-sm-6 col-lg">
                        <a href="{{ route('locacoes.contratos', array_merge(request()->except('page'), ['aba' => $key])) }}" class="text-decoration-none">
                            <div class="card h-100 {{ $aba === $key ? 'card-aba-ativa' : '' }}">
                                <div class="card-body py-2 px-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="titulo">{{ $card['label'] }}</span>
                                        <span class="badge bg-label-{{ $card['badge'] }}">{{ $qtdAba }}</span>
                                    </div>
                                    <div class="valor mb-1">R$ {{ number_format($valorAba, 2, ',', '.') }}</div>
                                    <div class="progress" style="height:5px;">
                                        <div class="progress-bar bg-{{ $card['badge'] }}" role="progressbar" style="width: {{ $percentual }}%"></div>
                                    </div>
                                    <div class="meta mt-1">{{ number_format($percentual, 2, ',', '.') }}% do total</div>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card-header pb-0">
            <ul class="nav nav-pills nav-fill locacoes-abas-status">
                <li class="nav-item"><a class="nav-link aba-ativos {{ $aba === 'ativos' ? 'active' : '' }}" href="{{ route('locacoes.contratos', array_merge(request()->except('page'), ['aba' => 'ativos'])) }}">Ativos <span class="badge bg-label-success ms-1">{{ $abasContagem['ativos'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $aba === 'vencidos' ? 'active' : '' }}" href="{{ route('locacoes.contratos', array_merge(request()->except('page'), ['aba' => 'vencidos'])) }}">Vencidos <span class="badge bg-label-danger ms-1">{{ $abasContagem['vencidos'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $aba === 'futuros' ? 'active' : '' }}" href="{{ route('locacoes.contratos', array_merge(request()->except('page'), ['aba' => 'futuros'])) }}">Futuros <span class="badge bg-label-warning ms-1">{{ $abasContagem['futuros'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $aba === 'encerrados' ? 'active' : '' }}" href="{{ route('locacoes.contratos', array_merge(request()->except('page'), ['aba' => 'encerrados'])) }}">Encerrados <span class="badge bg-label-info ms-1">{{ $abasContagem['encerrados'] ?? 0 }}</span></a></li>
                <li class="nav-item"><a class="nav-link {{ $aba === 'todos' ? 'active' : '' }}" href="{{ route('locacoes.contratos', array_merge(request()->except('page'), ['aba' => 'todos'])) }}">Todos <span class="badge bg-label-secondary ms-1">{{ $abasContagem['todos'] ?? 0 }}</span></a></li>
            </ul>
        </div>
        <div class="card-body pt-3">
            <div class="table-responsive">
                <table class="table table-hover locacoes-table align-middle">
                    <thead>
                        <tr>
                            <th style="width: 70px">Ações</th>
                            <th style="width: 70px">Editar</th>
                            <th style="width: 95px">Código</th>
                            <th style="width: 85px">Aditivo</th>
                            <th style="width: 95px">Faturado</th>
                            <th style="min-width: 260px">Cliente</th>
                            <th style="width: 230px">Período</th>
                            <th style="width: 120px">Status</th>
                            <th style="width: 120px">Auto-renovação</th>
                            <th style="width: 140px">Valor total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locacoes as $locacao)
                            @php
                                $statusCor = [
                                    'orcamento' => 'secondary',
                                    'aprovado' => 'success',
                                    'cancelado' => 'danger',
                                    'cancelada' => 'danger',
                                    'encerrado' => 'info',
                                ][$locacao->status] ?? 'secondary';

                                $venceHoje = $aba === 'ativos'
                                    && optional($locacao->data_fim)->format('Y-m-d') === $agora->format('Y-m-d');

                                $temPatrimonioPendente = $locacao->produtos()
                                    ->whereNotNull('id_patrimonio')
                                    ->where(function($q){
                                        $q->whereNull('status_retorno')->orWhere('status_retorno', 'pendente');
                                    })->exists();

                                $qtdPeriodo = (int) ($locacao->quantidade_dias ?? 0);
                                $unidadePeriodo = 'dia(s)';
                                $dataInicioPeriodo = optional($locacao->data_inicio)->format('Y-m-d');
                                $dataFimPeriodo = optional($locacao->data_fim)->format('Y-m-d');
                                $horaInicioPeriodo = (string) ($locacao->hora_inicio ?? '');
                                $horaFimPeriodo = (string) ($locacao->hora_fim ?? '');

                                if (
                                    $dataInicioPeriodo
                                    && $dataFimPeriodo
                                    && !empty($horaInicioPeriodo)
                                    && !empty($horaFimPeriodo)
                                ) {
                                    $inicioHora = \Carbon\Carbon::parse($dataInicioPeriodo . ' ' . $locacao->hora_inicio);
                                    $fimHora = \Carbon\Carbon::parse($dataFimPeriodo . ' ' . $locacao->hora_fim);
                                    $diasInclusivos = max(1, $inicioHora->copy()->startOfDay()->diffInDays($fimHora->copy()->startOfDay()) + 1);
                                    $ehPorHora = $dataInicioPeriodo === $dataFimPeriodo
                                        || $qtdPeriodo > $diasInclusivos;

                                    if ($ehPorHora && $fimHora->gte($inicioHora)) {
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
                                        data-target="#acoes-locacao-{{ $locacao->id_locacao }}"
                                        title="Abrir ações"
                                        aria-label="Abrir ações"
                                    >
                                        <i class="ti ti-chevron-down"></i>
                                    </button>
                                </td>
                                <td class="col-editar">
                                    @if($podeEditarLocacao)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('locacoes.edit', ['locacao' => $locacao->id_locacao, 'aba' => $aba]) }}"><i class="ti ti-pencil"></i></a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td class="col-codigo"><strong>{{ $locacao->codigo_display }}</strong></td>
                                <td><span class="badge bg-label-secondary">{{ (int) ($locacao->aditivo ?? 1) }}</span></td>
                                <td>
                                    @if((int) ($locacao->faturamentos_ativos_count ?? 0) > 0)
                                        <span class="badge bg-label-success">Sim</span>
                                    @else
                                        <span class="badge bg-label-danger">Não</span>
                                    @endif
                                </td>
                                <td class="col-cliente">{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                                <td class="col-periodo-resumo">
                                    <div class="linha {{ $venceHoje ? 'text-danger fw-bold' : '' }}">{{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio,0,5) : '' }} <span class="text-muted">→</span> {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ? substr($locacao->hora_fim,0,5) : '' }}</div>
                                    <div class="linha meta">{{ $qtdPeriodo }} {{ $unidadePeriodo }}</div>
                                </td>
                                <td class="col-status"><span class="badge bg-label-{{ $statusCor }}">{{ \App\Domain\Locacao\Models\Locacao::statusList()[$locacao->status] ?? $locacao->status }}</span></td>
                                <td>
                                    @if((bool) ($locacao->renovacao_automatica ?? false))
                                        <span class="badge bg-label-success">Sim</span>
                                    @else
                                        <span class="badge bg-label-secondary">Não</span>
                                    @endif
                                </td>
                                <td class="col-valor"><strong>R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}</strong></td>
                            </tr>
                            <tr id="acoes-locacao-{{ $locacao->id_locacao }}" class="d-none linha-acoes-locacao">
                                <td colspan="10">
                                    <div class="painel-acoes-linha">
                                        <div class="painel-acoes-info">
                                            <div class="titulo">Contrato {{ $locacao->codigo_display }}</div>
                                            <div class="detalhes">
                                                {{ $locacao->cliente->nome ?? 'N/A' }}
                                                • {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio,0,5) : '' }}
                                                até {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ? substr($locacao->hora_fim,0,5) : '' }}
                                                • R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}
                                            </div>
                                                @if($locacao->assinaturaDigital)
                                                    <div class="mt-2">
                                                        <span class="badge bg-label-{{ ($locacao->assinaturaDigital->status ?? '') === 'assinado' ? 'success' : 'warning' }}">
                                                            {{ ($locacao->assinaturaDigital->status ?? '') === 'assinado' ? 'Contrato assinado' : 'Contrato pendente de assinatura' }}
                                                        </span>
                                                    </div>
                                                @endif
                                        </div>

                                        <div class="painel-acoes-botoes">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-secondary btn-icon-acao btn-log-atividades-locacao"
                                            data-id="{{ $locacao->id_locacao }}"
                                            data-codigo="{{ $locacao->codigo_display }}"
                                            data-cliente="{{ $locacao->cliente->nome ?? '' }}"
                                            data-bs-toggle="tooltip"
                                            title="Log de Atividades"
                                        ><i class="ti ti-activity"></i></button>
                                        @if($locacao->status === 'aprovado')
                                            @php
                                                $modelosContratoDropdown = $modelosContratoAtivos;
                                                $assinaturasContratoAssinadas = ($locacao->assinaturasDigitais ?? collect())
                                                    ->filter(function ($assinaturaContrato) {
                                                        return ($assinaturaContrato->status ?? '') === 'assinado' && !empty($assinaturaContrato->token);
                                                    })
                                                    ->sortByDesc('id_assinatura')
                                                    ->values();
                                                $assinaturaContratoPadrao = $assinaturasContratoAssinadas->first(function ($assinaturaContrato) {
                                                    return empty($assinaturaContrato->id_modelo);
                                                }) ?: $assinaturasContratoAssinadas->first();
                                            @endphp
                                            @if($podeContratoPdfLocacao || $podeAssinaturaDigitalLocacao)
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-primary btn-icon-acao" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Contrato">
                                                    <i class="ti ti-printer"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($modelosContratoDropdown->count() > 0)
                                                        @foreach($modelosContratoDropdown as $modeloContratoItem)
                                                            <li>
                                                                <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato&id_modelo={{ $modeloContratoItem->id_modelo }}">
                                                                    Imprimir contrato - {{ $modeloContratoItem->nome }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    @else
                                                        <li>
                                                            <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato">
                                                                Imprimir contrato
                                                            </a>
                                                        </li>
                                                    @endif
                                                    <li><hr class="dropdown-divider"></li>
                                                    @if($modelosContratoDropdown->count() > 0)
                                                        @foreach($modelosContratoDropdown as $modeloContratoItem)
                                                            @php
                                                                $assinaturaModeloAssinado = $assinaturasContratoAssinadas->first(function ($assinaturaContrato) use ($modeloContratoItem) {
                                                                    return (int) ($assinaturaContrato->id_modelo ?? 0) === (int) $modeloContratoItem->id_modelo;
                                                                });
                                                            @endphp
                                                            <li>
                                                                @if($assinaturaModeloAssinado)
                                                                    @if($podeAssinaturaDigitalLocacao)
                                                                        <a class="dropdown-item" target="_blank" href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinaturaModeloAssinado->token, 'tipo' => 'contrato', 'id_modelo' => $modeloContratoItem->id_modelo]) }}">
                                                                            Imprimir assinado - {{ $modeloContratoItem->nome }}
                                                                        </a>
                                                                    @endif
                                                                @else
                                                                    @if($podeAssinaturaDigitalLocacao)
                                                                        <a class="dropdown-item" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}?id_modelo={{ $modeloContratoItem->id_modelo }}">
                                                                            Enviar pra assinatura - {{ $modeloContratoItem->nome }}
                                                                        </a>
                                                                    @endif
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    @else
                                                        <li>
                                                            @if($assinaturaContratoPadrao)
                                                                @if($podeAssinaturaDigitalLocacao)
                                                                    <a class="dropdown-item" target="_blank" href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinaturaContratoPadrao->token, 'tipo' => 'contrato', 'id_modelo' => $assinaturaContratoPadrao->id_modelo]) }}">
                                                                        Imprimir assinado - Contrato
                                                                    </a>
                                                                @endif
                                                            @else
                                                                @if($podeAssinaturaDigitalLocacao)
                                                                    <a class="dropdown-item" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}">
                                                                        Enviar pra assinatura
                                                                    </a>
                                                                @endif
                                                            @endif
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            @if($podeContratoPdfLocacao)
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=checklist" data-bs-toggle="tooltip" title="Imprimir Checklist"><i class="ti ti-clipboard-check"></i></a>
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=entrega" data-bs-toggle="tooltip" title="Comprovante de Entrega"><i class="ti ti-truck-delivery"></i></a>
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=romaneio" data-bs-toggle="tooltip" title="Imprimir Romaneio"><i class="ti ti-list-details"></i></a>
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=recibo" data-bs-toggle="tooltip" title="Imprimir Recibo"><i class="ti ti-receipt-2"></i></a>
                                            @endif
                                            @if($podeAlterarStatusLocacao)
                                                <button type="button" class="btn btn-sm btn-outline-warning btn-icon-acao btn-alterar-status" data-id="{{ $locacao->id_locacao }}" data-status="orcamento" data-label="voltar para orçamento" data-bs-toggle="tooltip" title="Voltar para Orçamento"><i class="ti ti-rotate-2"></i></button>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon-acao btn-alterar-status" data-id="{{ $locacao->id_locacao }}" data-status="cancelado" data-label="cancelar contrato" data-bs-toggle="tooltip" title="Cancelar Contrato"><i class="ti ti-ban"></i></button>
                                            @endif
                                            @if($podeRenovarLocacao)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-warning btn-icon-acao btn-renovar-aditivo"
                                                    data-id="{{ $locacao->id_locacao }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Renovar Locação"
                                                ><i class="ti ti-refresh"></i></button>
                                            @endif
                                            @if(in_array($aba, ['ativos', 'vencidos'], true))
                                                @if($podeRetornarLocacao)
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-info btn-icon-acao btn-retorno-parcial"
                                                    data-id="{{ $locacao->id_locacao }}"
                                                    data-bs-toggle="tooltip"
                                                    title="Retorno Parcial"
                                                ><i class="ti ti-arrow-back"></i></button>
                                                @endif
                                            @endif
                                            @if($podeRetornarLocacao)
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon-acao btn-retornar-locacao" data-id="{{ $locacao->id_locacao }}" data-tem-patrimonio="{{ $temPatrimonioPendente ? 1 : 0 }}" data-bs-toggle="tooltip" title="Retornar Locação"><i class="ti ti-arrow-back-up"></i></button>
                                            @endif
                                        @elseif(in_array($locacao->status, ['cancelado', 'cancelada'], true))
                                            @if($podeAlterarStatusLocacao)
                                                <button type="button" class="btn btn-sm btn-outline-success btn-icon-acao btn-alterar-status" data-id="{{ $locacao->id_locacao }}" data-status="aprovado" data-label="reativar contrato" data-bs-toggle="tooltip" title="Reativar Contrato"><i class="ti ti-player-play"></i></button>
                                            @endif
                                        @elseif($locacao->status === 'encerrado')
                                            @php
                                                $modelosContratoDropdown = $modelosContratoAtivos;
                                                $assinaturasContratoAssinadas = ($locacao->assinaturasDigitais ?? collect())
                                                    ->filter(function ($assinaturaContrato) {
                                                        return ($assinaturaContrato->status ?? '') === 'assinado' && !empty($assinaturaContrato->token);
                                                    })
                                                    ->sortByDesc('id_assinatura')
                                                    ->values();
                                                $assinaturaContratoPadrao = $assinaturasContratoAssinadas->first(function ($assinaturaContrato) {
                                                    return empty($assinaturaContrato->id_modelo);
                                                }) ?: $assinaturasContratoAssinadas->first();
                                            @endphp
                                            @if($podeContratoPdfLocacao || $podeAssinaturaDigitalLocacao)
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-primary btn-icon-acao" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Contrato">
                                                    <i class="ti ti-printer"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($modelosContratoDropdown->count() > 0)
                                                        @foreach($modelosContratoDropdown as $modeloContratoItem)
                                                            <li>
                                                                <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato&id_modelo={{ $modeloContratoItem->id_modelo }}">
                                                                    Imprimir contrato - {{ $modeloContratoItem->nome }}
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    @else
                                                        <li>
                                                            <a class="dropdown-item" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato">
                                                                Imprimir contrato
                                                            </a>
                                                        </li>
                                                    @endif
                                                    <li><hr class="dropdown-divider"></li>
                                                    @if($modelosContratoDropdown->count() > 0)
                                                        @foreach($modelosContratoDropdown as $modeloContratoItem)
                                                            @php
                                                                $assinaturaModeloAssinado = $assinaturasContratoAssinadas->first(function ($assinaturaContrato) use ($modeloContratoItem) {
                                                                    return (int) ($assinaturaContrato->id_modelo ?? 0) === (int) $modeloContratoItem->id_modelo;
                                                                });
                                                            @endphp
                                                            <li>
                                                                @if($assinaturaModeloAssinado)
                                                                    @if($podeAssinaturaDigitalLocacao)
                                                                        <a class="dropdown-item" target="_blank" href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinaturaModeloAssinado->token, 'tipo' => 'contrato', 'id_modelo' => $modeloContratoItem->id_modelo]) }}">
                                                                            Imprimir assinado - {{ $modeloContratoItem->nome }}
                                                                        </a>
                                                                    @endif
                                                                @else
                                                                    @if($podeAssinaturaDigitalLocacao)
                                                                        <a class="dropdown-item" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}?id_modelo={{ $modeloContratoItem->id_modelo }}">
                                                                            Enviar pra assinatura - {{ $modeloContratoItem->nome }}
                                                                        </a>
                                                                    @endif
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    @else
                                                        <li>
                                                            @if($assinaturaContratoPadrao)
                                                                @if($podeAssinaturaDigitalLocacao)
                                                                    <a class="dropdown-item" target="_blank" href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinaturaContratoPadrao->token, 'tipo' => 'contrato', 'id_modelo' => $assinaturaContratoPadrao->id_modelo]) }}">
                                                                        Imprimir assinado - Contrato
                                                                    </a>
                                                                @endif
                                                            @else
                                                                @if($podeAssinaturaDigitalLocacao)
                                                                    <a class="dropdown-item" href="{{ route('locacoes.enviar-assinatura-digital', $locacao->id_locacao) }}">
                                                                        Enviar pra assinatura
                                                                    </a>
                                                                @endif
                                                            @endif
                                                        </li>
                                                    @endif
                                                </ul>
                                            </div>
                                            @endif

                                            @if($podeContratoPdfLocacao)
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=checklist" data-bs-toggle="tooltip" title="Imprimir Checklist"><i class="ti ti-clipboard-check"></i></a>
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=entrega" data-bs-toggle="tooltip" title="Comprovante de Entrega"><i class="ti ti-truck-delivery"></i></a>
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=romaneio" data-bs-toggle="tooltip" title="Imprimir Romaneio"><i class="ti ti-list-details"></i></a>
                                                <a class="btn btn-sm btn-outline-primary btn-icon-acao" target="_blank" href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=recibo" data-bs-toggle="tooltip" title="Imprimir Recibo"><i class="ti ti-receipt-2"></i></a>
                                            @endif
                                        @else
                                            <a class="btn btn-sm btn-outline-secondary btn-icon-acao" href="{{ route('locacoes.show', $locacao->id_locacao) }}" data-bs-toggle="tooltip" title="Visualizar"><i class="ti ti-eye"></i></a>
                                        @endif
                                        </div>

                                        <div class="w-100 tabela-itens-locacao">
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Item</th>
                                                            <th style="width: 90px;">Origem</th>
                                                            <th style="width: 70px;">Qtd</th>
                                                            <th style="width: 95px;">Período</th>
                                                            <th style="width: 120px;">Unit.</th>
                                                            <th style="width: 130px;">Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php
                                                            $itensProprios = $locacao->produtos ?? collect();
                                                            $itensTerceiros = $locacao->produtosTerceiros ?? collect();
                                                            $itensServicos = $locacao->servicos ?? collect();
                                                            $itensDespesas = $locacao->despesas ?? collect();
                                                            $temItens = $itensProprios->count() > 0
                                                                || $itensTerceiros->count() > 0
                                                                || $itensServicos->count() > 0
                                                                || $itensDespesas->count() > 0;
                                                        @endphp

                                                        @if($temItens)
                                                            @foreach($itensProprios as $item)
                                                                @php
                                                                    $itemRetornado = (int) ($item->estoque_status ?? 0) === 2
                                                                        || in_array($item->status_retorno, ['devolvido', 'avariado', 'extraviado'], true);

                                                                    $qtdItem = max(1, (int) ($item->quantidade ?? 1));
                                                                    $unitItem = (float) ($item->preco_unitario ?? 0);
                                                                    $tipoCobrancaItem = strtolower((string) ($item->tipo_cobranca ?? 'diaria'));
                                                                    $valorFechadoItem = in_array($tipoCobrancaItem, ['fechado', 'valor_fechado'], true)
                                                                        || (int) ($item->valor_fechado ?? 0) === 1;

                                                                    $dataInicioItem = (string) (($item->data_saida ?? $item->data_inicio ?? $locacao->data_inicio) ?: '');
                                                                    $dataFimItem = (string) (($item->data_retorno ?? $item->data_fim ?? $locacao->data_fim) ?: '');
                                                                    $horaInicioItem = (string) (($item->hora_saida ?? $item->hora_inicio ?? $locacao->hora_inicio) ?: '');
                                                                    $horaFimItem = (string) (($item->hora_retorno ?? $item->hora_fim ?? $locacao->hora_fim) ?: '');

                                                                    $periodoItem = max(1, (int) $qtdPeriodo);
                                                                    if ($unidadePeriodo === 'hora(s)' && $dataInicioItem !== '' && $dataFimItem !== '' && $horaInicioItem !== '' && $horaFimItem !== '') {
                                                                        try {
                                                                            $inicioItem = \Carbon\Carbon::parse($dataInicioItem . ' ' . $horaInicioItem);
                                                                            $fimItem = \Carbon\Carbon::parse($dataFimItem . ' ' . $horaFimItem);
                                                                            if ($fimItem->gte($inicioItem)) {
                                                                                $periodoItem = max(1, (int) ceil($inicioItem->diffInMinutes($fimItem) / 60));
                                                                            }
                                                                        } catch (\Throwable $e) {
                                                                            $periodoItem = max(1, (int) $qtdPeriodo);
                                                                        }
                                                                    } elseif ($dataInicioItem !== '' && $dataFimItem !== '') {
                                                                        try {
                                                                            $inicioItem = \Carbon\Carbon::parse($dataInicioItem);
                                                                            $fimItem = \Carbon\Carbon::parse($dataFimItem);
                                                                            $periodoItem = max(1, $inicioItem->startOfDay()->diffInDays($fimItem->startOfDay()) + 1);
                                                                        } catch (\Throwable $e) {
                                                                            $periodoItem = max(1, (int) ($item->quantidade_dias ?? $qtdPeriodo));
                                                                        }
                                                                    }

                                                                    $subtotalCalculadoItem = $valorFechadoItem
                                                                        ? (float) ($item->preco_total ?? 0)
                                                                        : ($unitItem * $qtdItem * $periodoItem);
                                                                @endphp
                                                                <tr>
                                                                    <td>
                                                                        {{ $item->produto->nome ?? 'Produto próprio' }}
                                                                        @if($itemRetornado)
                                                                            <span class="badge bg-label-success ms-1">Retornado</span>
                                                                        @endif
                                                                    </td>
                                                                    <td><span class="badge bg-label-primary">Próprio</span></td>
                                                                    <td>{{ $qtdItem }}</td>
                                                                    <td>{{ $periodoItem }} {{ $unidadePeriodo === 'hora(s)' ? 'hora(s)' : 'dia(s)' }}</td>
                                                                    <td>R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                                                                    <td><strong>R$ {{ number_format($subtotalCalculadoItem, 2, ',', '.') }}</strong></td>
                                                                </tr>
                                                            @endforeach

                                                            @foreach($itensTerceiros as $item)
                                                                @php
                                                                    $qtdItem = max(1, (int) ($item->quantidade ?? 1));
                                                                    $unitItem = (float) ($item->preco_unitario ?? 0);
                                                                    $tipoCobrancaItem = strtolower((string) ($item->tipo_cobranca ?? 'diaria'));
                                                                    $valorFechadoItem = in_array($tipoCobrancaItem, ['fechado', 'valor_fechado'], true)
                                                                        || (int) ($item->valor_fechado ?? 0) === 1;

                                                                    $dataInicioItem = (string) (($item->data_inicio ?? $locacao->data_inicio) ?: '');
                                                                    $dataFimItem = (string) (($item->data_fim ?? $locacao->data_fim) ?: '');
                                                                    $horaInicioItem = (string) (($item->hora_inicio ?? $locacao->hora_inicio) ?: '');
                                                                    $horaFimItem = (string) (($item->hora_fim ?? $locacao->hora_fim) ?: '');

                                                                    $periodoItem = max(1, (int) $qtdPeriodo);
                                                                    if ($unidadePeriodo === 'hora(s)' && $dataInicioItem !== '' && $dataFimItem !== '' && $horaInicioItem !== '' && $horaFimItem !== '') {
                                                                        try {
                                                                            $inicioItem = \Carbon\Carbon::parse($dataInicioItem . ' ' . $horaInicioItem);
                                                                            $fimItem = \Carbon\Carbon::parse($dataFimItem . ' ' . $horaFimItem);
                                                                            if ($fimItem->gte($inicioItem)) {
                                                                                $periodoItem = max(1, (int) ceil($inicioItem->diffInMinutes($fimItem) / 60));
                                                                            }
                                                                        } catch (\Throwable $e) {
                                                                            $periodoItem = max(1, (int) $qtdPeriodo);
                                                                        }
                                                                    } elseif ($dataInicioItem !== '' && $dataFimItem !== '') {
                                                                        try {
                                                                            $inicioItem = \Carbon\Carbon::parse($dataInicioItem);
                                                                            $fimItem = \Carbon\Carbon::parse($dataFimItem);
                                                                            $periodoItem = max(1, $inicioItem->startOfDay()->diffInDays($fimItem->startOfDay()) + 1);
                                                                        } catch (\Throwable $e) {
                                                                            $periodoItem = max(1, (int) ($item->quantidade_dias ?? $qtdPeriodo));
                                                                        }
                                                                    }

                                                                    $subtotalCalculadoItem = $valorFechadoItem
                                                                        ? (float) ($item->valor_total ?? 0)
                                                                        : ($unitItem * $qtdItem * $periodoItem);
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $item->produtoTerceiro->nome ?? $item->nome_produto_manual ?? 'Produto de terceiro' }}</td>
                                                                    <td><span class="badge bg-label-info">Terceiro</span></td>
                                                                    <td>{{ $qtdItem }}</td>
                                                                    <td>{{ $periodoItem }} {{ $unidadePeriodo === 'hora(s)' ? 'hora(s)' : 'dia(s)' }}</td>
                                                                    <td>R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                                                                    <td><strong>R$ {{ number_format($subtotalCalculadoItem, 2, ',', '.') }}</strong></td>
                                                                </tr>
                                                            @endforeach

                                                            @foreach($itensServicos as $item)
                                                                @php
                                                                    $isTerceiro = (($item->tipo_item ?? 'proprio') === 'terceiro');
                                                                @endphp
                                                                <tr>
                                                                    <td>{{ $item->descricao ?? 'Serviço' }}</td>
                                                                    <td>
                                                                        <span class="badge {{ $isTerceiro ? 'bg-label-warning' : 'bg-label-success' }}">
                                                                            {{ $isTerceiro ? 'Serv. Terceiro' : 'Serviço' }}
                                                                        </span>
                                                                    </td>
                                                                    <td>{{ (int) ($item->quantidade ?? 1) }}</td>
                                                                    <td>1 un.</td>
                                                                    <td>R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                                                                    <td><strong>R$ {{ number_format((float) ($item->valor_total ?? 0), 2, ',', '.') }}</strong></td>
                                                                </tr>
                                                            @endforeach

                                                            @foreach($itensDespesas as $item)
                                                                <tr>
                                                                    <td>{{ $item->descricao ?? ('Despesa ' . ucfirst((string) ($item->tipo ?? ''))) }}</td>
                                                                    <td><span class="badge bg-label-secondary">Despesa</span></td>
                                                                    <td>1</td>
                                                                    <td>1 un.</td>
                                                                    <td>R$ {{ number_format((float) ($item->valor ?? 0), 2, ',', '.') }}</td>
                                                                    <td><strong>R$ {{ number_format((float) ($item->valor ?? 0), 2, ',', '.') }}</strong></td>
                                                                </tr>
                                                            @endforeach
                                                        @else
                                                            <tr>
                                                                <td colspan="6" class="text-muted text-center py-2">Sem itens cadastrados para esta locação.</td>
                                                            </tr>
                                                        @endif
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        @php
                                            $subtotalProdutos = (float) ($locacao->subtotal_produtos_listagem ?? 0);
                                            $subtotalServicos = (float) ($locacao->subtotal_servicos_listagem ?? 0);
                                            $subtotalDespesas = (float) ($locacao->subtotal_despesas_listagem ?? 0);
                                            $valorTotalBase = (float) ($locacao->valor_total_base_listagem ?? ($subtotalProdutos + $subtotalServicos));

                                            $valorFrete = (float) ($locacao->valor_frete ?? 0);
                                            $valorAcrescimo = (float) ($locacao->valor_acrescimo ?? 0);
                                            $valorImposto = (float) ($locacao->valor_imposto ?? 0);
                                            $valorDespesasExtras = (float) ($locacao->valor_despesas_extras ?? 0);
                                            $valorDesconto = (float) ($locacao->valor_desconto ?? 0);
                                            $totalFinalExpandido = (float) ($locacao->valor_total_listagem ?? 0);
                                        @endphp

                                        <div class="w-100 resumo-financeiro-locacao">
                                            <div class="linha"><span>Subtotal Produtos</span><strong>R$ {{ number_format($subtotalProdutos, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Subtotal Serviços</span><strong>R$ {{ number_format($subtotalServicos, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Despesas Operacionais</span><strong>R$ {{ number_format($subtotalDespesas, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Valor Total (base)</span><strong>R$ {{ number_format($valorTotalBase, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Frete</span><strong>R$ {{ number_format($valorFrete, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Acréscimo</span><strong>R$ {{ number_format($valorAcrescimo, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Imposto</span><strong>R$ {{ number_format($valorImposto, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Despesas Extras</span><strong>R$ {{ number_format($valorDespesasExtras, 2, ',', '.') }}</strong></div>
                                            <div class="linha"><span>Desconto</span><strong>- R$ {{ number_format($valorDesconto, 2, ',', '.') }}</strong></div>
                                            <div class="linha total"><span>Total Final</span><span>R$ {{ number_format($totalFinalExpandido, 2, ',', '.') }}</span></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">Nenhum contrato encontrado.</td></tr>
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

<div class="modal fade" id="modalFiltrosContratos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="GET" action="{{ route('locacoes.contratos') }}">
                <div class="modal-header">
                    <h5 class="modal-title">Filtros de Locações</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="aba" value="{{ $aba }}">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Cliente</label>
                            <select name="id_cliente" class="form-select">
                                <option value="">Todos</option>
                                @foreach($clientes as $cliente)
                                    <option value="{{ $cliente->id_clientes }}" {{ ((string)($filters['id_cliente'] ?? '') === (string)$cliente->id_clientes) ? 'selected' : '' }}>{{ $cliente->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Produto</label>
                            <select name="id_produto" class="form-select">
                                <option value="">Todos</option>
                                @foreach(($produtos ?? collect()) as $produto)
                                    <option value="{{ $produto->id_produto }}" {{ ((string)($filters['id_produto'] ?? '') === (string)$produto->id_produto) ? 'selected' : '' }}>{{ $produto->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Funcionário</label>
                            <select name="id_usuario" class="form-select">
                                <option value="">Todos</option>
                                @foreach(($usuarios ?? collect()) as $usuario)
                                    <option value="{{ $usuario->id_usuario }}" {{ ((string)($filters['id_usuario'] ?? '') === (string)$usuario->id_usuario) ? 'selected' : '' }}>{{ $usuario->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Código da Locação</label>
                            <input type="text" name="codigo" class="form-control" value="{{ $filters['codigo'] ?? '' }}" placeholder="Ex: 038">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nome do Cliente</label>
                            <input type="text" name="busca" class="form-control" value="{{ $filters['busca'] ?? '' }}" placeholder="Buscar por cliente">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data início</label>
                            <input type="date" name="data_inicio" class="form-control" value="{{ $filters['data_inicio'] ?? '' }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Data fim</label>
                            <input type="date" name="data_fim" class="form-control" value="{{ $filters['data_fim'] ?? '' }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="{{ route('locacoes.contratos', ['aba' => $aba]) }}" class="btn btn-label-secondary">Limpar</a>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-search me-1"></i>Aplicar Filtros</button>
                </div>
            </form>
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
                <div class="table-responsive">
                    <table class="table table-sm" id="tabelaRetornoPatrimonios">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Patrimônio</th>
                                <th>Status</th>
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

<div class="modal fade" id="modalDefeitoRetorno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Itens com Defeito no Retorno</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning py-2">
                    Existem itens marcados com defeito na expedição. Escolha a ação para cada item.
                </div>
                <div class="table-responsive">
                    <table class="table table-sm" id="tabelaDefeitoRetorno">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Patrimônio</th>
                                <th>Qtd<br>defeito</th>
                                <th>Ação</th>
                                <th>Tipo<br>manutenção</th>
                                <th>Data<br>manutenção</th>
                                <th>Data<br>previsão</th>
                                <th>Descrição</th>
                                <th>Responsável</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmarDefeitoRetorno">Continuar Finalização</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRetornoParcial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Retorno Parcial</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Data/Hora de devolução</label>
                        <input type="datetime-local" class="form-control" id="retornoParcialDataHora">
                    </div>
                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" value="1" id="retornoParcialNaoRecalcular">
                            <label class="form-check-label" for="retornoParcialNaoRecalcular">
                                Não recalcular valor
                            </label>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm" id="tabelaRetornoParcial">
                        <thead>
                            <tr>
                                <th style="width: 40px;"></th>
                                <th>Produto</th>
                                <th style="width: 140px;">Patrimônio</th>
                                <th style="width: 170px;">Início</th>
                                <th style="width: 120px;">Qtd retorno</th>
                                <th style="width: 150px;">Cobrança</th>
                                <th style="width: 140px;">Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarRetornoParcial">Confirmar Retorno Parcial</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRenovarAditivo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Renovar Contrato (Aditivo)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2" id="renovacaoContratoInfo">-</div>

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Data início</label>
                        <input type="date" class="form-control" id="renovacaoDataInicio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hora início</label>
                        <input type="time" class="form-control" id="renovacaoHoraInicio">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data fim</label>
                        <input type="date" class="form-control" id="renovacaoDataFim">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hora fim</label>
                        <input type="time" class="form-control" id="renovacaoHoraFim">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="renovacaoAutomatica">
                            <label class="form-check-label" for="renovacaoAutomatica">Ativar renovação automática para próximos vencimentos</label>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm" id="tabelaRenovacaoAditivo">
                        <thead>
                            <tr>
                                <th style="width:40px"></th>
                                <th>Produto</th>
                                <th style="width:140px">Patrimônio</th>
                                <th style="width:180px">Início item</th>
                                <th style="width:180px">Fim item</th>
                                <th style="width:130px">Qtd renovar</th>
                                <th style="width:120px">Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="btnConfirmarRenovacaoAditivo">Criar Aditivo</button>
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
$(function () {
    if (!document.getElementById('swal2-front-modal-zindex')) {
        $('head').append('<style id="swal2-front-modal-zindex">.swal2-container{z-index:200000 !important;}</style>');
    }

    let locacaoRetornoAtual = null;
    let locacaoRetornoParcialAtual = null;
    let itensRetornoParcialAtual = [];
    let locacaoRenovacaoAtual = null;
    let locacaoDefeitoAtual = null;
    let retornosDefeitoPendentes = [];
    let opcoesDefeitoPendentes = {};
    const modalRetornoElement = document.getElementById('modalRetornoPatrimonios');
    const modalRetorno = modalRetornoElement ? new bootstrap.Modal(modalRetornoElement) : null;
    const modalDefeitoElement = document.getElementById('modalDefeitoRetorno');
    const modalDefeitoRetorno = modalDefeitoElement ? new bootstrap.Modal(modalDefeitoElement) : null;
    const modalRetornoParcialElement = document.getElementById('modalRetornoParcial');
    const modalRetornoParcial = modalRetornoParcialElement ? new bootstrap.Modal(modalRetornoParcialElement) : null;
    const modalRenovarAditivoElement = document.getElementById('modalRenovarAditivo');
    const modalRenovarAditivo = modalRenovarAditivoElement ? new bootstrap.Modal(modalRenovarAditivoElement) : null;

    function fecharModalRenovacaoAditivo() {
        if (!modalRenovarAditivo) return;
        modalRenovarAditivo.hide();
    }

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

    let estadoLogAtividadesLocacao = {
        idLocacao: null,
        codigoLocacao: '',
        clienteLocacao: '',
        escopo: 'todos',
        referencias: {
            empresas: {},
            clientes: {},
            usuarios: {},
            locacoes: {},
            produtos: {},
            salas: {},
            tabelas_preco: {},
        },
        contagens: {
            todos: 0,
            locacao: 0,
            produtos: 0,
            servicos: 0,
            despesas: 0,
            terceiros: 0,
            retornos: 0,
            trocas: 0,
        },
    };

    function abrirLogAtividadesLocacao(idLocacao, codigoLocacao, clienteLocacao) {
        estadoLogAtividadesLocacao = {
            idLocacao: idLocacao,
            codigoLocacao: codigoLocacao || '',
            clienteLocacao: clienteLocacao || '',
            escopo: 'todos',
            referencias: {
                empresas: {},
                clientes: {},
                usuarios: {},
                locacoes: {},
                produtos: {},
                salas: {},
                tabelas_preco: {},
            },
            contagens: {
                todos: 0,
                locacao: 0,
                produtos: 0,
                servicos: 0,
                despesas: 0,
                terceiros: 0,
                retornos: 0,
                trocas: 0,
            },
        };

        mostrarModalLogAtividadesLocacao([], {
            carregando: true,
            atualizarConteudo: false,
            escopo: 'todos',
            contagens: estadoLogAtividadesLocacao.contagens,
        });

        carregarLogAtividadesLocacaoPorEscopo('todos');
    }

    function carregarLogAtividadesLocacaoPorEscopo(escopo) {
        if (!estadoLogAtividadesLocacao.idLocacao) {
            return;
        }

        estadoLogAtividadesLocacao.escopo = escopo;

        $.ajax({
            url: `{{ url('locacoes') }}/${estadoLogAtividadesLocacao.idLocacao}/logs-atividades`,
            data: { escopo: escopo },
            method: 'GET',
            success: function(response) {
                if (!response.success) {
                    Swal.fire('Erro', response.message || 'Não foi possível carregar o log de atividades.', 'error');
                    return;
                }

                estadoLogAtividadesLocacao.contagens = {
                    todos: Number(response?.contagens?.todos || 0),
                    locacao: Number(response?.contagens?.locacao || 0),
                    produtos: Number(response?.contagens?.produtos || 0),
                    servicos: Number(response?.contagens?.servicos || 0),
                    despesas: Number(response?.contagens?.despesas || 0),
                    terceiros: Number(response?.contagens?.terceiros || 0),
                    retornos: Number(response?.contagens?.retornos || 0),
                    trocas: Number(response?.contagens?.trocas || 0),
                };

                estadoLogAtividadesLocacao.referencias = {
                    empresas: response?.referencias?.empresas || {},
                    clientes: response?.referencias?.clientes || {},
                    usuarios: response?.referencias?.usuarios || {},
                    locacoes: response?.referencias?.locacoes || {},
                    produtos: response?.referencias?.produtos || {},
                    salas: response?.referencias?.salas || {},
                    tabelas_preco: response?.referencias?.tabelas_preco || {},
                };

                estadoLogAtividadesLocacao.escopo = response.escopo || escopo;

                mostrarModalLogAtividadesLocacao(response.logs || [], {
                    carregando: false,
                    atualizarConteudo: true,
                    escopo: estadoLogAtividadesLocacao.escopo,
                    contagens: estadoLogAtividadesLocacao.contagens,
                });
            },
            error: function(xhr) {
                Swal.fire('Erro', xhr.responseJSON?.message || 'Erro ao carregar o log de atividades.', 'error');
            }
        });
    }

    function mostrarModalLogAtividadesLocacao(logs, opcoes = {}) {
        const html = gerarHtmlLogAtividadesLocacao(logs, opcoes);

        if (opcoes.atualizarConteudo) {
            const container = Swal.getHtmlContainer();
            if (container) {
                container.innerHTML = html;
                return;
            }
        }

        Swal.fire({
            title: '<div class="text-center fw-bold mb-0">Log de Atividades da Locação</div>',
            html: html,
            showCancelButton: false,
            showConfirmButton: false,
            showCloseButton: true,
            buttonsStyling: false,
            width: '1140px',
            customClass: {
                popup: 'p-0',
                htmlContainer: 'm-0 p-0',
                title: 'pt-4 pb-0'
            }
        });
    }

    function gerarHtmlLogAtividadesLocacao(logs, opcoes = {}) {
        const escopo = opcoes.escopo || 'todos';
        const contagens = opcoes.contagens || {};
        const carregando = opcoes.carregando === true;
        const totalEscopo = Number(contagens?.[escopo] ?? (Array.isArray(logs) ? logs.length : 0));

        const rotulosEscopo = {
            todos: 'Todos',
            locacao: 'Locação',
            produtos: 'Produtos',
            servicos: 'Serviços',
            despesas: 'Despesas',
            terceiros: 'Terceiros',
            retornos: 'Retornos',
            trocas: 'Trocas',
        };

        const botaoEscopo = function (valor, titulo, icone) {
            const ativo = escopo === valor;
            const classe = ativo ? 'btn-primary' : 'btn-outline-primary';
            const contador = Number(contagens?.[valor] ?? 0);
            return `<button type="button" class="btn btn-sm ${classe}" onclick="filtrarLogsAtividadesLocacao('${valor}')"><i class="ti ti-${icone} me-1"></i>${titulo} (${contador})</button>`;
        };

        let html = `
            <div class="text-start p-4 border-bottom bg-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <div class="small text-muted mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">Contrato</div>
                        <div class="fw-bold mb-0" style="font-size: 1.05rem; line-height: 1.4;">${escapeHtmlLogLocacao(estadoLogAtividadesLocacao.codigoLocacao || '-')}</div>
                        <div class="small text-muted mt-1">Cliente: ${escapeHtmlLogLocacao(estadoLogAtividadesLocacao.clienteLocacao || '-')}</div>
                    </div>
                    <span class="badge bg-label-primary fw-semibold px-3 py-2" style="font-size: 0.85rem;">
                        <i class="ti ti-list-check me-1"></i>${totalEscopo} registro${totalEscopo !== 1 ? 's' : ''}
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap mt-3 justify-content-end">
                    ${botaoEscopo('todos', 'Todos', 'list')}
                    ${botaoEscopo('locacao', 'Locação', 'file-text')}
                    ${botaoEscopo('produtos', 'Produtos', 'package')}
                    ${botaoEscopo('servicos', 'Serviços', 'tool')}
                    ${botaoEscopo('despesas', 'Despesas', 'currency-real')}
                    ${botaoEscopo('terceiros', 'Terceiros', 'building-store')}
                    ${botaoEscopo('retornos', 'Retornos', 'arrow-back-up')}
                    ${botaoEscopo('trocas', 'Trocas', 'replace')}
                </div>
            </div>
        `;

        if (carregando) {
            html += `
                <div class="p-4">
                    <div class="alert alert-info mb-0 text-center rounded-3" style="padding: 2rem;">
                        <i class="spinner-border spinner-border-sm me-2"></i>
                        Carregando log de atividades...
                    </div>
                </div>
            `;
            return html;
        }

        if (!Array.isArray(logs) || logs.length === 0) {
            html += `
                <div class="p-4">
                    <div class="alert alert-info mb-0 text-center rounded-3" style="padding: 2rem;">
                        <i class="ti ti-info-circle mb-2" style="font-size: 2rem;"></i>
                        <div class="fw-semibold">Nenhum log de atividade encontrado</div>
                        <div class="text-muted small mt-1">Nenhum registro para o filtro: ${rotulosEscopo[escopo] || 'Todos'}.</div>
                    </div>
                </div>
            `;
            return html;
        }

        html += '<div class="bg-body-secondary" style="max-height: 600px; overflow-y: auto; padding: 1.5rem;">';

        logs.forEach((item, index) => {
            const cor = normalizarCorLogLocacao(item.cor);
            const icone = item.icone || 'activity';
            const responsavel = escapeHtmlLogLocacao(item.nome_responsavel || item.email_responsavel || 'Sistema');
            const dataHora = formatDateTimeLogLocacao(item.ocorrido_em);
            const acao = formatarAcaoLogLocacao(item.acao || '-');
            const descricao = escapeHtmlLogLocacao(item.descricao || 'Atividade registrada');
            const origem = formatarOrigemEntidadeLogLocacao(item.entidade_tipo);
            const temAntes = item.antes && Object.keys(item.antes).length > 0;
            const temDepois = item.depois && Object.keys(item.depois).length > 0;

            html += `
                <div class="card mb-3 shadow-sm position-relative" style="border-left: 5px solid var(--bs-${cor}) !important;">
                    <div class="position-absolute top-0 end-0 mt-3 me-3">
                        <span class="badge bg-label-secondary" style="font-size: 0.7rem;">#${logs.length - index}</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="avatar avatar-md bg-label-${cor} flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                                <i class="ti ti-${icone}" style="font-size: 1.5rem;"></i>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <h6 class="mb-2 fw-bold" style="line-height: 1.4;">${descricao}</h6>
                                <div class="d-flex flex-wrap gap-3 text-muted small">
                                    <span class="d-flex align-items-center"><i class="ti ti-user me-1"></i><span class="fw-medium">${responsavel}</span></span>
                                    <span class="d-flex align-items-center"><i class="ti ti-calendar-event me-1"></i><span>${dataHora}</span></span>
                                </div>
                                <div class="mt-2">
                                    <span class="badge bg-${cor} fw-semibold" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">${acao}</span>
                                    <span class="badge bg-label-secondary fw-semibold ms-1" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">${origem}</span>
                                </div>
                            </div>
                        </div>
                        ${(temAntes || temDepois) ? `
                            <div class="border-top pt-3 mt-3">
                                <div class="row g-3">
                                    ${temAntes ? `<div class="col-md-${temDepois ? '6' : '12'}"><div class="p-3 rounded-3 h-100 bg-label-danger border border-danger border-opacity-25"><div class="d-flex align-items-center mb-3"><span class="avatar avatar-xs bg-label-danger me-2"><i class="ti ti-arrow-left" style="font-size: 0.8rem;"></i></span><strong class="text-danger" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Antes</strong></div><div class="small" style="line-height: 1.8;">${formatarObjetoDetalhadoLogLocacao(item.antes)}</div></div></div>` : ''}
                                    ${temDepois ? `<div class="col-md-${temAntes ? '6' : '12'}"><div class="p-3 rounded-3 h-100 bg-label-success border border-success border-opacity-25"><div class="d-flex align-items-center mb-3"><span class="avatar avatar-xs bg-label-success me-2"><i class="ti ti-arrow-right" style="font-size: 0.8rem;"></i></span><strong class="text-success" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Depois</strong></div><div class="small" style="line-height: 1.8;">${formatarObjetoDetalhadoLogLocacao(item.depois)}</div></div></div>` : ''}
                                </div>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        html += '</div>';
        return html;
    }

    function formatarObjetoDetalhadoLogLocacao(obj) {
        if (!obj || typeof obj !== 'object') {
            return '<span class="text-muted fst-italic">Sem dados</span>';
        }

        const entries = Object.entries(obj);
        if (entries.length === 0) {
            return '<span class="text-muted fst-italic">Sem alterações</span>';
        }

        let html = '<div class="d-flex flex-column gap-2">';
        entries.forEach(([chave, valor]) => {
            const chaveFormatada = formatarChaveDetalheLogLocacao(chave);
            const valorTexto = formatarValorDetalheLogLocacao(chave, valor);
            html += `
                <div class="d-flex gap-2">
                    <span class="fw-semibold text-nowrap text-body-secondary" style="min-width: 120px;">${escapeHtmlLogLocacao(chaveFormatada)}:</span>
                    <span class="flex-grow-1 text-break">${escapeHtmlLogLocacao(valorTexto)}</span>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    function formatarChaveDetalheLogLocacao(chave) {
        const mapaRotulos = {
            id_empresa: 'Empresa',
            id_cliente: 'Cliente',
            id_clientes: 'Cliente',
            id_usuario: 'Usuário',
            id_locacao: 'Contrato',
            id_produto: 'Produto',
            id_sala: 'Sala',
            id_tabela_preco: 'Tabela de Preço',
        };

        if (mapaRotulos[chave]) {
            return mapaRotulos[chave];
        }

        if (String(chave).startsWith('id_')) {
            return String(chave)
                .replace(/^id_/, '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, function (l) { return l.toUpperCase(); });
        }

        return String(chave).replace(/_/g, ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
    }

    function formatarValorDetalheLogLocacao(chave, valor) {
        if (valor === null || valor === undefined || valor === '') {
            return '(vazio)';
        }

        const chaveTexto = String(chave || '');
        const idTexto = String(valor);

        if (chaveTexto === 'id_empresa') {
            return estadoLogAtividadesLocacao?.referencias?.empresas?.[idTexto] || 'Empresa não identificada';
        }

        if (chaveTexto === 'id_cliente' || chaveTexto === 'id_clientes') {
            return estadoLogAtividadesLocacao?.referencias?.clientes?.[idTexto] || 'Cliente não identificado';
        }

        if (chaveTexto === 'id_usuario') {
            return estadoLogAtividadesLocacao?.referencias?.usuarios?.[idTexto] || 'Usuário não identificado';
        }

        if (chaveTexto === 'id_locacao') {
            return estadoLogAtividadesLocacao?.referencias?.locacoes?.[idTexto] || 'Contrato não identificado';
        }

        if (chaveTexto === 'id_produto') {
            return estadoLogAtividadesLocacao?.referencias?.produtos?.[idTexto] || 'Produto não identificado';
        }

        if (chaveTexto === 'id_sala') {
            return estadoLogAtividadesLocacao?.referencias?.salas?.[idTexto] || 'Sala não identificada';
        }

        if (chaveTexto === 'id_tabela_preco') {
            return estadoLogAtividadesLocacao?.referencias?.tabelas_preco?.[idTexto] || 'Tabela de preço não identificada';
        }

        if (chaveTexto.startsWith('id_')) {
            return 'Registro relacionado';
        }

        return String(valor);
    }

    function normalizarCorLogLocacao(cor) {
        const mapa = {
            verde: 'success',
            amarelo: 'warning',
            vermelho: 'danger',
            azul: 'primary',
            'azul-escuro': 'info',
            laranja: 'warning',
            cinza: 'secondary',
            'cinza-escuro': 'dark',
            roxo: 'primary',
            'verde-escuro': 'success',
            'azul-claro': 'info',
        };

        return mapa[String(cor || '').toLowerCase()] || 'primary';
    }

    function formatDateTimeLogLocacao(dateString) {
        if (!dateString) return '-';
        const date = new Date(dateString);
        if (Number.isNaN(date.getTime())) return '-';
        return date.toLocaleString('pt-BR');
    }

    function formatarAcaoLogLocacao(acao) {
        if (!acao || acao === '-') return '-';
        return String(acao).replace(/[_\.]+/g, ' ').trim();
    }

    function formatarOrigemEntidadeLogLocacao(entidadeTipo) {
        const valor = String(entidadeTipo || '').toLowerCase();

        if (valor === 'locacao') return 'Locação';
        if (valor === 'locacaoproduto' || valor === 'locacao_produto') return 'Produto';
        if (valor === 'locacaoservico' || valor === 'locacao_servico') return 'Serviço';
        if (valor === 'locacaodespesa' || valor === 'locacao_despesa') return 'Despesa';
        if (valor === 'produtoterceiroslocacao' || valor === 'produto_terceiros_locacao') return 'Terceiro';
        if (valor === 'locacaoretornopatrimonio' || valor === 'locacao_retorno_patrimonio') return 'Retorno';
        if (valor === 'locacaotrocaproduto' || valor === 'locacao_troca_produto') return 'Troca';

        return entidadeTipo || 'Registro';
    }

    function escapeHtmlLogLocacao(texto) {
        return String(texto)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    window.filtrarLogsAtividadesLocacao = function(escopo) {
        carregarLogAtividadesLocacaoPorEscopo(escopo);
    };

    function executarRetornoLocacao(idLocacao, retornos = [], opcoes = {}) {
        const dataHoraFinalizacao = opcoes.dataHoraFinalizacao || formatarDataHoraInputAgora().replace('T', ' ');
        const decisoesAvaria = Array.isArray(opcoes.decisoesAvaria) ? opcoes.decisoesAvaria : [];

        $.ajax({
            url: `{{ url('locacoes') }}/${idLocacao}/retornar`,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                retornos: retornos,
                decisoes_avaria: decisoesAvaria,
                data_hora_finalizacao: dataHoraFinalizacao,
                recalcular_atraso: opcoes.recalcularAtraso ? 1 : 0,
                confirmar_sem_recalculo_atraso: opcoes.confirmarSemRecalculoAtraso ? 1 : 0,
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire('Sucesso!', response.message, 'success').then(() => location.reload());
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON || {};
                if (xhr.status === 409 && response.requires_overdue_recalculation) {
                    const itens = Array.isArray(response.itens_atraso) ? response.itens_atraso : [];
                    const htmlItens = itens.length
                        ? `<div class="text-start mt-2" style="max-height:220px;overflow:auto;">${itens.map((item) => `
                            <div class="small mb-1">• ${item.produto_nome || 'Produto'}${item.patrimonio ? ' (' + item.patrimonio + ')' : ''} - fim: ${item.data_fim_item || '-'}</div>
                        `).join('')}</div>`
                        : '';

                    Swal.fire({
                        title: 'Itens em atraso',
                        html: `Os itens abaixo estão sendo devolvidos em atraso, deseja recalcular os valores automaticamente?${htmlItens}`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sim, recalcular',
                        cancelButtonText: 'Não recalcular',
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            executarRetornoLocacao(idLocacao, retornos, {
                                recalcularAtraso: true,
                                dataHoraFinalizacao: response.data_hora_atual || dataHoraFinalizacao,
                            });
                            return;
                        }

                        if (result.dismiss === Swal.DismissReason.cancel) {
                            executarRetornoLocacao(idLocacao, retornos, {
                                confirmarSemRecalculoAtraso: true,
                                dataHoraFinalizacao: response.data_hora_atual || dataHoraFinalizacao,
                            });
                        }
                    });
                    return;
                }

                if (xhr.status === 409 && response.requires_defeito_action && Array.isArray(response.itens_defeito)) {
                    locacaoDefeitoAtual = idLocacao;
                    retornosDefeitoPendentes = Array.isArray(retornos) ? retornos : [];
                    opcoesDefeitoPendentes = {
                        dataHoraFinalizacao: response.data_hora_atual || dataHoraFinalizacao,
                        recalcularAtraso: !!opcoes.recalcularAtraso,
                        confirmarSemRecalculoAtraso: !!opcoes.confirmarSemRecalculoAtraso,
                    };
                    abrirModalDefeitoRetorno(response.itens_defeito);
                    return;
                }

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
                                    </select>
                                </td>
                                <td><input type="text" class="form-control form-control-sm retorno-observacoes" placeholder="Observações"></td>
                            </tr>
                        `);
                    });
                    if (modalRetorno) modalRetorno.show();
                    return;
                }
                Swal.fire('Erro!', response.message || 'Não foi possível retornar a locação.', 'error');
            }
        });
    }

    function abrirModalDefeitoRetorno(itensDefeito) {
        const $tbody = $('#tabelaDefeitoRetorno tbody');
        $tbody.empty();

        (itensDefeito || []).forEach((item) => {
            const sugestaoDescricao = (item.observacao_defeito || '').replace(/"/g, '&quot;');
            const quantidadeDefeito = Math.max(1, Number(item.quantidade_com_defeito || 1));
            const quantidadeTotal = Math.max(1, Number(item.quantidade || 1));
            const ehPatrimonio = !!item.id_patrimonio;
            const descricaoPadrao = sugestaoDescricao || `Retorno com defeito - ${item.nome_produto || 'Produto'}`;

            $tbody.append(`
                <tr data-id-produto-locacao="${item.id_produto_locacao}">
                    <td>${item.nome_produto || '-'}</td>
                    <td>${item.patrimonio || '-'}</td>
                    <td>
                        <input
                            type="number"
                            class="form-control form-control-sm defeito-quantidade"
                            min="1"
                            max="${quantidadeTotal}"
                            value="${ehPatrimonio ? 1 : quantidadeDefeito}"
                            ${ehPatrimonio ? 'readonly' : ''}
                        >
                        <small class="text-muted">de ${quantidadeTotal}</small>
                    </td>
                    <td>
                        <select class="form-select form-select-sm defeito-acao">
                            <option value="retornar_estoque">Retornar ao estoque</option>
                            <option value="gerar_manutencao">Gerar manutenção</option>
                        </select>
                    </td>
                    <td>
                        <select class="form-select form-select-sm defeito-campo-manutencao defeito-tipo d-none">
                            <option value="corretiva" selected>Corretiva</option>
                            <option value="preventiva">Preventiva</option>
                            <option value="preditiva">Preditiva</option>
                            <option value="emergencial">Emergencial</option>
                        </select>
                    </td>
                    <td>
                        <input type="date" class="form-control form-control-sm defeito-campo-manutencao defeito-data d-none" value="${new Date().toISOString().slice(0, 10)}">
                    </td>
                    <td>
                        <input type="date" class="form-control form-control-sm defeito-campo-manutencao defeito-data-previsao d-none" value="${new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10)}">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm defeito-campo-manutencao defeito-descricao d-none" value="${descricaoPadrao}" placeholder="Descrição da manutenção">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm defeito-campo-manutencao defeito-responsavel d-none" placeholder="Responsável">
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm defeito-observacoes" value="${sugestaoDescricao}" placeholder="Observações">
                    </td>
                </tr>
            `);
        });

        if (modalDefeitoRetorno) modalDefeitoRetorno.show();
    }

    function formatarDataHoraInputAgora() {
        const agora = new Date();
        const yyyy = agora.getFullYear();
        const mm = String(agora.getMonth() + 1).padStart(2, '0');
        const dd = String(agora.getDate()).padStart(2, '0');
        const hh = String(agora.getHours()).padStart(2, '0');
        const mi = String(agora.getMinutes()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}T${hh}:${mi}`;
    }

    function formatarDataBr(dataIso) {
        if (!dataIso) return '-';
        const partes = dataIso.split('-');
        if (partes.length !== 3) return dataIso;
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    function abrirModalRetornoParcial(idLocacao, itens) {
        locacaoRetornoParcialAtual = idLocacao;
        itensRetornoParcialAtual = Array.isArray(itens) ? itens : [];

        const $tbody = $('#tabelaRetornoParcial tbody');
        $tbody.empty();

        if (itensRetornoParcialAtual.length === 0) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Nenhum item elegível para retorno parcial.</td></tr>');
        }

        itensRetornoParcialAtual.forEach((item) => {
            const retornado = Number(item.estoque_status || 0) === 2
                || ![null, '', 'pendente'].includes(item.status_retorno);
            const temPatrimonio = !!item.id_patrimonio;
            const quantidadeTotal = Math.max(1, Number(item.quantidade || 1));

            const statusTexto = retornado ? 'Retornado' : 'Pendente';
            const statusBadge = retornado ? 'success' : 'warning';

            $tbody.append(`
                <tr data-id-produto-locacao="${item.id_produto_locacao}">
                    <td>
                        <input
                            class="form-check-input retorno-parcial-item"
                            type="checkbox"
                            value="${item.id_produto_locacao}"
                            ${retornado ? 'disabled' : ''}
                        >
                    </td>
                    <td>${item.nome || '-'}</td>
                    <td>${item.patrimonio || '-'}</td>
                    <td>${formatarDataBr(item.data_inicio)} ${item.hora_inicio ? String(item.hora_inicio).substring(0, 5) : ''}</td>
                    <td>
                        <input
                            type="number"
                            class="form-control form-control-sm retorno-parcial-qtd"
                            min="1"
                            max="${quantidadeTotal}"
                            value="1"
                            ${retornado ? 'disabled' : ''}
                            ${temPatrimonio ? 'readonly' : ''}
                        >
                        <small class="text-muted">de ${quantidadeTotal}</small>
                    </td>
                    <td>${item.valor_fechado ? 'Valor fechado' : 'Diária'}</td>
                    <td><span class="badge bg-label-${statusBadge}">${statusTexto}</span></td>
                </tr>
            `);

            if (temPatrimonio) {
                const $ultimaLinha = $tbody.find('tr').last();
                $ultimaLinha.find('.retorno-parcial-qtd').val(1);
            }
        });

        $('#retornoParcialDataHora').val(formatarDataHoraInputAgora());
        $('#retornoParcialNaoRecalcular').prop('checked', false);
        if (modalRetornoParcial) modalRetornoParcial.show();
    }

    function executarRetornoParcial() {
        if (!locacaoRetornoParcialAtual) return;

        const itensSelecionados = [];
        $('.retorno-parcial-item:checked').each(function () {
            const $row = $(this).closest('tr');
            const idProdutoLocacao = $row.data('id-produto-locacao');
            const $inputQtd = $row.find('.retorno-parcial-qtd');
            const qtdMax = Math.max(1, Number($inputQtd.attr('max') || 1));
            let qtdRetorno = Math.max(1, Number($inputQtd.val() || 1));

            if (qtdRetorno > qtdMax) {
                qtdRetorno = qtdMax;
            }

            itensSelecionados.push({
                id_produto_locacao: idProdutoLocacao,
                quantidade_retorno: qtdRetorno
            });
        });

        const dataHoraRetorno = $('#retornoParcialDataHora').val();
        const naoRecalcularValor = $('#retornoParcialNaoRecalcular').is(':checked') ? 1 : 0;

        if (itensSelecionados.length === 0) {
            Swal.fire('Atenção', 'Selecione ao menos um produto para retorno parcial.', 'warning');
            return;
        }

        if (!dataHoraRetorno) {
            Swal.fire('Atenção', 'Informe a data e hora de devolução.', 'warning');
            return;
        }

        $.ajax({
            url: `{{ url('locacoes') }}/${locacaoRetornoParcialAtual}/retorno-parcial`,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                itens: itensSelecionados,
                data_hora_retorno: dataHoraRetorno,
                nao_recalcular_valor: naoRecalcularValor
            },
            success: function (response) {
                if (modalRetornoParcial) modalRetornoParcial.hide();
                Swal.fire('Sucesso!', response.message || 'Retorno parcial realizado com sucesso.', 'success')
                    .then(() => location.reload());
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                if (modalRetornoParcial) modalRetornoParcial.hide();
                Swal.fire('Erro!', response.message || 'Não foi possível realizar o retorno parcial.', 'error');
            }
        });
    }

    function abrirModalRenovacao(idLocacao, response) {
        locacaoRenovacaoAtual = idLocacao;

        const locacao = response?.locacao || {};
        const periodo = response?.periodo_padrao || {};
        const itens = Array.isArray(response?.itens) ? response.itens : [];

        $('#renovacaoContratoInfo').text(`Contrato ${locacao.numero_contrato || '-'} | Aditivo atual: ${locacao.aditivo || 1} | Próximo: ${(Number(locacao.aditivo || 1) + 1)}`);
        $('#renovacaoDataInicio').val(periodo.data_inicio || '');
        $('#renovacaoHoraInicio').val((periodo.hora_inicio || '00:00:00').substring(0, 5));
        $('#renovacaoDataFim').val(periodo.data_fim || '');
        $('#renovacaoHoraFim').val((periodo.hora_fim || '23:59:59').substring(0, 5));
        $('#renovacaoAutomatica').prop('checked', !!locacao.renovacao_automatica);

        const $tbody = $('#tabelaRenovacaoAditivo tbody');
        $tbody.empty();

        if (itens.length === 0) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted py-3">Nenhum item disponível para renovação.</td></tr>');
        }

        itens.forEach((item) => {
            const qtd = Math.max(1, Number(item.quantidade || 1));
            const temPatrimonio = !!item.id_patrimonio;
            const retornado = !!item.retornado;
            const dataInicioItem = item.data_inicio || periodo.data_inicio || '';
            const horaInicioItem = String(item.hora_inicio || periodo.hora_inicio || '00:00:00').substring(0, 5);
            const dataFimItem = item.data_fim || periodo.data_fim || '';
            const horaFimItem = String(item.hora_fim || periodo.hora_fim || '23:59:59').substring(0, 5);

            $tbody.append(`
                <tr data-id-produto-locacao="${item.id_produto_locacao}">
                    <td><input type="checkbox" class="form-check-input renovacao-item" ${retornado ? 'disabled' : 'checked'}></td>
                    <td>${item.nome || '-'}</td>
                    <td>${item.patrimonio || '-'}</td>
                    <td><input type="datetime-local" class="form-control form-control-sm renovacao-item-inicio" value="${dataInicioItem && horaInicioItem ? `${dataInicioItem}T${horaInicioItem}` : ''}" ${retornado ? 'disabled' : ''}></td>
                    <td><input type="datetime-local" class="form-control form-control-sm renovacao-item-fim" value="${dataFimItem && horaFimItem ? `${dataFimItem}T${horaFimItem}` : ''}" ${retornado ? 'disabled' : ''}></td>
                    <td>
                        <input type="number" min="1" max="${qtd}" value="${temPatrimonio ? 1 : qtd}" class="form-control form-control-sm renovacao-qtd" ${retornado ? 'disabled' : ''} ${temPatrimonio ? 'readonly' : ''}>
                        <small class="text-muted">de ${qtd}</small>
                    </td>
                    <td><span class="badge bg-label-${retornado ? 'success' : 'warning'}">${retornado ? 'Retornado' : 'Pendente'}</span></td>
                </tr>
            `);
        });

        if (modalRenovarAditivo) modalRenovarAditivo.show();
    }

    function executarRenovacaoAditivo() {
        if (!locacaoRenovacaoAtual) return;

        const itens = [];
        const dataInicioContrato = $('#renovacaoDataInicio').val();
        const horaInicioContrato = $('#renovacaoHoraInicio').val();
        const dataFimContrato = $('#renovacaoDataFim').val();
        const horaFimContrato = $('#renovacaoHoraFim').val();

        if (!dataInicioContrato || !dataFimContrato) {
            Swal.fire('Atenção', 'Informe o período do contrato para o aditivo.', 'warning');
            return;
        }

        const inicioContrato = new Date(`${dataInicioContrato}T${horaInicioContrato || '00:00'}`);
        const fimContrato = new Date(`${dataFimContrato}T${horaFimContrato || '23:59'}`);
        if (fimContrato < inicioContrato) {
            Swal.fire('Atenção', 'A data/hora final do contrato deve ser maior que a inicial.', 'warning');
            return;
        }

        $('#tabelaRenovacaoAditivo tbody tr').each(function () {
            const $tr = $(this);
            const marcado = $tr.find('.renovacao-item').is(':checked');
            if (!marcado) {
                return;
            }

            const idProdutoLocacao = Number($tr.data('id-produto-locacao') || 0);
            let qtd = Number($tr.find('.renovacao-qtd').val() || 1);
            const qtdMax = Number($tr.find('.renovacao-qtd').attr('max') || 1);
            const inicioItemTexto = String($tr.find('.renovacao-item-inicio').val() || '');
            const fimItemTexto = String($tr.find('.renovacao-item-fim').val() || '');

            if (qtd < 1) qtd = 1;
            if (qtd > qtdMax) qtd = qtdMax;

            if (!inicioItemTexto || !fimItemTexto) {
                return;
            }

            const inicioItem = new Date(inicioItemTexto);
            const fimItem = new Date(fimItemTexto);
            if (fimItem < inicioItem || inicioItem < inicioContrato || fimItem > fimContrato) {
                return;
            }

            const [dataInicioItem, horaInicioItem] = inicioItemTexto.split('T');
            const [dataFimItem, horaFimItem] = fimItemTexto.split('T');

            if (idProdutoLocacao > 0) {
                itens.push({
                    id_produto_locacao: idProdutoLocacao,
                    quantidade: qtd,
                    data_inicio: dataInicioItem,
                    hora_inicio: `${(horaInicioItem || '00:00').substring(0, 5)}:00`,
                    data_fim: dataFimItem,
                    hora_fim: `${(horaFimItem || '23:59').substring(0, 5)}:00`,
                });
            }
        });

        if (itens.length === 0) {
            Swal.fire('Atenção', 'Selecione itens válidos com período por item dentro do período do contrato.', 'warning');
            return;
        }

        Swal.fire({
            title: 'Validando estoque...',
            text: 'Aguarde, estamos verificando disponibilidade e criando o aditivo.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
        });

        $.ajax({
            url: `{{ url('locacoes') }}/${locacaoRenovacaoAtual}/renovar-aditivo`,
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                data_inicio: dataInicioContrato,
                hora_inicio: `${(horaInicioContrato || '00:00').substring(0, 5)}:00`,
                data_fim: dataFimContrato,
                hora_fim: `${(horaFimContrato || '23:59').substring(0, 5)}:00`,
                renovacao_automatica: $('#renovacaoAutomatica').is(':checked') ? 1 : 0,
                itens: itens,
            },
            success: function (response) {
                Swal.close();
                fecharModalRenovacaoAditivo();
                setTimeout(function () {
                    Swal.fire('Sucesso!', response.message || 'Aditivo criado com sucesso.', 'success').then(() => location.reload());
                }, 200);
            },
            error: function (xhr) {
                Swal.close();
                fecharModalRenovacaoAditivo();
                const response = xhr.responseJSON || {};
                setTimeout(function () {
                    Swal.fire('Erro!', response.message || 'Não foi possível renovar a locação.', 'error');
                }, 200);
            }
        });
    }

    $(document).on('click', '.btn-toggle-acoes', function () {
        const target = $(this).data('target');
        if (!target) return;

        $('.linha-acoes-locacao').not(target).addClass('d-none');
        $(target).toggleClass('d-none');
    });

    $(document).on('click', '.btn-log-atividades-locacao', function () {
        const idLocacao = Number($(this).data('id') || 0);
        if (!idLocacao) return;

        abrirLogAtividadesLocacao(
            idLocacao,
            String($(this).data('codigo') || ''),
            String($(this).data('cliente') || '')
        );
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

    $(document).on('change', '.defeito-acao', function() {
        const $linha = $(this).closest('tr');
        const exibirCamposManutencao = $(this).val() === 'gerar_manutencao';
        $linha.find('.defeito-campo-manutencao').toggleClass('d-none', !exibirCamposManutencao);
    });

    $(document).on('click', '.btn-retorno-parcial', function () {
        const idLocacao = $(this).data('id');

        $.ajax({
            url: `{{ url('locacoes') }}/${idLocacao}/itens-retorno-parcial`,
            type: 'GET',
            success: function (response) {
                const itens = Array.isArray(response?.itens) ? response.itens : [];
                abrirModalRetornoParcial(idLocacao, itens);
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                Swal.fire('Erro!', response.message || 'Não foi possível carregar os itens para retorno parcial.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-renovar-aditivo', function () {
        const idLocacao = $(this).data('id');

        $.ajax({
            url: `{{ url('locacoes') }}/${idLocacao}/itens-renovacao`,
            type: 'GET',
            success: function (response) {
                abrirModalRenovacao(idLocacao, response);
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                Swal.fire('Erro!', response.message || 'Não foi possível carregar os dados para renovação.', 'error');
            }
        });
    });

    $(document).on('click', '.btn-alterar-status', function() {
        const idLocacao = $(this).data('id');
        const status = $(this).data('status');
        const label = $(this).data('label') || 'alterar status';

        Swal.fire({
            title: `Deseja ${label}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sim',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: `{{ url('locacoes') }}/${idLocacao}/status`,
                type: 'PATCH',
                data: { _token: '{{ csrf_token() }}', status: status },
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Sucesso!', response.message || 'Status alterado com sucesso.', 'success').then(() => location.reload());
                    }
                },
                error: function(xhr) {
                    Swal.fire('Erro!', xhr.responseJSON?.message || 'Erro ao alterar status.', 'error');
                }
            });
        });
    });

    $('#btnConfirmarRetornoModal').on('click', function() {
        if (!locacaoRetornoAtual) return;
        const retornos = [];
        $('#tabelaRetornoPatrimonios tbody tr').each(function() {
            retornos.push({
                id_produto_locacao: $(this).data('id-produto-locacao'),
                status: $(this).find('.retorno-status').val(),
                observacoes: $(this).find('.retorno-observacoes').val()
            });
        });

        if (modalRetorno) modalRetorno.hide();
        executarRetornoLocacao(locacaoRetornoAtual, retornos);
    });

    $('#btnConfirmarDefeitoRetorno').on('click', function() {
        if (!locacaoDefeitoAtual) return;

        const decisoes = [];
        let erroValidacao = null;

        $('#tabelaDefeitoRetorno tbody tr').each(function() {
            if (erroValidacao) return;

            const $linha = $(this);
            const idProdutoLocacao = Number($linha.data('id-produto-locacao') || 0);
            const acao = $linha.find('.defeito-acao').val() || 'retornar_estoque';
            const observacoes = ($linha.find('.defeito-observacoes').val() || '').toString().trim();
            const qtdMax = Number($linha.find('.defeito-quantidade').attr('max') || 1);
            let quantidadeDefeito = Math.max(1, Number($linha.find('.defeito-quantidade').val() || 1));
            if (quantidadeDefeito > qtdMax) quantidadeDefeito = qtdMax;
            $linha.find('.defeito-quantidade').val(String(quantidadeDefeito));

            const decisao = {
                id_produto_locacao: idProdutoLocacao,
                acao: acao,
                quantidade_defeito: quantidadeDefeito,
                observacoes: observacoes,
            };

            if (acao === 'gerar_manutencao') {
                const tipo = ($linha.find('.defeito-tipo').val() || 'corretiva').toString().trim();
                const dataManutencao = ($linha.find('.defeito-data').val() || '').toString().trim();
                const dataPrevisao = ($linha.find('.defeito-data-previsao').val() || '').toString().trim();
                const descricao = ($linha.find('.defeito-descricao').val() || '').toString().trim();
                const responsavel = ($linha.find('.defeito-responsavel').val() || '').toString().trim();

                if (!dataManutencao || !dataPrevisao || !descricao) {
                    erroValidacao = 'Preencha data de manutenção, data de previsão e descrição para os itens com manutenção.';
                    return;
                }

                if (dataPrevisao < dataManutencao) {
                    erroValidacao = 'A data de previsão não pode ser menor que a data da manutenção.';
                    return;
                }

                decisao.tipo = tipo;
                decisao.data_manutencao = dataManutencao;
                decisao.data_previsao = dataPrevisao;
                decisao.descricao = descricao;
                decisao.responsavel = responsavel;
            }

            if (idProdutoLocacao > 0) {
                decisoes.push(decisao);
            }
        });

        if (erroValidacao) {
            Swal.fire('Atenção', erroValidacao, 'warning');
            return;
        }

        if (modalDefeitoRetorno) modalDefeitoRetorno.hide();

        executarRetornoLocacao(locacaoDefeitoAtual, retornosDefeitoPendentes, {
            ...opcoesDefeitoPendentes,
            decisoesAvaria: decisoes,
        });
    });

    $('#btnConfirmarRetornoParcial').on('click', function () {
        executarRetornoParcial();
    });

    $('#btnConfirmarRenovacaoAditivo').on('click', function () {
        executarRenovacaoAditivo();
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
