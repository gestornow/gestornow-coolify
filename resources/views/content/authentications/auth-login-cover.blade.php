@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Login')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/pages-auth.js')}}"></script>
<script src="{{asset('assets/js/secure-auth.js')}}?v=1"></script>
<script>
// Marcar como página de login para o sistema de segurança
document.body.setAttribute('data-page', 'login');
document.body.setAttribute('data-authenticated', '{{ Auth::check() ? "true" : "false" }}');
</script>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">
    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-login-illustration-'.$configData['style'].'.png') }}" alt="auth-login-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-login-illustration-light.png" data-app-dark-img="illustrations/auth-login-illustration-dark.png">

        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-login-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->

    <!-- Login -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-3 p-1">
      <div class="w-px-400 mx-auto px-3">
        <!-- Logo -->
        <div style="display:flex; flex-direction:column;">
          <a href="{{url('/')}}" style="margin-bottom:40px;">
            <span>@include('_partials.macros',["height"=>60,"withbg"=>'fill: #fff;'])</span>
          </a>
        <!-- /Logo -->
        <h3 class="mb-1 fw-bold" style="text-align: center;">Bem-vindo ao GestorNow!</h3>
        <p class="mb-4" style="text-align: center;">Software para Locadoras</p>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if (isset($sessionWarning) && $sessionWarning)
            <div class="alert alert-warning alert-dismissible" role="alert">
                <i class="ti ti-alert-triangle me-2"></i>
                <strong>Atenção!</strong> {{ $sessionWarning }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form id="formAuthentication" action="{{ route('login') }}" method="POST">
          @csrf
          <div class="mb-3">
            <label for="login" class="form-label">Email ou CPF</label>
            <input type="text" 
                   class="form-control @error('login') is-invalid @enderror" 
                   id="login" 
                   name="login" 
                   placeholder="Digite seu email ou CPF" 
                   value="{{ old('login', $rememberedLogin ?? '') }}"
                   autofocus>
            @error('login')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          
          <div class="mb-3 form-password-toggle">
            <div class="d-flex justify-content-between">
              <label class="form-label" for="senha">Senha</label>
              <a href="{{ route('recuperar-senha.form') }}">
                <small>Esqueceu a senha?</small>
              </a>
            </div>
            <div class="input-group input-group-merge">
              <input type="password" 
                     id="senha" 
                     class="form-control @error('senha') is-invalid @enderror" 
                     name="senha" 
                     placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
                     aria-describedby="senha" />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              @error('senha')
                  <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" 
                     type="checkbox" 
                     id="lembrar" 
                     name="lembrar"
                     {{ (old('lembrar') || ($rememberChecked ?? false)) ? 'checked' : '' }}>
              <label class="form-check-label" for="lembrar">
                Lembrar-me
              </label>
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary d-grid w-100">
            Entrar
          </button>
        </form>

        <p class="text-center">
          <span>Novo por aqui?</span>
          <a href="{{ route('registro.form') }}">
            <span>Criar uma conta</span>
          </a>
        </p>
      </div>
    </div>
    <!-- /Login -->
  </div>
</div>

@if (isset($sessionWarning) && $sessionWarning)
<script>
  // Limpar o cookie após exibir a mensagem
  document.cookie = 'session_warning=; path=/; max-age=0';
</script>
@endif

@endsection
