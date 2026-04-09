@extends('layouts.layoutMaster')

@section('title', 'Visualizar Acessório - ' . ($acessorio->nome ?? 'Acessório'))

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-plug me-2"></i>
                        {{ $acessorio->nome }}
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('acessorios.edit', $acessorio->id_acessorio) }}" class="btn btn-primary btn-sm">
                            <i class="ti ti-pencil me-1"></i> Editar
                        </a>
                        <a href="{{ route('acessorios.index') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="ti ti-arrow-left me-1"></i> Voltar
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Coluna Principal -->
                        <div class="col-md-8">
                            <!-- Informações Básicas -->
                            <div class="card mb-4" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 1px solid #667eea30;">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-info-circle me-2"></i>
                                        Informações Básicas
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small text-muted">Nome</label>
                                            <p class="mb-0 fw-medium">{{ $acessorio->nome }}</p>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label small text-muted">Código</label>
                                            <p class="mb-0 fw-medium">{{ $acessorio->codigo ?? '-' }}</p>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label class="form-label small text-muted">Número de Série</label>
                                            <p class="mb-0 fw-medium">{{ $acessorio->numero_serie ?? '-' }}</p>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small text-muted">Descrição</label>
                                            <p class="mb-0">{{ $acessorio->descricao ?? 'Sem descrição' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preços -->
                            <div class="card mb-4" style="background: linear-gradient(135deg, #11998e15 0%, #38ef7d15 100%); border: 1px solid #11998e30;">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-currency-dollar me-2"></i>
                                        Preços
                                    </h6>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label small text-muted">Preço de Custo</label>
                                            <p class="mb-0 fw-medium text-danger">R$ {{ number_format($acessorio->preco_custo ?? 0, 2, ',', '.') }}</p>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label small text-muted">Preço de Venda</label>
                                            <p class="mb-0 fw-medium text-success fs-5">R$ {{ number_format($acessorio->preco_venda ?? 0, 2, ',', '.') }}</p>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label small text-muted">Preço de Locação</label>
                                            <p class="mb-0 fw-medium text-info fs-5">R$ {{ number_format($acessorio->preco_locacao ?? 0, 2, ',', '.') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Produtos Vinculados -->
                            @if($acessorio->produtos && $acessorio->produtos->count() > 0)
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-package me-2"></i>
                                        Produtos Vinculados
                                    </h6>
                                    
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Produto</th>
                                                    <th>Quantidade</th>
                                                    <th>Obrigatório</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($acessorio->produtos as $produto)
                                                <tr>
                                                    <td>{{ $produto->nome }}</td>
                                                    <td>{{ $produto->pivot->quantidade ?? 1 }}</td>
                                                    <td>
                                                        @if($produto->pivot->obrigatorio)
                                                            <span class="badge bg-label-success">Sim</span>
                                                        @else
                                                            <span class="badge bg-label-secondary">Não</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>

                        <!-- Coluna Lateral -->
                        <div class="col-md-4">
                            <!-- Status -->
                            <div class="card mb-4">
                                <div class="card-body text-center">
                                    <div class="avatar avatar-xl mb-3">
                                        <span class="avatar-initial rounded-circle bg-label-info fs-1">{{ $acessorio->inicial }}</span>
                                    </div>
                                    <h5 class="mb-1">{{ $acessorio->nome }}</h5>
                                    @if($acessorio->status === 'ativo')
                                        <span class="badge bg-label-success">Ativo</span>
                                    @else
                                        <span class="badge bg-label-warning">Inativo</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Estoque -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-box me-2"></i>
                                        Estoque
                                    </h6>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Quantidade</span>
                                        <span class="fw-bold fs-5 {{ ($acessorio->quantidade ?? 0) > 0 ? 'text-success' : 'text-danger' }}">
                                            {{ $acessorio->quantidade ?? 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Datas -->
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title mb-3">
                                        <i class="ti ti-calendar me-2"></i>
                                        Datas
                                    </h6>
                                    
                                    <div class="mb-2">
                                        <span class="text-muted small">Cadastro:</span>
                                        <span class="d-block">{{ optional($acessorio->created_at)->format('d/m/Y H:i') ?? '-' }}</span>
                                    </div>
                                    <div>
                                        <span class="text-muted small">Última Atualização:</span>
                                        <span class="d-block">{{ optional($acessorio->updated_at)->format('d/m/Y H:i') ?? '-' }}</span>
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
