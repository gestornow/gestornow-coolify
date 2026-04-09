@extends('layouts.layoutMaster')

@section('title', 'Editar Tabela de Preços')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ti ti-table me-2"></i>
                        Editar Tabela de Preços
                    </h5>
                    <a href="{{ route('tabela-precos.index') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ti ti-arrow-left me-1"></i> Voltar
                    </a>
                </div>
                <div class="card-body">
                    <form action="{{ route('tabela-precos.update', $tabela->id_tabela) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row">
                            <!-- Informações Básicas -->
                            <div class="col-12 mb-3">
                                <h6 class="fw-semibold">Informações Básicas</h6>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Produto <span class="text-danger">*</span></label>
                                <select name="id_produto" class="form-select" required>
                                    <option value="">Selecione...</option>
                                    @foreach($produtos as $produto)
                                        <option value="{{ $produto->id_produto }}" {{ $tabela->id_produto == $produto->id_produto ? 'selected' : '' }}>{{ $produto->nome }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Nome da Tabela <span class="text-danger">*</span></label>
                                <input type="text" name="nome" class="form-control" value="{{ $tabela->nome }}" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="ativo" {{ $tabela->status == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                    <option value="inativo" {{ $tabela->status == 'inativo' ? 'selected' : '' }}>Inativo</option>
                                </select>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Descrição</label>
                                <textarea name="descricao" class="form-control" rows="2">{{ $tabela->descricao }}</textarea>
                            </div>

                            <!-- Preços por Período Fixo -->
                            <div class="col-12 mt-4 mb-3">
                                <h6 class="fw-semibold">Preços por Período Fixo</h6>
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Por Hora</label>
                                <input type="text" name="preco_hora" class="form-control money" value="{{ number_format($tabela->preco_hora ?? 0, 2, ',', '.') }}">
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Semanal</label>
                                <input type="text" name="preco_semanal" class="form-control money" value="{{ number_format($tabela->preco_semanal ?? 0, 2, ',', '.') }}">
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Quinzenal</label>
                                <input type="text" name="preco_quinzenal" class="form-control money" value="{{ number_format($tabela->preco_quinzenal ?? 0, 2, ',', '.') }}">
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Mensal</label>
                                <input type="text" name="preco_mensal" class="form-control money" value="{{ number_format($tabela->preco_mensal ?? 0, 2, ',', '.') }}">
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Trimestral</label>
                                <input type="text" name="preco_trimestral" class="form-control money" value="{{ number_format($tabela->preco_trimestral ?? 0, 2, ',', '.') }}">
                            </div>

                            <div class="col-md-2 mb-3">
                                <label class="form-label">Anual</label>
                                <input type="text" name="preco_anual" class="form-control money" value="{{ number_format($tabela->preco_anual ?? 0, 2, ',', '.') }}">
                            </div>

                            <!-- Preços por Dia -->
                            <div class="col-12 mt-4 mb-3">
                                <h6 class="fw-semibold">Preços por Dia (1-30 dias)</h6>
                            </div>

                            @for($i = 1; $i <= 30; $i++)
                            <div class="col-6 col-md-2 mb-2">
                                <label class="form-label small">{{ $i }} dia{{ $i > 1 ? 's' : '' }}</label>
                                <input type="text" name="d{{ $i }}" class="form-control form-control-sm money" value="{{ number_format($tabela->{'d'.$i} ?? 0, 2, ',', '.') }}">
                            </div>
                            @endfor

                            <!-- Preços por Período Longo -->
                            <div class="col-12 mt-4 mb-3">
                                <h6 class="fw-semibold">Preços por Período Longo</h6>
                            </div>

                            @foreach([45, 60, 90, 120, 150, 180, 210, 240, 270, 300, 330, 360] as $dias)
                            <div class="col-6 col-md-2 mb-2">
                                <label class="form-label small">{{ $dias }} dias</label>
                                <input type="text" name="d{{ $dias }}" class="form-control form-control-sm money" value="{{ number_format($tabela->{'d'.$dias} ?? 0, 2, ',', '.') }}">
                            </div>
                            @endforeach
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('tabela-precos.index') }}" class="btn btn-outline-secondary">
                                <i class="ti ti-x me-1"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="ti ti-check me-1"></i> Atualizar Tabela
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
