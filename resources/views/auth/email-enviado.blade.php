@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Email Enviado')

@section('page-style')
<!-- Page -->
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-auth.css')}}">
@endsection

@section('content')
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">

    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="{{ asset('assets/img/illustrations/auth-verify-email-illustration-'.$configData['style'].'.png') }}" alt="auth-verify-email-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-verify-email-illustration-light.png" data-app-dark-img="illustrations/auth-verify-email-illustration-dark.png">

        <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="auth-verify-email-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->

    <!-- Verify Email -->
    <div class="d-flex col-12 col-lg-5 align-items-center p-sm-3 p-1">
      <div class="w-px-400 mx-auto px-3">
        <!-- Logo -->
        <div class="mb-1">
          <a href="{{url('/')}}" class="gap-2">
            <span>@include('_partials.macros',["height"=>60,"withbg"=>'fill: #fff;'])</span>
          </a>
        </div>
        <!-- /Logo -->
        
        <div class="text-center mb-2">
          <div class="mb-3">
            <i class="ti ti-mail-check text-primary" style="font-size: 4rem;"></i>
          </div>
          <h3 class="mb-1 fw-bold">Email Enviado!</h3>
          <p class="mb-4">Olá{{ session('nome') ? ', ' . session('nome') : '' }}!</p>
        </div>
        
        <div class="card">
          <div class="card-body">
            <h6 class="card-title">Próximos passos:</h6>
            <ol class="mb-0">
              <li class="mb-2">
                <strong>Verifique seu email ({{ session('email') }})</strong><br>
                <small class="text-muted">Acesse sua caixa de entrada</small>
              </li>
              <li class="mb-2">
                <strong>Clique no link de validação</strong><br>
                <small class="text-muted">O link expira em 24 horas</small>
              </li>
              <li class="mb-2">
                <strong>Crie sua senha</strong><br>
                <small class="text-muted">Finalize seu cadastro</small>
              </li>
              <li>
                <strong>Faça login</strong><br>
                <small class="text-muted">Acesse o sistema</small>
              </li>
            </ol>
          </div>
        </div>



        <div class="text-center mt-4">
          <p class="mb-2">
            <small class="text-muted">Não recebeu o email?</small>
          </p>
          <p class="mb-0">
            <small class="text-muted">
              Verifique sua caixa de spam ou 
              <a href="{{ route('registro.form') }}">tente novamente</a>
            </small>
          </p>
        </div>

        <div class="text-center mt-4">
          <a href="{{ route('login') }}" class="btn btn-outline-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" d="M14 17V7l-6 5z"/><path fill="currentColor" d="M12 2C6.486 2 2 6.486 2 12s4.486 10 10 10s10-4.486 10-10S17.514 2 12 2m0 18c-4.411 0-8-3.589-8-8s3.589-8 8-8s8 3.589 8 8s-3.589 8-8 8"/></svg>
            &nbsp;&nbsp; Voltar ao Login
          </a>
        </div>
      </div>
    </div>
    <!-- /Verify Email -->
  </div>
</div>
@endsection