@extends('layouts.layoutMaster')

@section('title', 'Editar Produto para Venda')

@section('page-style')
<style>
    .produto-card {
        background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
        border: 1px solid #667eea30;
    }

    @media (max-width: 767.98px) {
        .produto-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .produto-actions .btn {
            width: 100%;
        }
    }

    html.dark-style .produto-card {
        background: linear-gradient(135deg, #2b3046 0%, #25293c 100%);
        border-color: #444b6e;
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
                        <i class="ti ti-pencil me-2"></i>
                        Editar Produto: {{ $produto->nome }}
                    </h5>
                    <a href="{{ route('produtos-venda.index') }}" class="btn btn-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form id="produtoEditForm" action="{{ route('produtos-venda.update', $produto->id_produto_venda) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row g-3">
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
                                                <label for="nome" class="form-label">Nome do Produto <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="nome" name="nome" value="{{ old('nome', $produto->nome) }}" required>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="codigo" class="form-label">Código / Código de Barras</label>
                                                <input type="text" class="form-control" id="codigo" name="codigo" value="{{ old('codigo', $produto->codigo) }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="numero_serie" class="form-label">Número de Série</label>
                                                <input type="text" class="form-control" id="numero_serie" name="numero_serie" value="{{ old('numero_serie', $produto->numero_serie) }}">
                                            </div>
                                            <div class="col-md-12">
                                                <label for="descricao" class="form-label">Descrição</label>
                                                <textarea class="form-control" id="descricao" name="descricao" rows="2">{{ old('descricao', $produto->descricao) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Preços -->
                            <div class="col-12">
                                <div class="card produto-card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-currency-dollar me-2"></i>
                                            Preços
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="preco_custo" class="form-label">Preço de Custo</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" class="form-control money-mask" id="preco_custo" name="preco_custo" value="{{ old('preco_custo', number_format($produto->preco_custo ?? 0, 2, ',', '.')) }}">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="preco_venda" class="form-label">Preço de Venda</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" class="form-control money-mask" id="preco_venda" name="preco_venda" value="{{ old('preco_venda', number_format($produto->preco_venda ?? 0, 2, ',', '.')) }}">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="preco_reposicao" class="form-label">Preço de Reposição</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" class="form-control money-mask" id="preco_reposicao" name="preco_reposicao" value="{{ old('preco_reposicao', number_format($produto->preco_reposicao ?? 0, 2, ',', '.')) }}">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="preco_locacao" class="form-label">Preço de Locação</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="text" class="form-control money-mask" id="preco_locacao" name="preco_locacao" value="{{ old('preco_locacao', number_format($produto->preco_locacao ?? 0, 2, ',', '.')) }}">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Estoque -->
                            <div class="col-12">
                                <div class="card produto-card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-packages me-2"></i>
                                            Estoque
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label for="quantidade" class="form-label">Quantidade em Estoque</label>
                                                <input type="number" class="form-control" id="quantidade" name="quantidade" value="{{ old('quantidade', $produto->quantidade ?? 0) }}" min="0">
                                                <small class="text-muted">Altere para ajustar o estoque manualmente</small>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="estoque_total" class="form-label">Estoque Total (máximo)</label>
                                                <input type="number" class="form-control" id="estoque_total" name="estoque_total" value="{{ old('estoque_total', $produto->estoque_total ?? 0) }}" min="0">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="status" class="form-label">Status</label>
                                                <select class="form-select" id="status" name="status">
                                                    <option value="ativo" {{ old('status', $produto->status) == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                                    <option value="inativo" {{ old('status', $produto->status) == 'inativo' ? 'selected' : '' }}>Inativo</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Dimensões -->
                            <div class="col-12">
                                <div class="card produto-card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-ruler me-2"></i>
                                            Dimensões e Peso (opcional)
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-3">
                                                <label for="altura" class="form-label">Altura (cm)</label>
                                                <input type="text" class="form-control decimal-mask" id="altura" name="altura" value="{{ old('altura', $produto->altura ? number_format($produto->altura, 2, ',', '.') : '') }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="largura" class="form-label">Largura (cm)</label>
                                                <input type="text" class="form-control decimal-mask" id="largura" name="largura" value="{{ old('largura', $produto->largura ? number_format($produto->largura, 2, ',', '.') : '') }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="profundidade" class="form-label">Profundidade (cm)</label>
                                                <input type="text" class="form-control decimal-mask" id="profundidade" name="profundidade" value="{{ old('profundidade', $produto->profundidade ? number_format($produto->profundidade, 2, ',', '.') : '') }}">
                                            </div>
                                            <div class="col-md-3">
                                                <label for="peso" class="form-label">Peso (kg)</label>
                                                <input type="text" class="form-control decimal-mask" id="peso" name="peso" value="{{ old('peso', $produto->peso ? number_format($produto->peso, 2, ',', '.') : '') }}">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detalhes Adicionais -->
                            <div class="col-12">
                                <div class="card produto-card">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-notes me-2"></i>
                                            Detalhes Adicionais
                                        </h6>
                                        <div class="row g-3">
                                            <div class="col-md-12">
                                                <label for="detalhes" class="form-label">Detalhes</label>
                                                <textarea class="form-control" id="detalhes" name="detalhes" rows="3">{{ old('detalhes', $produto->detalhes) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2 produto-actions">
                                    <a href="{{ route('produtos-venda.index') }}" class="btn btn-secondary">
                                        <i class="ti ti-x me-1"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> Salvar Alterações
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page-script')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
    // Máscara de dinheiro
    $('.money-mask').mask('#.##0,00', {reverse: true});
    
    // Máscara decimal
    $('.decimal-mask').mask('#.##0,00', {reverse: true});
});
</script>
@endsection
