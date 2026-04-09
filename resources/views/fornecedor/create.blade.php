@extends('layouts.layoutMaster')

@section('title', 'Cadastrar Novo Fornecedor')

@section('content')
<div class="container-xxl flex-grow-1">
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('fornecedores.index') }}">
                <i class="ti ti-list me-1"></i> Listar Fornecedores
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('fornecedores.criar') }}">
                <i class="ti ti-plus me-1"></i> Cadastrar Fornecedor
            </a>
        </li>
    </ul>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Cadastrar Novo Fornecedor</h4>
            <p class="text-muted mb-0">Preencha os dados do fornecedor</p>
        </div>
        <a href="{{ route('fornecedores.index') }}" class="btn btn-outline-secondary">
            <i class="ti ti-arrow-left me-1"></i>
            Voltar
        </a>
    </div>

    <form id="fornecedorForm" action="{{ route('fornecedores.salvar') }}" method="POST">
        @csrf

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Dados Basicos</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <input type="hidden" name="id_empresa" value="{{ Auth::user()->id_empresa ?? session('id_empresa') }}">

                    <div class="col-md-6">
                        <label class="form-label" for="nome">Nome / Razao Social <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" value="{{ old('nome') }}" required>
                        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="razao_social">Nome Fantasia</label>
                        <input type="text" class="form-control @error('razao_social') is-invalid @enderror" id="razao_social" name="razao_social" value="{{ old('razao_social') }}">
                        @error('razao_social')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="id_tipo_pessoa">Tipo de Pessoa <span class="text-danger">*</span></label>
                        <select class="form-select @error('id_tipo_pessoa') is-invalid @enderror" id="id_tipo_pessoa" name="id_tipo_pessoa" required>
                            <option value="1" {{ old('id_tipo_pessoa', '2') == '1' ? 'selected' : '' }}>Pessoa Fisica</option>
                            <option value="2" {{ old('id_tipo_pessoa', '2') == '2' ? 'selected' : '' }}>Pessoa Juridica</option>
                        </select>
                        @error('id_tipo_pessoa')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="cpf_cnpj" id="label-cpf-cnpj">CPF/CNPJ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="text" class="form-control @error('cpf_cnpj') is-invalid @enderror" id="cpf_cnpj" name="cpf_cnpj" value="{{ old('cpf_cnpj') }}" required>
                            <button class="btn btn-outline-primary" type="button" id="btnConsultarCNPJ">
                                <i class="ti ti-search me-1"></i>Consultar
                            </button>
                        </div>
                        @error('cpf_cnpj')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="rg_ie" id="label-rg-ie">RG/IE</label>
                        <input type="text" class="form-control @error('rg_ie') is-invalid @enderror" id="rg_ie" name="rg_ie" value="{{ old('rg_ie') }}">
                        @error('rg_ie')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="telefone">Telefone</label>
                        <input type="text" class="form-control @error('telefone') is-invalid @enderror" id="telefone" name="telefone" value="{{ old('telefone') }}">
                        @error('telefone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="data_nascimento" id="label-data-nascimento">Data de Nascimento</label>
                        <input type="date" class="form-control @error('data_nascimento') is-invalid @enderror" id="data_nascimento" name="data_nascimento" value="{{ old('data_nascimento') }}">
                        @error('data_nascimento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                            <option value="ativo" {{ old('status', 'ativo') == 'ativo' ? 'selected' : '' }}>Ativo</option>
                            <option value="inativo" {{ old('status') == 'inativo' ? 'selected' : '' }}>Inativo</option>
                            <option value="bloqueado" {{ old('status') == 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                        </select>
                        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Endereco</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="cep">CEP</label>
                        <div class="input-group">
                            <input type="text" class="form-control @error('cep') is-invalid @enderror" id="cep" name="cep" value="{{ old('cep') }}">
                            <button class="btn btn-outline-secondary" type="button" id="btnBuscarCEP">
                                <i class="ti ti-map-search me-1"></i>Buscar
                            </button>
                        </div>
                        @error('cep')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-7">
                        <label class="form-label" for="endereco">Endereco</label>
                        <input type="text" class="form-control @error('endereco') is-invalid @enderror" id="endereco" name="endereco" value="{{ old('endereco') }}">
                        @error('endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-2">
                        <label class="form-label" for="numero">Numero</label>
                        <input type="text" class="form-control @error('numero') is-invalid @enderror" id="numero" name="numero" value="{{ old('numero') }}">
                        @error('numero')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="complemento">Complemento</label>
                        <input type="text" class="form-control @error('complemento') is-invalid @enderror" id="complemento" name="complemento" value="{{ old('complemento') }}">
                        @error('complemento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="bairro">Bairro</label>
                        <input type="text" class="form-control @error('bairro') is-invalid @enderror" id="bairro" name="bairro" value="{{ old('bairro') }}">
                        @error('bairro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label" for="municipio">Municipio</label>
                        <input type="text" class="form-control @error('municipio') is-invalid @enderror" id="municipio" name="municipio" value="{{ old('municipio') }}">
                        @error('municipio')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-1">
                        <label class="form-label" for="uf">UF</label>
                        <input type="text" class="form-control @error('uf') is-invalid @enderror" id="uf" name="uf" maxlength="2" value="{{ old('uf') }}">
                        @error('uf')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Observacoes</h5>
            </div>
            <div class="card-body">
                <textarea class="form-control @error('observacoes') is-invalid @enderror" name="observacoes" id="observacoes" rows="4" placeholder="Informacoes adicionais sobre o fornecedor">{{ old('observacoes') }}</textarea>
                @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('fornecedores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-check me-1"></i>Salvar Fornecedor
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/fornecedores-form.js') }}?v=20260318002"></script>

@if(session('success'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '{{ session('success') }}',
        confirmButtonText: 'OK',
        timer: 2500,
        timerProgressBar: true
    });
});
</script>
@endif

@if(session('error'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'error',
        title: 'Erro!',
        text: '{{ session('error') }}',
        confirmButtonText: 'OK'
    });
});
</script>
@endif

@if($errors->any())
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'error',
        title: 'Nao foi possivel salvar o fornecedor',
        html: '{!! collect($errors->all())->map(fn($error) => e($error))->implode("<br>") !!}',
        confirmButtonText: 'OK'
    });
});
</script>
@endif
@endsection
