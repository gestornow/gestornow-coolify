@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Nova Senha - GestorNow')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
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
}
.password-toggle:hover {
  color: #0397f9;
}
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
@endsection

@section('page-script')
<script src="{{asset('assets/js/pages-reset-password.js')}}"></script>
<script>
document.body.setAttribute('data-page', 'nova-senha');
</script>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">

    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-reset-password-illustration-'.$configData['style'].'.png') }}" alt="auth-new-password-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-reset-password-illustration-light.png" data-app-dark-img="illustrations/auth-reset-password-illustration-dark.png">

        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-new-password-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->

    <!-- New Password -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="auth-brand mb-4">
          <a href="{{url('/')}}" class="d-inline-flex align-items-center justify-content-center" title="GestorNow">
            <span>@include('_partials.macros',["height"=>50,"withbg"=>'fill: #0397f9;'])</span>
          </a>
        </div>
        <!-- /Logo -->
        
        <div class="text-center mb-4">
          <h3 class="mb-1 fw-bold">Definir Nova Senha</h3>
          <p class="mb-0">Sua identidade foi verificada! Defina uma nova senha segura.</p>
        </div>

        @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="ti ti-x-circle me-2"></i>
          {{ session('error') }}
        </div>
        @endif

        <form id="formAuthentication" class="mb-3" action="{{ route('reset-password.update') }}" method="POST">
          @csrf
          <input type="hidden" name="reset_token" value="{{ session('reset_token') }}">
          
          <div class="mb-3">
            <label for="password" class="form-label">Nova Senha</label>
            <div class="input-group input-group-merge">
              <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Digite sua nova senha" autofocus>
              <span class="input-group-text password-toggle">
                <i class="ti ti-eye-off"></i>
              </span>
            </div>
            <div id="passwordStrength" class="password-strength"></div>
            @error('password')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-4">
            <label for="password_confirmation" class="form-label">Confirmar Nova Senha</label>
            <div class="input-group input-group-merge">
              <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" placeholder="Confirme sua nova senha">
              <span class="input-group-text password-toggle">
                <i class="ti ti-eye-off"></i>
              </span>
            </div>
            <div id="confirmFeedback"></div>
            @error('password_confirmation')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>
          
          <button class="btn btn-primary d-grid w-100 mb-3" type="submit" disabled>
            <span class="d-flex align-items-center justify-content-center">
              <i class="ti ti-lock me-2"></i>
              Atualizar Senha
            </span>
          </button>
        </form>

        <div class="text-center">
          <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center">
            <i class="ti ti-chevron-left scaleX-n1-rtl"></i>
            Voltar para login
          </a>
        </div>
      </div>
    </div>
    <!-- /New Password -->
  </div>
</div>
@endsection