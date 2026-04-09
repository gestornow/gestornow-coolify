@extends('layouts.layoutMaster')

@section('title', 'Novo Acessório')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-plug me-2"></i>
                        Cadastrar Acessório
                    </h5>
                    <a href="{{ route('acessorios.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('acessorios.store') }}" method="POST" id="formAcessorio">
                        @csrf
                        
                        <div class="row">
                            <!-- Informações Básicas -->
                            <div class="col-12">
                                <h6 class="fw-semibold mb-3">Informações Básicas</h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="nome">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" required>
                                @error('nome')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="codigo">Código</label>
                                <input type="text" class="form-control @error('codigo') is-invalid @enderror" id="codigo" name="codigo" value="{{ old('codigo') }}">
                                @error('codigo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="numero_serie">Número de Série</label>
                                <input type="text" class="form-control @error('numero_serie') is-invalid @enderror" id="numero_serie" name="numero_serie" value="{{ old('numero_serie') }}">
                                @error('numero_serie')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label" for="descricao">Descrição</label>
                                <textarea class="form-control @error('descricao') is-invalid @enderror" id="descricao" name="descricao" rows="3">{{ old('descricao') }}</textarea>
                                @error('descricao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Preços e Quantidade -->
                            <div class="col-12 mt-3">
                                <h6 class="fw-semibold mb-3">Preços e Estoque</h6>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="quantidade">Quantidade</label>
                                <input type="number" class="form-control @error('quantidade') is-invalid @enderror" id="quantidade" name="quantidade" value="{{ old('quantidade', 0) }}" min="0">
                                @error('quantidade')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="preco_custo">Preço de Custo</label>
                                <input type="text" class="form-control money @error('preco_custo') is-invalid @enderror" id="preco_custo" name="preco_custo" value="{{ old('preco_custo') }}">
                                @error('preco_custo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="preco_venda">Preço de Venda</label>
                                <input type="text" class="form-control money @error('preco_venda') is-invalid @enderror" id="preco_venda" name="preco_venda" value="{{ old('preco_venda') }}">
                                @error('preco_venda')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="preco_locacao">Preço de Locação</label>
                                <input type="text" class="form-control money @error('preco_locacao') is-invalid @enderror" id="preco_locacao" name="preco_locacao" value="{{ old('preco_locacao') }}">
                                @error('preco_locacao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Status -->
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                    <option value="ativo" {{ old('status', 'ativo') == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                    <option value="inativo" {{ old('status') == 'inativo' ? 'selected' : '' }}>Inativo</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('acessorios.index') }}" class="btn btn-outline-secondary">
                                <i class="ti ti-x me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i> Salvar Acessório
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
<script>
$(document).ready(function() {
    $('.money').mask('#.##0,00', {reverse: true});
});
</script>
@endsection
