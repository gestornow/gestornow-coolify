@extends('layouts.layoutMaster')

@section('title', 'Editar Conta a Receber')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Conta a Receber #{{ $conta->id_contas }}</h5>
                    <a href="{{ route('financeiro.contas-a-receber.index') }}" class="btn btn-outline-secondary">
                        <i class="ti ti-arrow-left me-1"></i>
                        Voltar
                    </a>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('financeiro.contas-a-receber.update', $conta->id_contas) }}" method="POST">
                        @csrf
                        @method('PUT')
                        
                        <div class="row g-3">
                            <!-- Informações Básicas -->
                            <div class="col-12">
                                <h6 class="mb-3 text-primary">
                                    <i class="ti ti-file-info me-2"></i>
                                    Informações Básicas
                                </h6>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="descricao">Descrição <span class="text-danger">*</span></label>
                                <input type="text" 
                                    class="form-control @error('descricao') is-invalid @enderror" 
                                    id="descricao" 
                                    name="descricao" 
                                    value="{{ old('descricao', $conta->descricao) }}" 
                                    required>
                                @error('descricao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="documento">Documento</label>
                                <input type="text" 
                                    class="form-control @error('documento') is-invalid @enderror" 
                                    id="documento" 
                                    name="documento" 
                                    value="{{ old('documento', $conta->documento) }}">
                                @error('documento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="boleto">Boleto</label>
                                <input type="text" 
                                    class="form-control @error('boleto') is-invalid @enderror" 
                                    id="boleto" 
                                    name="boleto" 
                                    value="{{ old('boleto', $conta->boleto) }}">
                                @error('boleto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Relacionamentos -->
                            <div class="col-12 mt-4">
                                <h6 class="mb-3 text-primary">
                                    <i class="ti ti-users me-2"></i>
                                    Relacionamentos
                                </h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="id_clientes">Cliente</label>
                                <select class="form-select select2 @error('id_clientes') is-invalid @enderror" 
                                    id="id_clientes" 
                                    name="id_clientes">
                                    <option value="">Selecione...</option>
                                    @foreach($clientes as $cliente)
                                        <option value="{{ $cliente->id_clientes }}" 
                                            {{ old('id_clientes', $conta->id_clientes) == $cliente->id_clientes ? 'selected' : '' }}>
                                            {{ $cliente->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_clientes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="id_categoria_contas">Categoria</label>
                                <select class="form-select select2 @error('id_categoria_contas') is-invalid @enderror" 
                                    id="id_categoria_contas" 
                                    name="id_categoria_contas">
                                    <option value="">Selecione...</option>
                                    @foreach($categorias as $categoria)
                                        <option value="{{ $categoria->id_categoria_contas }}" 
                                            {{ old('id_categoria_contas', $conta->id_categoria_contas) == $categoria->id_categoria_contas ? 'selected' : '' }}>
                                            {{ $categoria->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_categoria_contas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="id_bancos">Banco</label>
                                <select class="form-select select2 @error('id_bancos') is-invalid @enderror" 
                                    id="id_bancos" 
                                    name="id_bancos">
                                    <option value="">Selecione...</option>
                                    @foreach($bancos as $banco)
                                        <option value="{{ $banco->id_bancos }}" 
                                            {{ old('id_bancos', $conta->id_bancos) == $banco->id_bancos ? 'selected' : '' }}>
                                            {{ $banco->nome_banco }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_bancos')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Valores -->
                            <div class="col-12 mt-4">
                                <h6 class="mb-3 text-primary">
                                    <i class="ti ti-cash me-2"></i>
                                    Valores
                                </h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="valor_total">Valor Total <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" 
                                        class="form-control mask-money @error('valor_total') is-invalid @enderror" 
                                        id="valor_total" 
                                        name="valor_total" 
                                        value="{{ old('valor_total', number_format($conta->valor_total, 2, ',', '.')) }}" 
                                        required>
                                </div>
                                @error('valor_total')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="juros">Juros</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" 
                                        class="form-control mask-money @error('juros') is-invalid @enderror" 
                                        id="juros" 
                                        name="juros" 
                                        value="{{ old('juros', number_format($conta->juros, 2, ',', '.')) }}">
                                </div>
                                @error('juros')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="multa">Multa</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" 
                                        class="form-control mask-money @error('multa') is-invalid @enderror" 
                                        id="multa" 
                                        name="multa" 
                                        value="{{ old('multa', number_format($conta->multa, 2, ',', '.')) }}">
                                </div>
                                @error('multa')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="desconto">Desconto</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" 
                                        class="form-control mask-money @error('desconto') is-invalid @enderror" 
                                        id="desconto" 
                                        name="desconto" 
                                        value="{{ old('desconto', number_format($conta->desconto, 2, ',', '.')) }}">
                                </div>
                                @error('desconto')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Datas e Status -->
                            <div class="col-12 mt-4">
                                <h6 class="mb-3 text-primary">
                                    <i class="ti ti-calendar me-2"></i>
                                    Datas e Status
                                </h6>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="data_emissao">Data Emissão</label>
                                <input type="date" 
                                    class="form-control @error('data_emissao') is-invalid @enderror" 
                                    id="data_emissao" 
                                    name="data_emissao" 
                                    value="{{ old('data_emissao', $conta->data_emissao?->format('Y-m-d')) }}">
                                @error('data_emissao')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="data_vencimento">Data Vencimento <span class="text-danger">*</span></label>
                                <input type="date" 
                                    class="form-control @error('data_vencimento') is-invalid @enderror" 
                                    id="data_vencimento" 
                                    name="data_vencimento" 
                                    value="{{ old('data_vencimento', $conta->data_vencimento?->format('Y-m-d')) }}" 
                                    required>
                                @error('data_vencimento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                                <select class="form-select @error('status') is-invalid @enderror" 
                                    id="status" 
                                    name="status" 
                                    required>
                                    <option value="pendente" {{ old('status', $conta->status) == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                    <option value="pago" {{ old('status', $conta->status) == 'pago' ? 'selected' : '' }}>Recebido</option>
                                    <option value="vencido" {{ old('status', $conta->status) == 'vencido' ? 'selected' : '' }}>Vencido</option>
                                    <option value="parcelado" {{ old('status', $conta->status) == 'parcelado' ? 'selected' : '' }}>Parcelado</option>
                                    <option value="cancelado" {{ old('status', $conta->status) == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3" id="div_forma_pagamento">
                                <label class="form-label" for="id_forma_pagamento">Forma de Recebimento</label>
                                <select class="form-select select2 @error('id_forma_pagamento') is-invalid @enderror" 
                                    id="id_forma_pagamento" 
                                    name="id_forma_pagamento">
                                    <option value="">Selecione...</option>
                                    @foreach($formasPagamento as $forma)
                                        <option value="{{ $forma->id_forma_pagamento }}" 
                                            {{ old('id_forma_pagamento', $conta->id_forma_pagamento) == $forma->id_forma_pagamento ? 'selected' : '' }}>
                                            {{ $forma->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_forma_pagamento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3" id="div_data_pagamento">
                                <label class="form-label" for="data_pagamento">Data Recebimento</label>
                                <input type="date" 
                                    class="form-control @error('data_pagamento') is-invalid @enderror" 
                                    id="data_pagamento" 
                                    name="data_pagamento" 
                                    value="{{ old('data_pagamento', $conta->data_pagamento?->format('Y-m-d')) }}">
                                @error('data_pagamento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3" id="div_valor_pago">
                                <label class="form-label" for="valor_pago">Valor Recebido</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" 
                                        class="form-control mask-money @error('valor_pago') is-invalid @enderror" 
                                        id="valor_pago" 
                                        name="valor_pago" 
                                        value="{{ old('valor_pago', number_format($conta->valor_pago, 2, ',', '.')) }}">
                                </div>
                                @error('valor_pago')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Observações -->
                            <div class="col-12 mt-4">
                                <label class="form-label" for="observacoes">Observações</label>
                                <textarea class="form-control @error('observacoes') is-invalid @enderror" 
                                    id="observacoes" 
                                    name="observacoes" 
                                    rows="3">{{ old('observacoes', $conta->observacoes) }}</textarea>
                                @error('observacoes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Informações de Auditoria -->
                            <div class="col-12 mt-4">
                                <div class="alert alert-info">
                                    <small>
                                        <strong>Criado em:</strong> {{ $conta->created_at?->format('d/m/Y H:i') ?? 'N/A' }}
                                        <br>
                                        <strong>Última atualização:</strong> {{ $conta->updated_at?->format('d/m/Y H:i') ?? 'N/A' }}
                                    </small>
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i>
                                    Salvar Alterações
                                </button>
                                <a href="{{ route('financeiro.contas-a-receber.index') }}" class="btn btn-outline-secondary">
                                    <i class="ti ti-x me-1"></i>
                                    Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/money-helpers.js')}}"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    if ($('.select2').length) {
        $('.select2').select2({
            placeholder: 'Selecione...',
            allowClear: true
        });
    }

    // Aplicar máscaras monetárias
    applyMoneyMaskToAll('mask-money');

    // Toggle payment fields based on status
    function togglePaymentFields() {
        const status = $('#status').val();
        if (status === 'pago') {
            $('#div_forma_pagamento, #div_data_pagamento, #div_valor_pago').show();
        } else {
            $('#div_forma_pagamento, #div_data_pagamento, #div_valor_pago').hide();
        }
    }

    $('#status').on('change', togglePaymentFields);
    togglePaymentFields(); // Initial call

    // Converter valores monetários antes de submeter o formulário
    $('form').on('submit', function(e) {
        // Converter campos monetários de formato BR para decimal
        $('.mask-money').each(function() {
            const valor = $(this).val();
            if (valor) {
                // Converter usando a função helper
                const valorDecimal = parseMoneyToFloat(valor);
                $(this).val(valorDecimal);
            }
        });
    });
});
</script>
@endsection
