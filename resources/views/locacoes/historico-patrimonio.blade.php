@extends('layouts.layoutMaster')

@section('title', 'Histórico do Patrimônio')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="ti ti-history me-2"></i>
                            Histórico do Patrimônio
                        </h5>
                        @if($patrimonio)
                            <small class="text-muted">
                                {{ $patrimonio->numero_serie ?? ('PAT-' . $patrimonio->id_patrimonio) }}
                                @if($patrimonio->produto)
                                    - {{ $patrimonio->produto->nome }}
                                @endif
                            </small>
                        @endif
                    </div>
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Voltar
                    </a>
                </div>
            </div>

            @if($patrimonio)
                <div class="row">
                    <!-- Info do Patrimônio -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Informações do Patrimônio</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Código:</strong> {{ $patrimonio->numero_serie ?? ('PAT-' . $patrimonio->id_patrimonio) }}
                                </p>
                                <p class="mb-2">
                                    <strong>Produto:</strong> {{ $patrimonio->produto->nome ?? 'N/A' }}
                                </p>
                                <p class="mb-2">
                                    <strong>Status Atual:</strong>
                                    @php
                                        $statusColors = [
                                            'disponivel' => 'success',
                                            'em_locacao' => 'primary',
                                            'em_manutencao' => 'warning',
                                            'extraviado' => 'danger',
                                            'indisponivel' => 'secondary'
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $statusColors[$patrimonio->status] ?? 'secondary' }}">
                                        {{ ucfirst(str_replace('_', ' ', $patrimonio->status ?? 'Indefinido')) }}
                                    </span>
                                </p>
                                @if($patrimonio->observacoes)
                                    <p class="mb-0">
                                        <strong>Observações:</strong><br>
                                        <small class="text-muted">{{ $patrimonio->observacoes }}</small>
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Timeline de Histórico -->
                    <div class="col-md-8 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Movimentações</h6>
                            </div>
                            <div class="card-body">
                                @if($historico->count() > 0)
                                    <ul class="timeline">
                                        @foreach($historico as $item)
                                            @php
                                                $tipoIcons = [
                                                    'saida_locacao' => ['icon' => 'ti-truck-delivery', 'color' => 'primary'],
                                                    'retorno_locacao' => ['icon' => 'ti-package-import', 'color' => 'success'],
                                                    'manutencao' => ['icon' => 'ti-tool', 'color' => 'warning'],
                                                    'avaria' => ['icon' => 'ti-alert-triangle', 'color' => 'danger'],
                                                    'extravio' => ['icon' => 'ti-alert-circle', 'color' => 'danger'],
                                                    'devolucao' => ['icon' => 'ti-refresh', 'color' => 'info'],
                                                    'cadastro' => ['icon' => 'ti-plus', 'color' => 'secondary'],
                                                ];
                                                $tipoConfig = $tipoIcons[$item->tipo_movimentacao] ?? ['icon' => 'ti-circle', 'color' => 'secondary'];
                                            @endphp
                                            <li class="timeline-item timeline-item-transparent">
                                                <span class="timeline-point timeline-point-{{ $tipoConfig['color'] }}"></span>
                                                <div class="timeline-event">
                                                    <div class="timeline-header mb-1">
                                                        <h6 class="mb-0">
                                                            <i class="ti {{ $tipoConfig['icon'] }} me-1"></i>
                                                            {{ ucfirst(str_replace('_', ' ', $item->tipo_movimentacao)) }}
                                                        </h6>
                                                        <small class="text-muted">
                                                            {{ $item->data_movimentacao->format('d/m/Y H:i') }}
                                                        </small>
                                                    </div>
                                                    <p class="mb-1">
                                                        @if($item->locacao)
                                                            Locação: <a href="{{ route('locacoes.show', $item->id_locacao) }}">#{{ $item->locacao->numero_contrato }}</a>
                                                            @if($item->locacao->cliente)
                                                                - {{ $item->locacao->cliente->nome }}
                                                            @endif
                                                        @endif
                                                    </p>
                                                    @if($item->observacoes)
                                                        <p class="mb-0 text-muted small">
                                                            <i class="ti ti-note me-1"></i>{{ $item->observacoes }}
                                                        </p>
                                                    @endif
                                                    @if($item->usuario)
                                                        <small class="text-muted">
                                                            Por: {{ $item->usuario->name ?? 'Sistema' }}
                                                        </small>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                    
                                    {{ $historico->links() }}
                                @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="ti ti-history ti-lg d-block mb-2"></i>
                                        Nenhuma movimentação registrada para este patrimônio.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="ti ti-alert-circle ti-lg text-warning d-block mb-2"></i>
                        <h6>Patrimônio não encontrado</h6>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
