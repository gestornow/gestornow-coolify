@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Código de Redefinição - GestorNow')

@section('vendor-style')
<!-- Vendor -->
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
@endsection

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
<style>
.code-input {
  font-family: 'Courier New', monospace;
  font-size: 1.5rem;
  font-weight: 600;
  text-align: center;
  letter-spacing: 0.5rem;
  padding: 1rem;
  border: 2px solid #e5e9f2;
  border-radius: 12px;
  transition: all 0.2s ease;
}
.code-input:focus {
  border-color: #0397f9;
  box-shadow: 0 0 0 0.2rem rgba(3, 151, 249, 0.25);
}
.resend-timer {
  color: #64748b;
  font-size: 0.875rem;
}
.resend-link {
  color: #0397f9;
  text-decoration: none;
  font-weight: 500;
}
.resend-link:hover {
  text-decoration: underline;
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
document.body.setAttribute('data-page', 'codigo-redefinicao');
// Inicializar sistema de reenvio específico desta página
document.addEventListener('DOMContentLoaded', function() {
  initResendSystem();
});
</script>
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">

    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-reset-password-illustration-'.$configData['style'].'.png') }}" alt="auth-reset-code-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-reset-password-illustration-light.png" data-app-dark-img="illustrations/auth-reset-password-illustration-dark.png">

        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-reset-code-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->

    <!-- Reset Code -->
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
          <h3 class="mb-1 fw-bold">Digite o Código</h3>
          <p class="mb-2">Enviamos um código de 6 dígitos para</p>
          <p class="fw-semibold text-primary">{{ session('email') }}</p>
        </div>

        @if(session('success'))
        <div class="alert alert-success d-flex align-items-center" role="alert">
          <i class="ti ti-check-circle me-2"></i>
          {{ session('success') }}
        </div>
        @endif

        @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center" role="alert">
          <i class="ti ti-x-circle me-2"></i>
          {{ session('error') }}
        </div>
        @endif

        <form id="formAuthentication" class="mb-3" action="{{ route('reset-code.verify') }}" method="POST">
          @csrf
          <div class="mb-4">
            <label for="code" class="form-label">Código de Verificação</label>
            <input type="text" class="form-control code-input @error('code') is-invalid @enderror" id="code" name="code" placeholder="000000" maxlength="6" autofocus>
            @error('code')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          
          <button class="btn btn-primary d-grid w-100 mb-3" type="submit">
            <span class="d-flex align-items-center justify-content-center">
              <i class="ti ti-check me-2"></i>
              Verificar Código
            </span>
          </button>
        </form>

        <div class="text-center">
          <p class="mb-2">
            <small class="text-muted">Não recebeu o código?</small>
          </p>
          <p class="mb-3">
            <span class="resend-timer" id="resendTimer" style="display: none;"></span>
            <a href="#" class="resend-link" id="resendBtn" style="display: none;">Reenviar código</a>
          </p>
          <a href="{{ route('forgot-password.form') }}" class="d-flex align-items-center justify-content-center">
            <i class="ti ti-chevron-left scaleX-n1-rtl"></i>
            Voltar
          </a>
        </div>
      </div>
    </div>
    <!-- /Reset Code -->
  </div>
</div>
@endsection