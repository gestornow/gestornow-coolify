@extends('layouts.layoutMaster')

@section('title', 'Pedidos de Locação')

@section('page-style')
<style>
    .pedido-card {
        border: 1px solid #e9edf3;
        border-radius: .6rem;
        height: 100%;
    }

    .pedido-card .titulo {
        font-size: .92rem;
        font-weight: 700;
        color: #566a7f;
    }

    .pedido-card .meta {
        font-size: .8rem;
        color: #8b93a5;
    }

    .pedido-card .linha {
        font-size: .84rem;
        color: #697a8d;
        margin-bottom: .2rem;
    }

    .pedidos-abas .nav-link {
        border: 1px solid #d9dee3;
        border-radius: .5rem;
        color: #566a7f;
        font-weight: 600;
        margin-right: .4rem;
    }

    .pedidos-abas .nav-link.active {
        background: rgba(105, 108, 255, .12);
        border-color: rgba(105, 108, 255, .3);
        color: #696cff;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-label-primary">{{ $hoje->format('d/m/Y') }}</span>
            </div>
        </div>
        <div class="card-body pt-2">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="text-muted small">Iniciam hoje</div>
                            <div class="h5 mb-0">{{ (int) ($totais['iniciam_hoje'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="text-muted small">Terminam hoje</div>
                            <div class="h5 mb-0">{{ (int) ($totais['terminam_hoje'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="text-muted small">Valor iniciam hoje</div>
                            <div class="h6 mb-0">R$ {{ number_format((float) ($totais['valor_iniciam_hoje'] ?? 0), 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border">
                        <div class="card-body py-2 px-3">
                            <div class="text-muted small">Valor terminam hoje</div>
                            <div class="h6 mb-0">R$ {{ number_format((float) ($totais['valor_terminam_hoje'] ?? 0), 2, ',', '.') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header pb-0">
            <ul class="nav nav-pills pedidos-abas">
                <li class="nav-item">
                    <a class="nav-link {{ $aba === 'iniciam_hoje' ? 'active' : '' }}" href="{{ route('locacoes.pedidos', ['aba' => 'iniciam_hoje']) }}">
                        Iniciam hoje <span class="badge bg-label-primary ms-1">{{ (int) ($totais['iniciam_hoje'] ?? 0) }}</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $aba === 'terminam_hoje' ? 'active' : '' }}" href="{{ route('locacoes.pedidos', ['aba' => 'terminam_hoje']) }}">
                        Terminam hoje <span class="badge bg-label-warning ms-1">{{ (int) ($totais['terminam_hoje'] ?? 0) }}</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="card-body pt-3">
            @php
                $listaPedidos = $aba === 'terminam_hoje' ? ($pedidosTerminamHoje ?? collect()) : ($pedidosIniciamHoje ?? collect());
            @endphp

            <div class="row g-3">
                @forelse($listaPedidos as $locacao)
                    @php
                        $statusCor = [
                            'aprovado' => 'success',
                            'retirada' => 'info',
                            'em_andamento' => 'primary',
                            'atrasada' => 'danger',
                            'encerrado' => 'secondary',
                        ][$locacao->status] ?? 'secondary';
                    @endphp
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card pedido-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <div class="titulo">Contrato #{{ $locacao->codigo_display }}</div>
                                        <div class="meta">{{ $locacao->cliente->nome ?? 'Cliente não informado' }}</div>
                                    </div>
                                    <span class="badge bg-label-{{ $statusCor }}">{{ \App\Domain\Locacao\Models\Locacao::statusList()[$locacao->status] ?? $locacao->status }}</span>
                                </div>

                                <div class="linha"><strong>Início:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ? substr($locacao->hora_inicio, 0, 5) : '' }}</div>
                                <div class="linha"><strong>Fim:</strong> {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ? substr($locacao->hora_fim, 0, 5) : '' }}</div>
                                <div class="linha"><strong>Itens:</strong> {{ (int) ($locacao->itens_count ?? 0) }}</div>
                                <div class="linha mb-2"><strong>Valor:</strong> R$ {{ number_format((float) ($locacao->valor_total_listagem ?? $locacao->valor_total ?? 0), 2, ',', '.') }}</div>

                                <div class="d-flex gap-2">
                                    <a href="{{ route('locacoes.show', $locacao->id_locacao) }}" class="btn btn-sm btn-outline-secondary">Visualizar</a>
                                    <a href="{{ route('locacoes.contrato-pdf', $locacao->id_locacao) }}?tipo=contrato" target="_blank" class="btn btn-sm btn-outline-primary">Contrato PDF</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="text-center text-muted py-4">Nenhum pedido para a aba selecionada.</div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
