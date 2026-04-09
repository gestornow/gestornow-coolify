<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true" data-open-on-errors="{{ $errors->any() ? '1' : '0' }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="userCreateForm" action="{{ route('usuarios.store') }}" method="POST" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title d-flex align-items-center" id="userModalLabel">
                        <i class="ti ti-user-plus me-2"></i>
                        Novo Usuário
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="login" class="form-label">Login <span class="text-danger">*</span></label>
                            <input id="login" name="login" type="text" class="form-control @error('login') is-invalid @enderror" value="{{ old('login') }}" required>
                            @error('login')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
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

                        <div class="col-md-12">
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

                        {{-- Campo Perfil de Suporte - só aparece se id_empresa = 1 --}}
                        @if((session('id_empresa') ?? Auth::user()->id_empresa ?? 0) == 1)
                        <div class="col-md-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="is_suporte" id="is_suporte" value="1" {{ old('is_suporte') ? 'checked' : '' }}>
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
                            <label for="endereco" class="form-label">Endereço</label>
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

                         <div class="col-md-6">
                            <label for="comissao" class="form-label">Comissão (%)</label>
                            <input id="comissao" name="comissao" type="text" class="form-control mask-percent @error('comissao') is-invalid @enderror" value="{{ old('comissao') }}" aria-describedby="comissaoHelp">
                            @error('comissao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small id="comissaoHelp" class="form-text text-danger d-none">Valor deve ser menor ou igual a 100</small>
                        </div>

                        <div class="col-md-6">
                            <label for="observacoes" class="form-label">Observação</label>
                            <input id="observacoes" name="observacoes" type="text" class="form-control @error('observacoes') is-invalid @enderror" value="{{ old('observacoes') }}">
                            @error('observacoes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <!-- Opções de criação de senha -->
                        <div class="col-md-12">
                            <label class="form-label">Como definir a senha?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_senha" 
                                       id="metodo_email_user" value="email" 
                                       {{ old('metodo_senha', 'email') === 'email' ? 'checked' : '' }} required>
                                <label class="form-check-label" for="metodo_email_user">
                                    <span class="fw-500">Enviar email para criar senha</span>
                                    <small class="d-block text-muted">O usuário receberá um link para definir sua senha</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_senha" 
                                       id="metodo_direto_user" value="direto" 
                                       {{ old('metodo_senha') === 'direto' ? 'checked' : '' }} required>
                                <label class="form-check-label" for="metodo_direto_user">
                                    <span class="fw-500">Definir senha agora</span>
                                    <small class="d-block text-muted">Você define a senha do usuário</small>
                                </label>
                            </div>
                        </div>

                        <!-- Campos de senha (aparecem apenas quando "Definir senha agora" é selecionado) -->
                        <div id="senha-fields-user" class="col-md-12" style="display: none;">
                            <div class="form-password-toggle mb-3">
                                <label class="form-label" for="senhaUser">Senha</label>
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
                                    Mínimo 8 caracteres, deve conter letras e números
                                </small>
                            </div>

                            <div class="form-password-toggle mb-3">
                                <label class="form-label" for="senhaConfirmUser">Confirmar Senha</label>
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

                        {{-- hidden company id (prefill with filters or logged user) --}}
                        <input type="hidden" name="id_empresa" value="{{ old('id_empresa', $filters['id_empresa'] ?? session('id_empresa') ?? '') }}">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="ti ti-x me-1"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="userCreateSubmit">
                        <i class="ti ti-check me-1"></i>
                        Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


@section('page-script')
<script src="{{asset('assets/js/utils.js')}}"></script>
<script src="{{asset('assets/js/password-validation.js')}}"></script>
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

document.addEventListener('DOMContentLoaded', function() {
    const metodoEmailRadio = document.getElementById('metodo_email_user');
    const metodoDirectoRadio = document.getElementById('metodo_direto_user');
    const senhaFields = document.getElementById('senha-fields-user');
    const senhaInput = document.getElementById('senhaUser');
    const confirmInput = document.getElementById('senhaConfirmUser');

    function updateFormUI() {
      if (metodoDirectoRadio && metodoDirectoRadio.checked) {
        // Mostrar campos de senha
        senhaFields.style.display = 'block';
        
        // Tornar campos de senha obrigatórios
        senhaInput.required = true;
        confirmInput.required = true;
      } else {
        // Esconder campos de senha
        senhaFields.style.display = 'none';
        
        // Remover obrigatoriedade
        senhaInput.required = false;
        confirmInput.required = false;
      }
    }

    // Adicionar listeners aos radios
    if (metodoEmailRadio) metodoEmailRadio.addEventListener('change', updateFormUI);
    if (metodoDirectoRadio) metodoDirectoRadio.addEventListener('change', updateFormUI);

    // Inicializar UI com o valor salvo (se houver)
    updateFormUI();
  });
</script>
@endsection

