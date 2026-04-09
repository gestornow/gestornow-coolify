@extends('layouts.layoutMaster')

@section('title', 'Histórico do Produto')

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
                            Histórico do Produto
                        </h5>
                        @if($produto)
                            <small class="text-muted">{{ $produto->nome }}</small>
                        @endif
                    </div>
                    <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Voltar
                    </a>
                </div>
            </div>

            @if($produto)
                <div class="row">
                    <!-- Info do Produto -->
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Informações do Produto</h6>
                            </div>
                            <div class="card-body">
                                @if($produto->foto_url)
                                    <div class="text-center mb-3">
                                        <img src="{{ $produto->foto_url }}" class="img-fluid rounded" style="max-height: 150px;">
                                    </div>
                                @endif
                                <p class="mb-2">
                                    <strong>Nome:</strong> {{ $produto->nome }}
                                </p>
                                <p class="mb-2">
                                    <strong>Código:</strong> {{ $produto->codigo ?? 'N/A' }}
                                </p>
                                <p class="mb-2">
                                    <strong>Estoque Total:</strong> {{ $produto->quantidade ?? 0 }}
                                </p>
                                <p class="mb-2">
                                    <strong>Em Locação:</strong> {{ $produto->quantidade_locada ?? 0 }}
                                </p>
                                <p class="mb-0">
                                    <strong>Disponível:</strong> 
                                    <span class="badge bg-{{ ($produto->quantidade - ($produto->quantidade_locada ?? 0)) > 0 ? 'success' : 'danger' }}">
                                        {{ ($produto->quantidade ?? 0) - ($produto->quantidade_locada ?? 0) }}
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Patrimônios do Produto -->
                    @if($produto->patrimonios && $produto->patrimonios->count() > 0)
                        <div class="col-md-8 mb-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Patrimônios ({{ $produto->patrimonios->count() }})</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Código</th>
                                                    <th>Status</th>
                                                    <th>Localização Atual</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($produto->patrimonios as $pat)
                                                    @php
                                                        $statusColors = [
                                                            'disponivel' => 'success',
                                                            'em_locacao' => 'primary',
                                                            'em_manutencao' => 'warning',
                                                            'extraviado' => 'danger',
                                                            'indisponivel' => 'secondary'
                                                        ];
                                                    @endphp
                                                    <tr>
                                                        <td><strong>{{ $pat->numero_serie ?? ('PAT-' . $pat->id_patrimonio) }}</strong></td>
                                                        <td>
                                                            <span class="badge bg-{{ $statusColors[$pat->status] ?? 'secondary' }}">
                                                                {{ ucfirst(str_replace('_', ' ', $pat->status ?? 'Indefinido')) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if($pat->status === 'em_locacao' && $pat->locacaoAtual)
                                                                <a href="{{ route('locacoes.show', $pat->locacaoAtual->id_locacao) }}">
                                                                    #{{ $pat->locacaoAtual->numero_contrato }}
                                                                </a>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <a href="{{ route('locacoes.historico-patrimonio', $pat->id_patrimonio) }}" 
                                                               class="btn btn-xs btn-outline-primary">
                                                                <i class="ti ti-history ti-xs"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Timeline de Histórico -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Movimentações de Estoque</h6>
                            </div>
                            <div class="card-body">
                                @if($historico->count() > 0)
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Data</th>
                                                    <th>Tipo</th>
                                                    <th>Qtd</th>
                                                    <th>Estoque Após</th>
                                                    <th>Referência</th>
                                                    <th>Observações</th>
                                                    <th>Usuário</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($historico as $item)
                                                    @php
                                                        $tipoColors = [
                                                            'entrada' => 'success',
                                                            'saida' => 'danger',
                                                            'reserva' => 'warning',
                                                            'retorno' => 'info',
                                                            'ajuste' => 'secondary',
                                                        ];
                                                    @endphp
                                                    <tr>
                                                        <td>{{ $item->data_movimentacao->format('d/m/Y H:i') }}</td>
                                                        <td>
                                                            <span class="badge bg-{{ $tipoColors[$item->tipo_movimentacao] ?? 'secondary' }}">
                                                                {{ ucfirst($item->tipo_movimentacao) }}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            @if(in_array($item->tipo_movimentacao, ['entrada', 'retorno']))
                                                                <span class="text-success">+{{ $item->quantidade }}</span>
                                                            @else
                                                                <span class="text-danger">-{{ $item->quantidade }}</span>
                                                            @endif
                                                        </td>
                                                        <td>{{ $item->quantidade_apos }}</td>
                                                        <td>
                                                            @if($item->id_locacao && $item->locacao)
                                                                <a href="{{ route('locacoes.show', $item->id_locacao) }}">
                                                                    Locação #{{ $item->locacao->numero_contrato }}
                                                                </a>
                                                            @elseif($item->referencia_tipo && $item->referencia_id)
                                                                {{ ucfirst($item->referencia_tipo) }} #{{ $item->referencia_id }}
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <small class="text-muted">{{ $item->observacoes ?? '-' }}</small>
                                                        </td>
                                                        <td>
                                                            <small>{{ $item->usuario->name ?? 'Sistema' }}</small>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    {{ $historico->links() }}
                                @else
                                    <div class="text-center py-4 text-muted">
                                        <i class="ti ti-history ti-lg d-block mb-2"></i>
                                        Nenhuma movimentação registrada para este produto.
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
                        <h6>Produto não encontrado</h6>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
