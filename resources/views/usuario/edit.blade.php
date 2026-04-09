@extends('layouts.layoutMaster')

@section('title', 'Editar Usuário - ' . ($user->nome ?? 'Usuário'))

@php
$isAdmin = Auth::user() && strtolower(Auth::user()->finalidade ?? '') === 'administrador';
$isSuporteLogado = Auth::user() && (int) (Auth::user()->is_suporte ?? 0) === 1;
$isOwnProfile = Auth::user() && Auth::user()->id_usuario == $user->id_usuario;
$canEditSensitiveFields = $isAdmin; // Apenas admin pode editar campos sensíveis
$canEditGrupoPermissao = $canEditSensitiveFields || $isSuporteLogado;
@endphp

@push('head')
<meta name="id_usuario" content="{{ $user->id_usuario }}">
<meta name="id_empresa" content="{{ $user->id_empresa }}">
<meta name="foto-filename" content="{{ $user->foto_filename ?? '' }}">

@endpush

@section('content')
<div class="container-xxl flex-grow-1">
    <input type="hidden" id="userId" value="{{ $user->id_usuario }}">
    <input type="hidden" id="empresaId" value="{{ $user->id_empresa }}">
    <input type="hidden" id="fotoFilename" value="{{ $user->foto_filename ?? '' }}">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Editar Usuário: {{ $user->nome }}</h5>
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

                    <div id="userModal" data-open-on-errors="{{ $errors->any() ? '1' : '0' }}">
                    <form id="userCreateForm" action="{{ route('usuarios.atualizar', $user->id_usuario) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row g-3">

                         {{-- Foto do Usuário --}}
                            <div class="col-12 mt-4">
                                <div class="card" style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 1px solid #667eea30;">
                                    <div class="card-body">
                                        <h6 class="card-title mb-3">
                                            <i class="ti ti-camera me-2"></i>
                                            Foto do Perfil
                                        </h6>
                                        
                                        <div class="row align-items-center">
                                            <div class="col-md-3 text-center">
                                                <div id="fotoPreview" class="mb-3">
                                                    @if(!empty($user->foto_url))
                                                        <img src="{{ $user->foto_url }}" alt="Foto do Usuário" class="rounded-circle" style="width: 120px; height: 120px; object-fit: cover; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                                    @else
                                                        <div class="avatar avatar-xl">
                                                            <span class="avatar-initial rounded-circle bg-label-primary fs-1">{{ $user->inicial }}</span>
                                                        </div>
                                                    @endif
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
                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-sm btn-primary" id="btnUploadFoto" disabled>
                                                        <i class="ti ti-upload me-1"></i> Upload Foto
                                                    </button>
                                                    @if(!empty($user->foto_url))
                                                    <button type="button" class="btn btn-sm btn-danger" id="btnDeletarFoto">
                                                        <i class="ti ti-trash me-1"></i> Remover Foto
                                                    </button>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            
                            <div class="col-md-6">
                                <label class="form-label small">Login <span class="text-danger">*</span></label>
                                <input id="login" type="text" name="login" class="form-control" value="{{ old('login', $user->login) }}" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small">Nome <span class="text-danger">*</span></label>
                                <input id="nome" type="text" name="nome" class="form-control" value="{{ old('nome', $user->nome) }}" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small">Telefone</label>
                                <input id="telefone" type="text" name="telefone" class="form-control mask-phone" value="{{ old('telefone', $user->telefone) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small">CPF</label>
                                <input id="cpf" type="text" name="cpf" class="form-control mask-cpf" value="{{ old('cpf', $user->cpf) }}" aria-describedby="cpfHelp">
                                <small id="cpfHelp" class="form-text text-danger d-none">CPF inválido</small>
                            </div>

                            @if($canEditSensitiveFields)
                            @if($canEditGrupoPermissao)
                                <div class="col-md-4">
                                    <label class="form-label small">Perfil Global</label>
                                    <select id="id_perfil_global" name="id_perfil_global" class="form-select">
                                        <option value="">Sem perfil global</option>
                                        @foreach(($perfisGlobais ?? []) as $perfil)
                                            <option value="{{ $perfil->id_perfil_global }}" {{ (string) old('id_perfil_global', $user->id_perfil_global ?? '') === (string) $perfil->id_perfil_global ? 'selected' : '' }}>
                                                {{ $perfil->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-4" id="grupoPermissaoWrapper">
                                    <label class="form-label small">Grupo da Empresa (sobrescreve o perfil global)</label>
                                    <select id="id_grupo" name="id_grupo" class="form-select">
                                        <option value="">Sem grupo especifico</option>
                                        @foreach(($grupos ?? []) as $grupo)
                                            <option value="{{ $grupo->id_grupo }}" {{ (string) old('id_grupo', $user->id_grupo) === (string) $grupo->id_grupo ? 'selected' : '' }}>
                                                {{ $grupo->nome }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Quando houver grupo, ele tem prioridade sobre o perfil global.</small>
                                </div>
                            @endif
                            @endif

                            {{-- Campo Perfil de Suporte - só aparece se id_empresa = 1 --}}
                            @if((session('id_empresa') ?? Auth::user()->id_empresa ?? 0) == 1)
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_suporte" id="is_suporte" value="1" {{ old('is_suporte', $user->is_suporte) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_suporte">
                                        <i class="ti ti-shield-check me-1"></i>
                                        Perfil de Suporte
                                    </label>
                                    <small class="d-block text-muted mt-1">
                                        Usuários com perfil de suporte têm acesso a todas as empresas do sistema
                                    </small>
                                </div>
                            </div>
                            @endif

                            <div class="col-md-8">
                                <label class="form-label small">Endereço</label>
                                <input id="endereco" type="text" name="endereco" class="form-control" value="{{ old('endereco', $user->endereco) }}">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small">CEP</label>
                                <input id="cep" type="text" name="cep" class="form-control mask-cep" value="{{ old('cep', $user->cep) }}" aria-describedby="cepHelp">
                                <small id="cepHelp" class="form-text text-danger d-none">CEP inválido</small>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label small">Bairro</label>
                                <input id="bairro" type="text" name="bairro" class="form-control" value="{{ old('bairro', $user->bairro) }}">
                            </div>

                            @if($canEditSensitiveFields)
                            <div class="col-md-6">
                                <label class="form-label small">Comissão (%)</label>
                                <input id="comissao" type="text" name="comissao" class="form-control mask-percent" value="{{ old('comissao', $user->comissao) }}" aria-describedby="comissaoHelp">
                                <small id="comissaoHelp" class="form-text text-danger d-none">Valor deve ser menor ou igual a 100</small>
                            </div>

                            <div class="col-12 mt-3">
                                <label class="form-label small">Observações</label>
                                <textarea id="observacoes" name="observacoes" class="form-control" rows="3">{{ old('observacoes', $user->observacoes) }}</textarea>
                            </div>
                            @endif

                            @if($canEditSensitiveFields)
                            {{-- Anexos do Usuário --}}
                            <div class="col-12 mt-3">
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
                                        
                                        <div class="d-flex gap-2 mb-3">
                                            <button type="button" class="btn btn-sm btn-primary" id="btnUploadAnexo" disabled>
                                                <i class="ti ti-upload me-1"></i> Upload Anexo
                                            </button>
                                        </div>

                                        <div id="listaAnexos" class="mt-3">
                                            <p class="text-muted small">Clique em "Atualizar Lista" para carregar os anexos</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <div class="col-12 mt-3 d-flex justify-content-between">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">Salvar</button>
                                    <a href="{{ $isOwnProfile && !$isAdmin ? url('/') : route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
                                    {{-- Alterar Senha --}}
                                    <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#senhaModal">
                                        <i class="ti ti-key me-1"></i> Alterar Senha
                                    </button>
                                </div>
                                @if($canEditSensitiveFields)
                                <div class="d-flex gap-2">
                                    {{-- Bloquear / Desbloquear / Ativar --}}
                                    @if($user->status === 'bloqueado')
                                        <button type="button" class="btn btn-success user-action" data-action="unlock" data-id="{{ $user->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                            <i class="ti ti-lock-open me-1"></i> Desbloquear
                                        </button>
                                    @elseif($user->status === 'inativo')
                                        <button type="button" class="btn btn-success user-action" data-action="activate" data-id="{{ $user->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                            <i class="ti ti-check me-1"></i> Ativar
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-warning user-action" data-action="block" data-id="{{ $user->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                            <i class="ti ti-lock me-1"></i> Bloquear
                                        </button>
                                    @endif

                                    {{-- Deletar (soft-delete) --}}
                                    <button type="button" class="btn btn-danger user-action" data-action="delete" data-id="{{ $user->id_usuario }}" data-base-url="{{ url('usuarios') }}">
                                        <i class="ti ti-trash me-1"></i> Deletar
                                    </button>
                                </div>
                                @endif
                            </div>
                        </div>
                    </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Alterar Senha -->
<div class="modal fade" id="senhaModal" tabindex="-1" aria-labelledby="senhaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="senhaForm" action="{{ route('usuarios.alterar-senha', $user->id_usuario) }}" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="senhaModalLabel">
                        <i class="ti ti-key me-2"></i>
                        Alterar Senha
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    @csrf
                    @method('PUT')

                    <div class="mb-3 form-password-toggle">
                        <label class="form-label" for="senhaAtual">Senha Atual <span class="text-danger">*</span></label>
                        <div class="input-group input-group-merge">
                            <input type="password" id="senhaAtual" class="form-control @error('senha_atual') is-invalid @enderror" 
                                   name="senha_atual" placeholder="••••••••" required />
                            <span class="input-group-text password-toggle cursor-pointer"><i class="ti ti-eye-off"></i></span>
                            @error('senha_atual')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3 form-password-toggle">
                        <label class="form-label" for="novaSenha">Nova Senha <span class="text-danger">*</span></label>
                        <div class="input-group input-group-merge">
                            <input type="password" id="novaSenha" class="form-control @error('senha') is-invalid @enderror" 
                                   name="senha" placeholder="••••••••" required />
                            <span class="input-group-text password-toggle cursor-pointer"><i class="ti ti-eye-off"></i></span>
                            @error('senha')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="form-text text-muted d-block mt-2">
                            Mínimo 8 caracteres, deve conter letras e números
                        </small>
                    </div>

                    <div class="mb-3 form-password-toggle">
                        <label class="form-label" for="confirmarSenha">Confirmar Nova Senha <span class="text-danger">*</span></label>
                        <div class="input-group input-group-merge">
                            <input type="password" id="confirmarSenha" class="form-control @error('senha_confirmation') is-invalid @enderror" 
                                   name="senha_confirmation" placeholder="••••••••" required />
                            <span class="input-group-text password-toggle cursor-pointer"><i class="ti ti-eye-off"></i></span>
                            @error('senha_confirmation')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="senhaSubmit">
                        <i class="ti ti-check me-1"></i>
                        Alterar Senha
                    </button>
                </div>
            </form>
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
<script src="{{asset('assets/js/usuarios-upload.js')}}?v=20251127007"></script>
<script src="{{asset('assets/js/usuarios-actions.js')}}?v=20251127001"></script>
<script>
// Limpar backdrops presos antes de qualquer outra coisa
window.addEventListener('load', function() {
    // Remove todos os backdrops que podem estar presos
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.remove();
    });
    
    // Remove classe modal-open do body se não houver modal aberto
    const openModals = document.querySelectorAll('.modal.show');
    if (openModals.length === 0) {
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
});

$(document).ready(function() {
    const suporteCheckbox = document.getElementById('is_suporte');
    const grupoWrapper = document.getElementById('grupoPermissaoWrapper');
    const grupoSelect = document.getElementById('id_grupo');

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

    toggleGrupoPorSuporte();

    // Remover qualquer handler AJAX anterior e usar submit normal do formulário
    // Isso evita problemas com CSRF token e sessão
    $('#userCreateForm').off('submit');
    
    // Validação básica antes de submeter
    $('#userCreateForm').on('submit', function(e) {
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        
        // Validação simples
        const login = $form.find('input[name="login"]').val();
        const nome = $form.find('input[name="nome"]').val();
        
        if (!login || !nome) {
            e.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Atenção!',
                text: 'Login e Nome são obrigatórios.',
                confirmButtonText: 'OK'
            });
            return false;
        }
        
        // Desabilita botão para evitar múltiplos cliques
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Salvando...');
        
        // Permite submit normal do formulário
        return true;
    });

    // Tratar submit do formulário de alterar senha
    $('#senhaForm').on('submit', function(e) {
        const $form = $(this);
        const $submitBtn = $form.find('#senhaSubmit');
        
        // Desabilita botão para evitar múltiplos cliques
        $submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Alterando...');
    });

    // Garantir que modais possam ser fechados mesmo com erros de JavaScript
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            // Fechar todos os modais abertos
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(modal => {
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) backdrop.remove();
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            });
        }
    });
});
</script>
@endsection


