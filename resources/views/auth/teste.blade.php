@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Criar Conta de Teste')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
<style>
  .auth-heading { text-align:center; }
  .auth-brand { text-align:center; margin-bottom:2rem; }
  .auth-brand span svg, .auth-brand span img { max-height:70px; height:auto; }
  .teste-badge {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-size: 0.875rem;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 1rem;
  }
  .plano-card {
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    padding: 1rem;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  .plano-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
  }
  .plano-card.selected {
    border-color: #667eea;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  }
  .plano-card .plano-nome {
    font-weight: 600;
    font-size: 1.1rem;
    color: #333;
  }
  .plano-card .plano-recursos {
    font-size: 0.8rem;
    color: #666;
    margin-top: 0.5rem;
  }
  .plano-card input[type="radio"] {
    display: none;
  }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/utils.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  // Seleção de plano
  document.querySelectorAll('.plano-card').forEach(card => {
    card.addEventListener('click', function() {
      document.querySelectorAll('.plano-card').forEach(c => c.classList.remove('selected'));
      this.classList.add('selected');
      this.querySelector('input[type="radio"]').checked = true;
    });
  });

  // Toggle de visibilidade de senha
  document.querySelectorAll('.password-toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
      const input = this.parentElement.querySelector('input[type="password"], input[type="text"]');
      const icon = this.querySelector('i');
      
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('ti-eye-off');
        icon.classList.add('ti-eye');
      } else {
        input.type = 'password';
        icon.classList.remove('ti-eye');
        icon.classList.add('ti-eye-off');
      }
    });
  });

  // Máscaras
  const cnpjInput = document.getElementById('cnpj');
  const cpfInput = document.getElementById('cpf');

  if (cnpjInput) {
    cnpjInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 14) value = value.slice(0, 14);
      value = value.replace(/^(\d{2})(\d)/, '$1.$2');
      value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
      value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
      value = value.replace(/(\d{4})(\d)/, '$1-$2');
      e.target.value = value;
    });
  }

  if (cpfInput) {
    cpfInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/\D/g, '');
      if (value.length > 11) value = value.slice(0, 11);
      value = value.replace(/(\d{3})(\d)/, '$1.$2');
      value = value.replace(/(\d{3})(\d)/, '$1.$2');
      value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
      e.target.value = value;
    });
  }

  // Form submission
  const form = document.getElementById('formTeste');
  form.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = form.querySelector('button[type="submit"]');
    const btnText = btn.querySelector('.btn-text');
    const spinner = btn.querySelector('.spinner-border');
    
    btn.disabled = true;
    btnText.textContent = 'Criando conta...';
    spinner.classList.remove('d-none');

    fetch(form.action, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value
      },
      body: JSON.stringify(Object.fromEntries(new FormData(form)))
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        window.location.href = data.redirect || '/dashboard';
      } else {
        btn.disabled = false;
        btnText.textContent = 'Começar teste grátis';
        spinner.classList.add('d-none');
        
        if (data.errors) {
          Object.keys(data.errors).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
              input.classList.add('is-invalid');
              const feedback = input.parentElement.querySelector('.invalid-feedback') || document.createElement('div');
              feedback.className = 'invalid-feedback';
              feedback.textContent = data.errors[key][0];
              if (!input.parentElement.querySelector('.invalid-feedback')) {
                input.parentElement.appendChild(feedback);
              }
            }
          });
        }
        
        if (data.message) {
          alert(data.message);
        }
      }
    })
    .catch(error => {
      btn.disabled = false;
      btnText.textContent = 'Começar teste grátis';
      spinner.classList.add('d-none');
      alert('Erro ao criar conta. Tente novamente.');
    });
  });

  // Limpar validação ao digitar
  form.querySelectorAll('input').forEach(input => {
    input.addEventListener('input', function() {
      this.classList.remove('is-invalid');
    });
  });
});
</script>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">

    <!-- Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-register-illustration-'.$configData['style'].'.png') }}" alt="auth-register-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-register-illustration-light.png" data-app-dark-img="illustrations/auth-register-illustration-dark.png">
        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-register-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>

    <!-- Register -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-1 p-3">
      <div class="w-px-400 mx-auto px-3">
        <!-- Logo -->
        <div class="auth-brand">
          <a href="{{url('/')}}" class="d-inline-flex align-items-center justify-content-center">
            <span>@include('_partials.macros',["height"=>60,"withbg"=>'fill: #fff;'])</span>
          </a>
        </div>

        <div class="text-center">
          <span class="teste-badge">
            <i class="ti ti-gift me-1"></i> {{ $diasTeste }} dias grátis
          </span>
        </div>

        <h3 class="mb-1 fw-bold auth-heading">Comece seu teste</h3>
        <p class="mb-4 text-center text-muted">
          Experimente todos os recursos 
          @if($planoSelecionado)
            do plano <strong>{{ $planoSelecionado->nome }}</strong>
          @else
            do plano completo
          @endif
        </p>

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ti ti-x me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form id="formTeste" action="{{ route('teste.store') }}" method="POST">
          @csrf
          
          <!-- Plano selecionado (hidden) -->
          @if($planoSelecionado)
            <input type="hidden" name="id_plano" value="{{ $planoSelecionado->id_plano }}">
          @endif

          <!-- Nome da Empresa -->
          <div class="mb-3">
            <label for="razao_social" class="form-label">Nome da Empresa</label>
            <input type="text" class="form-control @error('razao_social') is-invalid @enderror" id="razao_social" name="razao_social" placeholder="Minha Empresa Ltda" value="{{ old('razao_social') }}" required autofocus>
            @error('razao_social')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <!-- CNPJ ou CPF -->
          <div class="mb-3">
            <label class="form-label">CNPJ ou CPF <small class="text-muted">(opcional)</small></label>
            <div class="row g-2">
              <div class="col-6">
                <input type="text" class="form-control @error('cnpj') is-invalid @enderror" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" value="{{ old('cnpj') }}">
              </div>
              <div class="col-6">
                <input type="text" class="form-control @error('cpf') is-invalid @enderror" id="cpf" name="cpf" placeholder="000.000.000-00" value="{{ old('cpf') }}">
              </div>
            </div>
          </div>

          <!-- Seu Nome -->
          <div class="mb-3">
            <label for="nome" class="form-label">Seu Nome</label>
            <input type="text" class="form-control @error('nome') is-invalid @enderror" id="nome" name="nome" placeholder="João Silva" value="{{ old('nome') }}" required>
            @error('nome')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <!-- Email -->
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="seu@email.com" value="{{ old('email') }}" required>
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <!-- Senha -->
          <div class="mb-3">
            <label for="senha" class="form-label">Crie sua senha</label>
            <div class="input-group input-group-merge">
              <input type="password" class="form-control @error('senha') is-invalid @enderror" id="senha" name="senha" placeholder="••••••••" required minlength="6">
              <span class="input-group-text cursor-pointer password-toggle"><i class="ti ti-eye-off"></i></span>
            </div>
            @error('senha')
              <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <!-- Confirmar Senha -->
          <div class="mb-4">
            <label for="senha_confirmation" class="form-label">Confirme a senha</label>
            <div class="input-group input-group-merge">
              <input type="password" class="form-control" id="senha_confirmation" name="senha_confirmation" placeholder="••••••••" required>
              <span class="input-group-text cursor-pointer password-toggle"><i class="ti ti-eye-off"></i></span>
            </div>
          </div>

          <!-- Seleção de Plano (se não veio selecionado) -->
          @if(!$planoSelecionado && $planos->count() > 1)
          <div class="mb-4">
            <label class="form-label">Escolha o plano para testar</label>
            <div class="row g-2">
              @foreach($planos as $index => $plano)
              <div class="col-6">
                <label class="plano-card d-block {{ $index === $planos->count() - 1 ? 'selected' : '' }}">
                  <input type="radio" name="id_plano" value="{{ $plano->id_plano }}" {{ $index === $planos->count() - 1 ? 'checked' : '' }}>
                  <div class="plano-nome">{{ $plano->nome }}</div>
                  <div class="plano-recursos">
                    @if($plano->descricao)
                      {{ \Str::limit($plano->descricao, 50) }}
                    @else
                      Todos os recursos
                    @endif
                  </div>
                </label>
              </div>
              @endforeach
            </div>
          </div>
          @endif

          <!-- Submit -->
          <button type="submit" class="btn btn-primary d-grid w-100">
            <span class="d-flex align-items-center justify-content-center">
              <span class="spinner-border spinner-border-sm me-2 d-none" role="status"></span>
              <span class="btn-text">Começar teste grátis</span>
            </span>
          </button>
        </form>

        <p class="text-center mt-4">
          <span>Já tem uma conta?</span>
          <a href="{{ route('login.form') }}">
            <span>Fazer login</span>
          </a>
        </p>

        <p class="text-center text-muted small">
          <i class="ti ti-shield-check me-1"></i>
          Sem cartão de crédito • Cancele quando quiser
        </p>
      </div>
    </div>
  </div>
</div>
@endsection
