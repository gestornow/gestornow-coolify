@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Cadastro de Empresa')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
<style>
  /* Ajustes específicos da página de cadastro */
  .auth-heading { text-align:center; }
  .auth-brand { text-align:center; margin-bottom:2rem; }
  .auth-brand span svg, .auth-brand span img { max-height:70px; height:auto; }
  @media (max-width: 575.98px){
    .auth-brand { margin-bottom:1.5rem; }
  }
  /* Harmonizar feedback abaixo dos campos */
  #formAuthentication .invalid-feedback { margin-top: .35rem; }
  /* Espaço consistente entre campos e botão */
  #formAuthentication button[type=submit]{ margin-top:.25rem; }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{ asset('assets/js/utils.js') }}"></script>
<script src="{{asset('assets/js/pages-registro.js')}}"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const metodoEmailRadio = document.getElementById('metodo_email');
    const metodoDirectoRadio = document.getElementById('metodo_direto');
    const senhaFields = document.getElementById('senha-fields');
    const btnText = document.getElementById('btn-text');
    const senhaInput = document.getElementById('senha');
    const confirmInput = document.getElementById('senha_confirmation');

    function updateFormUI() {
      if (metodoDirectoRadio.checked) {
        // Mostrar campos de senha
        senhaFields.style.display = 'block';
        btnText.textContent = 'Criar Conta e Definir Senha';
        
        // Tornar campos de senha obrigatórios
        senhaInput.required = true;
        confirmInput.required = true;
      } else {
        // Esconder campos de senha
        senhaFields.style.display = 'none';
        btnText.textContent = 'Enviar link de validação';
        
        // Remover obrigatoriedade
        senhaInput.required = false;
        confirmInput.required = false;
      }
    }

    // Adicionar listeners aos radios
    metodoEmailRadio.addEventListener('change', updateFormUI);
    metodoDirectoRadio.addEventListener('change', updateFormUI);

    // Inicializar UI com o valor salvo (se houver)
    updateFormUI();

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
  });
</script>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">

    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-register-illustration-'.$configData['style'].'.png') }}" alt="auth-register-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-register-illustration-light.png" data-app-dark-img="illustrations/auth-register-illustration-dark.png">

        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-register-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->

    <!-- Register -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-1 p-3">
      <div class="w-px-400 mx-auto px-3">
        <!-- Logo -->
        <div class="auth-brand">
          <a href="{{url('/')}}" class="d-inline-flex align-items-center justify-content-center">
            <span>@include('_partials.macros',["height"=>60,"withbg"=>'fill: #fff;'])</span>
          </a>
        </div>
        <h3 class="mb-1 fw-bold auth-heading">Cadastre-se</h3>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="ti ti-check me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ti ti-x me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any() && !$errors->has('razao_social') && !$errors->has('cnpj') && !$errors->has('nome') && !$errors->has('email'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="ti ti-alert-triangle me-2"></i>
                <strong>Atenção!</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form id="formAuthentication" action="{{ route('registro') }}" method="POST">
          @csrf
          <input type="hidden" name="id_plano" value="{{ old('id_plano', request('id')) }}">
          <input type="hidden" name="plano" value="{{ old('plano', request('plano')) }}">
          
          <div class="mb-3">
            <label for="razao_social" class="form-label">Razão Social / Nome da Empresa</label>
            <input type="text" class="form-control @error('razao_social') is-invalid @enderror" 
                   id="razao_social" name="razao_social" 
                   placeholder="Digite o nome da sua empresa" 
                   value="{{ old('razao_social') }}" required autofocus>
            @error('razao_social')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="cnpj" class="form-label">CNPJ</label>
            <input type="text" id="cnpj" class="form-control mask-cnpj @error('cnpj') is-invalid @enderror"
                   name="cnpj" aria-describedby="cnpjHelp"
                   placeholder="00.000.000/0000-00"
                   value="{{ old('cnpj') }}" required>
            @error('cnpj')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small id="cnpjHelp" class="form-text text-danger d-none">CNPJ inválido</small>
          </div>

          <div class="mb-3">
            <label for="nome" class="form-label">Seu Nome Completo</label>
            <input type="text" class="form-control @error('nome') is-invalid @enderror" 
                   id="nome" name="nome" 
                   placeholder="Digite seu nome completo" 
                   value="{{ old('nome') }}" required>
            @error('nome')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="email" class="form-label">Email (será usado para login)</label>
            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                   id="email" name="email" 
                   placeholder="seu@email.com" 
                   value="{{ old('email') }}" required>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <button type="submit" class="btn btn-primary d-grid w-100">
            Enviar link de validação
          </button>
        </form>

        <p class="text-center">
          <span>Já tem uma conta?</span>
          <a href="{{ route('login') }}">
            <span>Entrar</span>
          </a>
        </p>

        <div class="divider my-4">
          <div class="divider-text">ou</div>
        </div>

        <div class="d-flex justify-content-center">

          <a href="javascript:;" class="btn btn-icon btn-label-google-plus">
            <i class="tf-icons fa-brands fa-google fs-5"></i>
          </a>

        </div>
      </div>
    </div>
    <!-- /Register -->
  </div>
</div>
@endsection
