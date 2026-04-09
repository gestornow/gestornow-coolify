@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/blankLayout')

@section('title', 'Criar Senha')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('page-script')
<script src="{{ asset('assets/js/pages-criar-senha.js') }}?v=3"></script>
@endsection

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
<style>
.password-strength {
  margin-top: 8px;
  font-size: 0.75rem;
}
.strength-weak { color: #dc3545; }
.strength-medium { color: #fd7e14; }
.strength-strong { color: #198754; }
.password-toggle {
  cursor: pointer;
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 8px 12px;
  user-select: none;
}

.password-toggle i {
  font-size: 1.2rem;
  pointer-events: none;
}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
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
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="mb-4" style="display:flex; justify-content:center;">
          <a href="{{url('/')}}">
            <span>@include('_partials.macros',["height"=>40,"withbg"=>'fill: #fff;'])</span>
          </a>
        </div>
        <!-- /Logo -->
        <h3 class="mb-1 fw-bold" style="text-align: center;">Criar Senha</h3>
        <p class="mb-4">Bem-vindo {{ $usuario->nome }}! Complete seu cadastro criando uma senha segura</p>

        <form class="mb-3" action="{{ route('usuario.criar-senha') }}" method="POST">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">
          
          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="senha">Senha</label>
            <div class="input-group input-group-merge">
              <input type="password" id="senha" 
                     class="form-control @error('senha') is-invalid @enderror" 
                     name="senha" 
                     placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
                     required />
              <span class="input-group-text password-toggle cursor-pointer"><i class="ti ti-eye-off"></i></span>
              @error('senha')
                  <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div id="passwordStrength" class="password-strength"></div>
          </div>

          <div class="mb-3 form-password-toggle">
            <label class="form-label" for="senha_confirmation">Confirmar Senha</label>
            <div class="input-group input-group-merge">
              <input type="password" id="senha_confirmation" 
                     class="form-control @error('senha_confirmation') is-invalid @enderror" 
                     name="senha_confirmation" 
                     placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
                     required />
              <span class="input-group-text password-toggle cursor-pointer"><i class="ti ti-eye-off"></i></span>
              @error('senha_confirmation')
                  <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
            <div id="confirmFeedback" class="password-strength"></div>
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input @error('aceita_termos') is-invalid @enderror" 
                     type="checkbox" id="aceita_termos" name="aceita_termos" 
                     {{ old('aceita_termos') ? 'checked' : '' }} required>
              <label class="form-check-label" for="aceita_termos">
                Eu aceito os <a href="javascript:void(0);">termos de privacidade e uso</a>
              </label>
              @error('aceita_termos')
                  <div class="invalid-feedback">{{ $message }}</div>
              @enderror
            </div>
          </div>
          
          <button type="submit" class="btn btn-primary d-grid w-100">
            Finalizar Cadastro
          </button>
        </form>

        <p class="text-center">
          <span>Já tem uma conta?</span>
          <a href="{{ route('login') }}">
            <span>Entrar</span>
          </a>
        </p>
      </div>
    </div>
    <!-- /Register -->
  </div>
</div>
@endsection
