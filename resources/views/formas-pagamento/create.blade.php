@extends('layouts.layoutMaster')

@section('title', 'Nova Forma de Pagamento')

@section('content')
<div class="container-xxl flex-grow-1 pt-1">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-credit-card me-2"></i>
                        Nova Forma de Pagamento
                    </h5>
                    <a href="{{ route('formas-pagamento.index') }}" class="btn btn-secondary btn-sm">
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

                    <form action="{{ route('formas-pagamento.store') }}" method="POST">
                        @csrf
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome" name="nome" value="{{ old('nome') }}" required maxlength="100" placeholder="Ex: Dinheiro, Cartão de Crédito, PIX...">
                            </div>
                            <div class="col-12">
                                <label for="descricao" class="form-label">Descrição (opcional)</label>
                                <textarea class="form-control" id="descricao" name="descricao" rows="3" placeholder="Descrição ou observações sobre esta forma de pagamento">{{ old('descricao') }}</textarea>
                            </div>

                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('formas-pagamento.index') }}" class="btn btn-secondary">
                                        <i class="ti ti-x me-1"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-check me-1"></i> Salvar
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
