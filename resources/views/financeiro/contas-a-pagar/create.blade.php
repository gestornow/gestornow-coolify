@extends('layouts.layoutMaster')

@section('title', 'Nova Conta a Pagar')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<style>
.parcela-custom-item {
    animation: slideIn 0.3s ease-out;
}
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.parcela-custom-item:hover {
    background-color: rgba(0,0,0,0.02);
    border-radius: 4px;
}
</style>
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Nova Conta a Pagar</h5>
                    <a href="{{ route('financeiro.index') }}" class="btn btn-outline-secondary">
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

                    <form action="{{ route('financeiro.store') }}" method="POST" id="formContaAPagar">
                        @csrf
                        
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
                                    value="{{ old('descricao') }}" 
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
                                    value="{{ old('documento') }}">
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
                                    value="{{ old('boleto') }}">
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

                            <div class="col-md-3">
                                <label class="form-label" for="id_fornecedores">Fornecedor</label>
                                <select class="form-select select2 @error('id_fornecedores') is-invalid @enderror" 
                                    id="id_fornecedores" 
                                    name="id_fornecedores">
                                    <option value="">Selecione...</option>
                                    @foreach($fornecedores as $fornecedor)
                                        <option value="{{ $fornecedor->id_fornecedores }}" 
                                            {{ old('id_fornecedores') == $fornecedor->id_fornecedores ? 'selected' : '' }}>
                                            {{ $fornecedor->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_fornecedores')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="id_usuario">Funcionário</label>
                                <select class="form-select select2 @error('id_usuario') is-invalid @enderror" 
                                    id="id_usuario" 
                                    name="id_usuario">
                                    <option value="">Selecione...</option>
                                    @foreach($funcionarios as $funcionario)
                                        <option value="{{ $funcionario->id_usuario }}" 
                                            {{ old('id_usuario') == $funcionario->id_usuario ? 'selected' : '' }}>
                                            {{ $funcionario->nome }}{{ !empty($funcionario->login) ? ' (' . $funcionario->login . ')' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_usuario')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="id_categoria_contas">Categoria</label>
                                <select class="form-select select2 @error('id_categoria_contas') is-invalid @enderror" 
                                    id="id_categoria_contas" 
                                    name="id_categoria_contas">
                                    <option value="">Selecione...</option>
                                    @foreach($categorias as $categoria)
                                        <option value="{{ $categoria->id_categoria_contas }}" 
                                            {{ old('id_categoria_contas') == $categoria->id_categoria_contas ? 'selected' : '' }}>
                                            {{ $categoria->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_categoria_contas')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="id_bancos">Banco</label>
                                <select class="form-select select2 @error('id_bancos') is-invalid @enderror" 
                                    id="id_bancos" 
                                    name="id_bancos">
                                    <option value="">Selecione...</option>
                                    @foreach($bancos as $banco)
                                        <option value="{{ $banco->id_bancos }}" 
                                            {{ old('id_bancos') == $banco->id_bancos ? 'selected' : '' }}>
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
                                        value="{{ old('valor_total', '0,00') }}" 
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
                                        value="{{ old('juros', '0,00') }}">
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
                                        value="{{ old('multa', '0,00') }}">
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
                                        value="{{ old('desconto', '0,00') }}">
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
                                    value="{{ old('data_emissao') }}">
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
                                    value="{{ old('data_vencimento') }}" 
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
                                    <option value="pendente" {{ old('status') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                                    <option value="pago" {{ old('status') == 'pago' ? 'selected' : '' }}>Pago</option>
                                    <option value="vencido" {{ old('status') == 'vencido' ? 'selected' : '' }}>Vencido</option>
                                    <option value="parcelado" {{ old('status') == 'parcelado' ? 'selected' : '' }}>Parcelado</option>
                                    <option value="cancelado" {{ old('status') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                </select>
                                @error('status')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3" id="div_forma_pagamento">
                                <label class="form-label" for="id_forma_pagamento">Forma de Pagamento</label>
                                <select class="form-select select2 @error('id_forma_pagamento') is-invalid @enderror" 
                                    id="id_forma_pagamento" 
                                    name="id_forma_pagamento">
                                    <option value="">Selecione...</option>
                                    @foreach($formasPagamento as $forma)
                                        <option value="{{ $forma->id_forma_pagamento }}" 
                                            {{ old('id_forma_pagamento') == $forma->id_forma_pagamento ? 'selected' : '' }}>
                                            {{ $forma->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('id_forma_pagamento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3" id="div_data_pagamento">
                                <label class="form-label" for="data_pagamento">Data Pagamento</label>
                                <input type="date" 
                                    class="form-control @error('data_pagamento') is-invalid @enderror" 
                                    id="data_pagamento" 
                                    name="data_pagamento" 
                                    value="{{ old('data_pagamento') }}">
                                @error('data_pagamento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-3" id="div_valor_pago">
                                <label class="form-label" for="valor_pago">Valor Pago</label>
                                <div class="input-group">
                                    <span class="input-group-text">R$</span>
                                    <input type="text" 
                                        class="form-control mask-money @error('valor_pago') is-invalid @enderror" 
                                        id="valor_pago" 
                                        name="valor_pago" 
                                        value="{{ old('valor_pago', '0,00') }}">
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
                                    rows="3">{{ old('observacoes') }}</textarea>
                                @error('observacoes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Parcelamento e Recorrência -->
                            <div class="col-12 mt-4">
                                <h6 class="mb-3 text-primary">
                                    <i class="ti ti-repeat me-2"></i>
                                    Parcelamento e Recorrência
                                </h6>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label" for="tipo_lancamento">Tipo de Lançamento</label>
                                <select class="form-select" id="tipo_lancamento" name="tipo_lancamento">
                                    <option value="unico" selected>Lançamento Único</option>
                                    <option value="parcelado">Parcelado (datas automáticas)</option>
                                    <option value="parcelado_customizado">Parcelado (datas customizadas)</option>
                                    <option value="recorrente">Recorrente</option>
                                </select>
                                <small class="text-muted">
                                    Escolha se a conta será única, parcelada ou recorrente
                                </small>
                            </div>

                            <!-- Opções de Parcelamento Automático -->
                            <div class="col-md-4" id="div_total_parcelas" style="display: none;">
                                <label class="form-label" for="total_parcelas">Número de Parcelas <span class="text-danger">*</span></label>
                                <input type="number" 
                                    class="form-control" 
                                    id="total_parcelas" 
                                    name="total_parcelas" 
                                    min="2" 
                                    max="120"
                                    value="2"
                                    placeholder="Ex: 12">
                                <small class="text-muted">
                                    Quantas parcelas serão criadas
                                </small>
                            </div>

                            <!-- Intervalo entre Parcelas -->
                            <div class="col-md-4" id="div_intervalo_parcelas" style="display: none;">
                                <label class="form-label" for="intervalo_parcelas">Intervalo entre Parcelas <span class="text-danger">*</span></label>
                                <select class="form-select" id="intervalo_parcelas" name="intervalo_parcelas">
                                    <option value="7">Semanal (7 dias)</option>
                                    <option value="15">Quinzenal (15 dias)</option>
                                    <option value="30" selected>Mensal (30 dias)</option>
                                    <option value="60">Bimestral (60 dias)</option>
                                    <option value="90">Trimestral (90 dias)</option>
                                    <option value="custom">Personalizado</option>
                                </select>
                                <small class="text-muted">
                                    Período entre cada vencimento
                                </small>
                            </div>

                            <!-- Intervalo Personalizado -->
                            <div class="col-md-4" id="div_intervalo_custom" style="display: none;">
                                <label class="form-label" for="intervalo_custom">Dias entre Parcelas <span class="text-danger">*</span></label>
                                <input type="number" 
                                    class="form-control" 
                                    id="intervalo_custom" 
                                    name="intervalo_custom" 
                                    min="1" 
                                    max="365"
                                    value="30"
                                    placeholder="Ex: 45">
                                <small class="text-muted">
                                    Defina o intervalo em dias
                                </small>
                            </div>

                            <!-- Número de Parcelas Customizadas -->
                            <div class="col-md-4" id="div_num_parcelas_customizadas" style="display: none;">
                                <label class="form-label" for="num_parcelas_customizadas">Número de Parcelas <span class="text-danger">*</span></label>
                                <input type="number" 
                                    class="form-control" 
                                    id="num_parcelas_customizadas" 
                                    min="2" 
                                    max="120"
                                    value="2"
                                    placeholder="Ex: 12">
                                <small class="text-muted">
                                    Defina quantas parcelas deseja criar
                                </small>
                            </div>

                            <!-- Intervalo entre Parcelas Customizadas -->
                            <div class="col-md-4" id="div_intervalo_parcelas_custom" style="display: none;">
                                <label class="form-label" for="intervalo_parcelas_custom">Intervalo entre Parcelas</label>
                                <select class="form-select" id="intervalo_parcelas_custom" name="intervalo_parcelas_custom">
                                    <option value="7">Semanal (7 dias)</option>
                                    <option value="15">Quinzenal (15 dias)</option>
                                    <option value="30" selected>Mensal (30 dias)</option>
                                    <option value="60">Bimestral (60 dias)</option>
                                    <option value="90">Trimestral (90 dias)</option>
                                </select>
                                <small class="text-muted">
                                    Base para gerar datas automaticamente
                                </small>
                            </div>

                            <div class="col-md-4" id="div_btn_gerar_parcelas" style="display: none;">
                                <label class="form-label">&nbsp;</label>
                                <button type="button" class="btn btn-primary w-100" onclick="gerarParcelasCustomizadas()">
                                    <i class="ti ti-refresh me-1"></i>
                                    Gerar Parcelas
                                </button>
                            </div>

                            <!-- Parcelas Customizadas -->
                            <div class="col-12" id="div_parcelas_customizadas" style="display: none;">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="mb-0">
                                                <i class="ti ti-calendar-event me-1"></i>
                                                Datas de Vencimento das Parcelas
                                            </h6>
                                            <small class="text-muted">
                                                <strong>Total de Parcelas:</strong> <span id="total_parcelas_count">0</span>
                                            </small>
                                        </div>
                                        
                                        <div id="lista_parcelas_customizadas">
                                            <!-- Parcelas serão adicionadas aqui dinamicamente -->
                                        </div>

                                        <div class="mt-3">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="alert alert-primary mb-0">
                                                        <small>
                                                            <strong>Total Valor Parcelas:</strong> R$ <span id="total_parcelas_custom">0,00</span><br>
                                                            <strong>Valor Original:</strong> R$ <span id="valor_original">0,00</span>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="alert alert-info mb-0">
                                                        <small>
                                                            <i class="ti ti-info-circle me-1"></i>
                                                            <strong>Valores editáveis!</strong> Altere qualquer valor e os demais se ajustarão automaticamente
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Opções de Recorrência -->
                            <div class="col-md-4" id="div_tipo_recorrencia" style="display: none;">
                                <label class="form-label" for="tipo_recorrencia">Periodicidade <span class="text-danger">*</span></label>
                                <select class="form-select" id="tipo_recorrencia" name="tipo_recorrencia">
                                    <option value="diario">Diário</option>
                                    <option value="semanal">Semanal (a cada 7 dias)</option>
                                    <option value="quinzenal">Quinzenal (a cada 15 dias)</option>
                                    <option value="mensal" selected>Mensal (a cada 30 dias)</option>
                                    <option value="bimestral">Bimestral (a cada 2 meses)</option>
                                    <option value="trimestral">Trimestral (a cada 3 meses)</option>
                                    <option value="semestral">Semestral (a cada 6 meses)</option>
                                    <option value="anual">Anual</option>
                                </select>
                                <small class="text-muted">
                                    Frequência de repetição da conta
                                </small>
                            </div>

                            <div class="col-md-4" id="div_quantidade_recorrencias" style="display: none;">
                                <label class="form-label" for="quantidade_recorrencias">Quantidade de Repetições</label>
                                <input type="number" 
                                    class="form-control" 
                                    id="quantidade_recorrencias" 
                                    name="quantidade_recorrencias" 
                                    min="2" 
                                    max="60"
                                    value="12"
                                    placeholder="Ex: 12">
                                <small class="text-muted">
                                    Deixe vazio para repetir indefinidamente (máx 60)
                                </small>
                            </div>

                            <!-- Informativo -->
                            <div class="col-12" id="info_parcelamento" style="display: none;">
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="ti ti-info-circle me-2"></i>
                                    <div>
                                        <strong>Parcelamento:</strong> Serão criadas <span id="info_num_parcelas">-</span> contas com vencimentos mensais consecutivos.
                                        O valor de cada parcela será de aproximadamente R$ <span id="info_valor_parcela">0,00</span>.
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" id="info_recorrencia" style="display: none;">
                                <div class="alert alert-info d-flex align-items-center">
                                    <i class="ti ti-info-circle me-2"></i>
                                    <div>
                                        <strong>Recorrência:</strong> Serão criadas <span id="info_num_recorrencias">-</span> contas 
                                        com periodicidade <span id="info_periodicidade">-</span>.
                                        Cada conta terá o valor integral de R$ <span id="info_valor_recorrente">0,00</span>.
                                    </div>
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-check me-1"></i>
                                    Salvar
                                </button>
                                <a href="{{ route('financeiro.index') }}" class="btn btn-outline-secondary">
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
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/js/utils.js')}}"></script>
<script src="{{asset('assets/js/money-helpers.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/financeiro/contas-form-common.js')}}"></script>
<script src="{{asset('assets/js/financeiro/contas-a-pagar-create.js')}}"></script>
@endsection

