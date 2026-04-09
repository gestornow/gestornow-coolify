@extends('layouts.layoutMaster')

@section('title', 'Cadastrar Novo Usuário')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
@endsection

@section('content')
<div class="container-xxl flex-grow-1">
    <!-- Hidden fields para upload (serão preenchidos após criar o usuário) -->
    <input type="hidden" id="userId" value="">
    <input type="hidden" id="empresaId" value="{{ old('id_empresa', $filters['id_empresa'] ?? session('id_empresa') ?? '') }}">
    <input type="hidden" id="fotoFilename" value="">
    
    <div class="row">
        <div class="col-12">
            <!-- Cabeçalho -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="ti ti-user-plus me-2"></i>
                        Novo Usuário
                    </h4>
                    <p class="text-muted mb-0">Preencha os dados para cadastrar um novo usuário</p>
                </div>
                <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
                    <i class="ti ti-arrow-left me-1"></i>
                    Voltar
                </a>
            </div>

            <!-- Formulário -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form id="userCreateForm" action="{{ route('usuarios.store') }}" method="POST" novalidate>
                                @csrf
                                
                                <!-- Informações Básicas -->
                                <div class="mb-4">
                                    <h5 class="card-title mb-3">
                                        <i class="ti ti-user me-1"></i>
                                        Informações Básicas
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="login" class="form-label">Login/Email <span class="text-danger">*</span></label>
                                            <input id="login" name="login" type="text" class="form-control @error('login') is-invalid @enderror" value="{{ old('login') }}" required>
                                            @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                            <input id="nome" name="nome" type="text" class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}" required>
                                            @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6">
                                            <label for="telefone" class="form-label">Telefone</label>
                                            <input id="telefone" name="telefone" type="text" class="form-control mask-phone" value="{{ old('telefone') }}">
                                        </div>

                                        <div class="col-md-6">
                                            <label for="cpf" class="form-label">CPF</label>
                                            <input id="cpf" name="cpf" type="text" class="form-control mask-cpf" value="{{ old('cpf') }}" aria-describedby="cpfHelp">
                                            <small id="cpfHelp" class="form-text text-danger d-none">CPF inválido</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="id_perfil_global" class="form-label">Perfil Global</label>
                                            <select id="id_perfil_global" name="id_perfil_global" class="form-select @error('id_perfil_global') is-invalid @enderror">
                                                <option value="">Sem perfil global</option>
                                                @foreach(($perfisGlobais ?? []) as $perfil)
                                                    <option value="{{ $perfil->id_perfil_global }}" {{ (string) old('id_perfil_global') === (string) $perfil->id_perfil_global ? 'selected' : '' }}>
                                                        {{ $perfil->nome }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('id_perfil_global')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-6" id="grupoPermissaoWrapper">
                                            <label for="id_grupo" class="form-label">Grupo da Empresa (sobrescreve o perfil global)</label>
                                            <select id="id_grupo" name="id_grupo" class="form-select @error('id_grupo') is-invalid @enderror">
                                                <option value="">Sem grupo especifico</option>
                                                @foreach(($grupos ?? []) as $grupo)
                                                    <option value="{{ $grupo->id_grupo }}" {{ (string) old('id_grupo') === (string) $grupo->id_grupo ? 'selected' : '' }}>
                                                        {{ $grupo->nome }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="text-muted">Se definir grupo, ele tem prioridade sobre o perfil global.</small>
                                            @error('id_grupo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        {{-- Campo Perfil de Suporte - só aparece se id_empresa = 1 --}}
                                        @if((session('id_empresa') ?? Auth::user()->id_empresa ?? 0) == 1)
                                        <div class="col-12">
                                            <div class="alert alert-info d-flex align-items-start" role="alert">
                                                <div class="form-check form-switch mb-0 me-3">
                                                    <input class="form-check-input" type="checkbox" name="is_suporte" id="is_suporte" value="1" {{ old('is_suporte') ? 'checked' : '' }}>
                                                </div>
                                                <div>
                                                    <i class="ti ti-shield-check me-1"></i>
                                                    <strong>Perfil de Suporte</strong>
                                                    <div class="text-muted small mt-1">
                                                        Usuários com perfil de suporte têm acesso a todas as empresas do sistema
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>



                                <!-- Endereço -->
                                <div class="mb-4">
                                    <h5 class="card-title mb-3">
                                        <i class="ti ti-map-pin me-1"></i>
                                        Endereço
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <label for="endereco" class="form-label">Logradouro</label>
                                            <input id="endereco" name="endereco" type="text" class="form-control @error('endereco') is-invalid @enderror" value="{{ old('endereco') }}">
                                            @error('endereco')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="col-md-4">
                                            <label for="cep" class="form-label">CEP</label>
                                            <input id="cep" name="cep" type="text" class="form-control mask-cep @error('cep') is-invalid @enderror" value="{{ old('cep') }}" aria-describedby="cepHelp">
                                            @error('cep')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small id="cepHelp" class="form-text text-danger d-none">CEP inválido</small>
                                        </div>

                                        <div class="col-md-12">
                                            <label for="bairro" class="form-label">Bairro</label>
                                            <input id="bairro" name="bairro" type="text" class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro') }}">
                                            @error('bairro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Informações Adicionais -->
                                <div class="mb-4">
                                    <h5 class="card-title mb-3">
                                        <i class="ti ti-info-circle me-1"></i>
                                        Informações Adicionais
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="comissao" class="form-label">Comissão (%)</label>
                                            <input id="comissao" name="comissao" type="text" class="form-control mask-percent @error('comissao') is-invalid @enderror" value="{{ old('comissao') }}" aria-describedby="comissaoHelp">
                                            @error('comissao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                            <small id="comissaoHelp" class="form-text text-danger d-none">Valor deve ser menor ou igual a 100</small>
                                        </div>

                                        <div class="col-md-6">
                                            <label for="observacoes" class="form-label">Observações</label>
                                            <input id="observacoes" name="observacoes" type="text" class="form-control @error('observacoes') is-invalid @enderror" value="{{ old('observacoes') }}">
                                            @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Foto do Perfil -->
                                <div class="mb-4">
                                    <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 1px solid #667eea30;">
                                        <div class="card-body">
                                            <h6 class="card-title mb-3">
                                                <i class="ti ti-camera me-2"></i>
                                                Foto do Perfil
                                            </h6>
                                            
                                            <div class="row align-items-center">
                                                <div class="col-md-3 text-center">
                                                    <div id="fotoPreview" class="mb-3">
                                                        <div class="avatar avatar-xl">
                                                            <span class="avatar-initial rounded-circle bg-label-primary fs-1">US</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-9">
                                                    <div class="mb-3">
                                                        <label for="fotoUpload" class="form-label small">Selecionar Nova Foto</label>
                                                        <input type="file" class="form-control" id="fotoUpload" accept="image/jpeg,image/jpg,image/png,image/webp">
                                                        <small class="form-text text-muted">
                                                            Formatos: JPG, PNG, WEBP. Tamanho máximo: 10MB
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Anexos -->
                                <div class="mb-4">
                                    <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 1px solid #667eea30;">
                                        <div class="card-body">
                                            <h6 class="card-title mb-3">
                                                <i class="ti ti-paperclip me-2"></i>
                                                Anexos (Documentos)
                                            </h6>
                                            
                                            <div class="mb-3">
                                                <label for="anexoUpload" class="form-label small">Adicionar Anexo</label>
                                                <input type="file" class="form-control" id="anexoUpload" accept=".pdf,.doc,.docx">
                                                <small class="form-text text-muted">
                                                    Formatos: PDF, DOC, DOCX. Tamanho máximo: 20MB
                                                </small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="nomeAnexo" class="form-label small">Nome do Documento</label>
                                                <input type="text" class="form-control" id="nomeAnexo" placeholder="Ex: RG, CNH, Comprovante de Residência">
                                            </div>
                                            
                                            <button type="button" class="btn btn-sm btn-primary mb-3" id="btnAdicionarAnexo" disabled>
                                                <i class="ti ti-plus me-1"></i> Adicionar à Lista
                                            </button>
                                            
                                            <div id="listaAnexosTemp" class="mt-3">
                                                <p class="text-muted small mb-0">Nenhum anexo selecionado</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <!-- Definição de Senha -->
                                <div class="mb-4">
                                    <h5 class="card-title mb-3">
                                        <i class="ti ti-lock me-1"></i>
                                        Definição de Senha
                                    </h5>
                                    
                                    <div class="alert alert-warning d-flex align-items-center mb-3" role="alert">
                                        <i class="ti ti-alert-triangle me-2"></i>
                                        <div>
                                            <strong>Importante:</strong> Escolha como o usuário irá definir sua senha de acesso ao sistema.
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">Método de Criação de Senha <span class="text-danger">*</span></label>
                                            <div class="card mb-2">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="metodo_senha" 
                                                               id="metodo_email_user" value="email" 
                                                               {{ old('metodo_senha', 'email') === 'email' ? 'checked' : '' }} required>
                                                        <label class="form-check-label w-100" for="metodo_email_user">
                                                            <div class="d-flex align-items-start">
                                                                <i class="ti ti-mail ti-sm me-2 mt-1"></i>
                                                                <div>
                                                                    <div class="fw-semibold">Enviar email para criar senha</div>
                                                                    <small class="text-muted">O usuário receberá um link por email para definir sua própria senha (recomendado)</small>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="metodo_senha" 
                                                               id="metodo_direto_user" value="direto" 
                                                               {{ old('metodo_senha') === 'direto' ? 'checked' : '' }} required>
                                                        <label class="form-check-label w-100" for="metodo_direto_user">
                                                            <div class="d-flex align-items-start">
                                                                <i class="ti ti-key ti-sm me-2 mt-1"></i>
                                                                <div>
                                                                    <div class="fw-semibold">Definir senha agora</div>
                                                                    <small class="text-muted">Você define a senha do usuário manualmente</small>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Campos de senha (aparecem apenas quando "Definir senha agora" é selecionado) -->
                                        <div id="senha-fields-user" class="col-12" style="display: none;">
                                            <div class="card bg-light">
                                                <div class="card-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <div class="form-password-toggle">
                                                                <label class="form-label" for="senhaUser">Senha <span class="text-danger">*</span></label>
                                                                <div class="input-group input-group-merge">
                                                                    <input type="password" id="senhaUser" 
                                                                           class="form-control @error('senha') is-invalid @enderror" 
                                                                           name="senha" 
                                                                           placeholder="••••••••" />
                                                                    <span class="input-group-text password-toggle-user cursor-pointer"><i class="ti ti-eye-off"></i></span>
                                                                    @error('senha')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                                <small class="form-text text-muted d-block mt-2">
                                                                    <i class="ti ti-info-circle ti-xs me-1"></i>
                                                                    Mínimo 8 caracteres, deve conter letras e números
                                                                </small>
                                                            </div>
                                                        </div>

                                                        <div class="col-md-6">
                                                            <div class="form-password-toggle">
                                                                <label class="form-label" for="senhaConfirmUser">Confirmar Senha <span class="text-danger">*</span></label>
                                                                <div class="input-group input-group-merge">
                                                                    <input type="password" id="senhaConfirmUser" 
                                                                           class="form-control @error('senha_confirmation') is-invalid @enderror" 
                                                                           name="senha_confirmation" 
                                                                           placeholder="••••••••" />
                                                                    <span class="input-group-text password-toggle-user cursor-pointer"><i class="ti ti-eye-off"></i></span>
                                                                    @error('senha_confirmation')
                                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                                    @enderror
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- hidden company id --}}
                                <input type="hidden" name="id_empresa" value="{{ old('id_empresa', $filters['id_empresa'] ?? session('id_empresa') ?? '') }}">

                                <hr class="my-4">

                                <!-- Botões -->
                                <div class="d-flex justify-content-between pt-3">
                                    <a href="{{ route('usuarios.index') }}" class="btn btn-outline-secondary">
                                        <i class="ti ti-x me-1"></i>
                                        Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-primary" id="userCreateSubmit">
                                        <i class="ti ti-check me-1"></i>
                                        Cadastrar Usuário
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('vendor-script')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/utils.js')}}"></script>
<script src="{{asset('assets/js/password-validation.js')}}"></script>
<script src="{{asset('assets/js/usuarios-create.js')}}?v=20251127008"></script>
<script>
// Toggle de senha - funcionalidade específica desta página
document.addEventListener('DOMContentLoaded', function() {
    const metodoEmailRadio = document.getElementById('metodo_email_user');
    const metodoDirectoRadio = document.getElementById('metodo_direto_user');
    const senhaFields = document.getElementById('senha-fields-user');
    const senhaInput = document.getElementById('senhaUser');
    const confirmInput = document.getElementById('senhaConfirmUser');
    const suporteCheckbox = document.getElementById('is_suporte');
    const grupoWrapper = document.getElementById('grupoPermissaoWrapper');
    const grupoSelect = document.getElementById('id_grupo');

    function updateFormUI() {
        if (metodoDirectoRadio && metodoDirectoRadio.checked) {
            senhaFields.style.display = 'block';
            senhaInput.required = true;
            confirmInput.required = true;
        } else {
            senhaFields.style.display = 'none';
            senhaInput.required = false;
            confirmInput.required = false;
        }
    }

    if (metodoEmailRadio) metodoEmailRadio.addEventListener('change', updateFormUI);
    if (metodoDirectoRadio) metodoDirectoRadio.addEventListener('change', updateFormUI);

    function toggleGrupoPorSuporte() {
        if (!grupoWrapper || !grupoSelect) {
            return;
        }

        const isSuporte = suporteCheckbox ? suporteCheckbox.checked : false;
        grupoWrapper.style.display = isSuporte ? 'none' : '';
        grupoSelect.disabled = isSuporte;

        if (isSuporte) {
            grupoSelect.value = '';
        }
    }

    if (suporteCheckbox) {
        suporteCheckbox.addEventListener('change', toggleGrupoPorSuporte);
    }
    
    updateFormUI();
    toggleGrupoPorSuporte();

    // Toggle de visualização de senha
    $(document).on('click', '.password-toggle-user', function(e) {
        e.preventDefault();
        const $toggle = $(this);
        const $input = $toggle.closest('.input-group').find('input');
        const $icon = $toggle.find('i');
        
        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('ti-eye-off').addClass('ti-eye');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('ti-eye').addClass('ti-eye-off');
        }
    });
});
</script>

@if(session('success'))
<script>
$(document).ready(function() {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: '{{ session('success') }}',
        confirmButtonText: 'OK',
        timer: 3000,
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
@endsection
