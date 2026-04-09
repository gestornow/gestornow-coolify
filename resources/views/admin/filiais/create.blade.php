@extends('layouts.layoutMaster')

@section('title', 'Nova Filial')

@section('content')
<div class="container-xxl flex-grow-1">
    <div class="row">
        <div class="col-12">
            <form action="{{ route('admin.filiais.store') }}" method="POST">
                @csrf
                
                <div class="row">
                    <!-- Informações da Empresa -->
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Nova Filial</h5>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('admin.filiais.index') }}" class="btn btn-secondary btn-sm">
                                        <i class="ti ti-x me-1"></i>
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="ti ti-device-floppy me-1"></i>
                                        Cadastrar
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                @if ($errors->any())
                                    <div class="alert alert-danger">
                                        <ul class="mb-0">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="nome_empresa">Nome da Empresa <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('nome_empresa') is-invalid @enderror" 
                                               id="nome_empresa" name="nome_empresa" 
                                               value="{{ old('nome_empresa') }}" required>
                                        @error('nome_empresa')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="razao_social">Razão Social <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('razao_social') is-invalid @enderror" 
                                               id="razao_social" name="razao_social" 
                                               value="{{ old('razao_social') }}" required>
                                        @error('razao_social')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="cnpj">CNPJ <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control @error('cnpj') is-invalid @enderror" 
                                               id="cnpj" name="cnpj" 
                                               value="{{ old('cnpj') }}" required>
                                        @error('cnpj')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="cpf">CPF</label>
                                        <input type="text" class="form-control @error('cpf') is-invalid @enderror" 
                                               id="cpf" name="cpf" 
                                               value="{{ old('cpf') }}">
                                        @error('cpf')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="codigo">Código</label>
                                        <input type="text" class="form-control @error('codigo') is-invalid @enderror" 
                                               id="codigo" name="codigo" 
                                               value="{{ old('codigo') }}">
                                        @error('codigo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="filial">Tipo <span class="text-danger">*</span></label>
                                        <select class="form-select @error('filial') is-invalid @enderror" id="filial" name="filial" required>
                                            <option value="">Selecione...</option>
                                            <option value="Unica" {{ old('filial') == 'Unica' ? 'selected' : '' }}>Única</option>
                                            <option value="Matriz" {{ old('filial') == 'Matriz' ? 'selected' : '' }}>Matriz</option>
                                            <option value="Filial" {{ old('filial') == 'Filial' ? 'selected' : '' }}>Filial</option>
                                        </select>
                                        @error('filial')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="status">Status <span class="text-danger">*</span></label>
                                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                                            <option value="">Selecione...</option>
                                            <option value="ativo" {{ old('status') == 'ativo' ? 'selected' : '' }}>Ativo</option>
                                            <option value="inativo" {{ old('status') == 'inativo' ? 'selected' : '' }}>Inativo</option>
                                            <option value="bloqueado" {{ old('status') == 'bloqueado' ? 'selected' : '' }}>Bloqueado</option>
                                            <option value="validacao" {{ old('status', 'validacao') == 'validacao' ? 'selected' : '' }}>Em Validação</option>
                                            <option value="teste" {{ old('status') == 'teste' ? 'selected' : '' }}>Teste</option>
                                            <option value="cancelado" {{ old('status') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                                            <option value="teste bloqueado" {{ old('status') == 'teste bloqueado' ? 'selected' : '' }}>Teste Bloqueado</option>
                                        </select>
                                        @error('status')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="dados_cadastrais">Dados Cadastrais <span class="text-danger">*</span></label>
                                        <select class="form-select @error('dados_cadastrais') is-invalid @enderror" id="dados_cadastrais" name="dados_cadastrais" required>
                                            <option value="">Selecione...</option>
                                            <option value="incompleto" {{ old('dados_cadastrais', 'incompleto') == 'incompleto' ? 'selected' : '' }}>Incompleto</option>
                                            <option value="completo" {{ old('dados_cadastrais') == 'completo' ? 'selected' : '' }}>Completo</option>
                                        </select>
                                        @error('dados_cadastrais')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="email">E-mail</label>
                                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                               id="email" name="email" 
                                               value="{{ old('email') }}">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="telefone">Telefone</label>
                                        <input type="text" class="form-control @error('telefone') is-invalid @enderror" 
                                               id="telefone" name="telefone" 
                                               value="{{ old('telefone') }}">
                                        @error('telefone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="ie">Inscrição Estadual</label>
                                        <input type="text" class="form-control @error('ie') is-invalid @enderror" 
                                               id="ie" name="ie" 
                                               value="{{ old('ie') }}">
                                        @error('ie')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="im">Inscrição Municipal</label>
                                        <input type="text" class="form-control @error('im') is-invalid @enderror" 
                                               id="im" name="im" 
                                               value="{{ old('im') }}">
                                        @error('im')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Endereço -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Endereço</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label" for="endereco">Endereço</label>
                                        <input type="text" class="form-control @error('endereco') is-invalid @enderror" 
                                               id="endereco" name="endereco" 
                                               value="{{ old('endereco') }}">
                                        @error('endereco')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="numero">Número</label>
                                        <input type="text" class="form-control @error('numero') is-invalid @enderror" 
                                               id="numero" name="numero" 
                                               value="{{ old('numero') }}">
                                        @error('numero')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="bairro">Bairro</label>
                                        <input type="text" class="form-control @error('bairro') is-invalid @enderror" 
                                               id="bairro" name="bairro" 
                                               value="{{ old('bairro') }}">
                                        @error('bairro')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="complemento">Complemento</label>
                                        <input type="text" class="form-control @error('complemento') is-invalid @enderror" 
                                               id="complemento" name="complemento" 
                                               value="{{ old('complemento') }}">
                                        @error('complemento')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="uf">UF</label>
                                        <select class="form-select @error('uf') is-invalid @enderror" id="uf" name="uf">
                                            <option value="">Selecione...</option>
                                            <option value="AC" {{ old('uf') == 'AC' ? 'selected' : '' }}>AC</option>
                                            <option value="AL" {{ old('uf') == 'AL' ? 'selected' : '' }}>AL</option>
                                            <option value="AP" {{ old('uf') == 'AP' ? 'selected' : '' }}>AP</option>
                                            <option value="AM" {{ old('uf') == 'AM' ? 'selected' : '' }}>AM</option>
                                            <option value="BA" {{ old('uf') == 'BA' ? 'selected' : '' }}>BA</option>
                                            <option value="CE" {{ old('uf') == 'CE' ? 'selected' : '' }}>CE</option>
                                            <option value="DF" {{ old('uf') == 'DF' ? 'selected' : '' }}>DF</option>
                                            <option value="ES" {{ old('uf') == 'ES' ? 'selected' : '' }}>ES</option>
                                            <option value="GO" {{ old('uf') == 'GO' ? 'selected' : '' }}>GO</option>
                                            <option value="MA" {{ old('uf') == 'MA' ? 'selected' : '' }}>MA</option>
                                            <option value="MT" {{ old('uf') == 'MT' ? 'selected' : '' }}>MT</option>
                                            <option value="MS" {{ old('uf') == 'MS' ? 'selected' : '' }}>MS</option>
                                            <option value="MG" {{ old('uf') == 'MG' ? 'selected' : '' }}>MG</option>
                                            <option value="PA" {{ old('uf') == 'PA' ? 'selected' : '' }}>PA</option>
                                            <option value="PB" {{ old('uf') == 'PB' ? 'selected' : '' }}>PB</option>
                                            <option value="PR" {{ old('uf') == 'PR' ? 'selected' : '' }}>PR</option>
                                            <option value="PE" {{ old('uf') == 'PE' ? 'selected' : '' }}>PE</option>
                                            <option value="PI" {{ old('uf') == 'PI' ? 'selected' : '' }}>PI</option>
                                            <option value="RJ" {{ old('uf') == 'RJ' ? 'selected' : '' }}>RJ</option>
                                            <option value="RN" {{ old('uf') == 'RN' ? 'selected' : '' }}>RN</option>
                                            <option value="RS" {{ old('uf') == 'RS' ? 'selected' : '' }}>RS</option>
                                            <option value="RO" {{ old('uf') == 'RO' ? 'selected' : '' }}>RO</option>
                                            <option value="RR" {{ old('uf') == 'RR' ? 'selected' : '' }}>RR</option>
                                            <option value="SC" {{ old('uf') == 'SC' ? 'selected' : '' }}>SC</option>
                                            <option value="SP" {{ old('uf') == 'SP' ? 'selected' : '' }}>SP</option>
                                            <option value="SE" {{ old('uf') == 'SE' ? 'selected' : '' }}>SE</option>
                                            <option value="TO" {{ old('uf') == 'TO' ? 'selected' : '' }}>TO</option>
                                        </select>
                                        @error('uf')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="cep">CEP</label>
                                        <input type="text" class="form-control @error('cep') is-invalid @enderror" 
                                               id="cep" name="cep" 
                                               value="{{ old('cep') }}">
                                        @error('cep')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Resumo e Ações -->
                    <div class="col-lg-4">
                        <!-- Plano Contratado -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Plano</h5>
                                <p class="text-muted mb-0 small">Contrate após cadastrar</p>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-4">
                                    <i class="ti ti-package-off ti-48 text-muted mb-3"></i>
                                    <h6 class="text-muted">Nenhum plano contratado</h6>
                                    <p class="text-muted small">Cadastre a filial primeiro para poder contratar um plano</p>
                                </div>
                            </div>
                        </div>

                        <!-- Usuário -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Usuário</h5>
                                <p class="text-muted mb-0 small">Crie após cadastrar</p>
                            </div>
                            <div class="card-body">
                                <div class="text-center py-4">
                                    <i class="ti ti-user-off ti-48 text-muted mb-3"></i>
                                    <h6 class="text-muted">Nenhum usuário criado</h6>
                                    <p class="text-muted small">Cadastre a filial primeiro para poder criar usuários</p>
                                </div>
                            </div>
                        </div>

                        <div class="card position-sticky" style="top: 20px;">
                            <div class="card-header">
                                <h5 class="mb-0">Informações</h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <i class="ti ti-info-circle me-2"></i>
                                    <strong>Campos Obrigatórios</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Nome da Empresa</li>
                                        <li>Razão Social</li>
                                        <li>CNPJ</li>
                                        <li>Tipo (Única, Matriz ou Filial)</li>
                                        <li>Status</li>
                                        <li>Dados Cadastrais</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras para os campos usando Cleave.js
    if (document.getElementById('cnpj')) {
        new Cleave('#cnpj', {
            delimiters: ['.', '.', '/', '-'],
            blocks: [2, 3, 3, 4, 2],
            uppercase: false
        });
    }
    
    if (document.getElementById('cpf')) {
        new Cleave('#cpf', {
            delimiters: ['.', '.', '-'],
            blocks: [3, 3, 3, 2],
            uppercase: false
        });
    }
    
    if (document.getElementById('telefone')) {
        new Cleave('#telefone', {
            phone: true,
            phoneRegionCode: 'BR'
        });
    }
    
    if (document.getElementById('cep')) {
        new Cleave('#cep', {
            delimiters: ['-'],
            blocks: [5, 3],
            uppercase: false
        });
    }
});
</script>
@endsection

@endsection
