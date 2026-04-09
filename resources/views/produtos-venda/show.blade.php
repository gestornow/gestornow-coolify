@extends('layouts.layoutMaster')

@section('title', 'Detalhes do Produto')

@section('page-style')
<style>
    .produto-card {
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border: 1px solid #667eea30;
    }

    .info-label {
        font-weight: 600;
        color: #666;
        font-size: 0.85rem;
    }

    .info-value {
        font-size: 1rem;
    }

    html.dark-style .produto-card {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
        border-color: #444b6e;
    }

    html.dark-style .info-label {
        color: #a0a4b8;
    }
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 pt-1">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-package me-2"></i>
                        {{ $produto->nome }}
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('produtos-venda.edit', $produto->id_produto_venda) }}" class="btn btn-primary btn-sm">
                            <i class="ti ti-pencil me-1"></i> Editar
                        </a>
                        <a href="{{ route('produtos-venda.index') }}" class="btn btn-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-4">
                        <!-- Informações Básicas -->
                        <div class="col-12">
                            <div class="card produto-card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-info-circle me-2"></i>
                                        Informações Básicas
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="info-label">Nome</div>
                                            <div class="info-value">{{ $produto->nome }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-label">Código</div>
                                            <div class="info-value">{{ $produto->codigo ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="info-label">Número de Série</div>
                                            <div class="info-value">{{ $produto->numero_serie ?? '-' }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Status</div>
                                            <div class="info-value">
                                                @if($produto->status === 'ativo')
                                                    <span class="badge bg-label-success">Ativo</span>
                                                @else
                                                    <span class="badge bg-label-warning">Inativo</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Estoque</div>
                                            <div class="info-value">
                                                @php $qtd = $produto->quantidade ?? 0; @endphp
                                                @if($qtd <= 0)
                                                    <span class="text-danger fw-bold">{{ $qtd }} (Sem estoque)</span>
                                                @elseif($qtd <= 5)
                                                    <span class="text-warning fw-bold">{{ $qtd }} (Estoque baixo)</span>
                                                @else
                                                    <span class="text-success fw-bold">{{ $qtd }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($produto->descricao)
                                        <div class="col-md-12">
                                            <div class="info-label">Descrição</div>
                                            <div class="info-value">{{ $produto->descricao }}</div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preços -->
                        <div class="col-md-6">
                            <div class="card produto-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-currency-dollar me-2"></i>
                                        Preços
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="info-label">Preço de Custo</div>
                                            <div class="info-value">{{ $produto->preco_custo_formatado }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="info-label">Preço de Venda</div>
                                            <div class="info-value text-success fw-bold">{{ $produto->preco_formatado }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="info-label">Preço de Reposição</div>
                                            <div class="info-value">R$ {{ number_format($produto->preco_reposicao ?? 0, 2, ',', '.') }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="info-label">Preço de Locação</div>
                                            <div class="info-value">R$ {{ number_format($produto->preco_locacao ?? 0, 2, ',', '.') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dimensões -->
                        <div class="col-md-6">
                            <div class="card produto-card h-100">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-ruler me-2"></i>
                                        Dimensões e Peso
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="info-label">Altura</div>
                                            <div class="info-value">{{ $produto->altura ? number_format($produto->altura, 2, ',', '.') . ' cm' : '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="info-label">Largura</div>
                                            <div class="info-value">{{ $produto->largura ? number_format($produto->largura, 2, ',', '.') . ' cm' : '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="info-label">Profundidade</div>
                                            <div class="info-value">{{ $produto->profundidade ? number_format($produto->profundidade, 2, ',', '.') . ' cm' : '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="info-label">Peso</div>
                                            <div class="info-value">{{ $produto->peso ? number_format($produto->peso, 2, ',', '.') . ' kg' : '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalhes -->
                        @if($produto->detalhes)
                        <div class="col-12">
                            <div class="card produto-card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-notes me-2"></i>
                                        Detalhes Adicionais
                                    </h6>
                                    <div class="info-value">{{ $produto->detalhes }}</div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <!-- Datas -->
                        <div class="col-12">
                            <div class="card produto-card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-calendar me-2"></i>
                                        Informações do Registro
                                    </h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="info-label">Cadastrado em</div>
                                            <div class="info-value">{{ $produto->created_at ? $produto->created_at->format('d/m/Y H:i') : '-' }}</div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="info-label">Última atualização</div>
                                            <div class="info-value">{{ $produto->updated_at ? $produto->updated_at->format('d/m/Y H:i') : '-' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
