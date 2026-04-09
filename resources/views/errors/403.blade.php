@php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Acesso Negado')

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}">
@endsection

@section('content')
<div class="container-xxl container-p-y">
  <div class="misc-wrapper">
    <h2 class="mb-1 mx-2">Acesso negado (403)</h2>
    <p class="mb-4 mx-2">
      Você não possui permissão para acessar esta área com o seu perfil atual.<br>
      Entre em contato com o administrador da sua empresa para solicitar o acesso.
    </p>

    <div class="d-flex gap-2 flex-wrap justify-content-center mb-4">
      <a href="{{ url('/') }}" class="btn btn-primary">Ir para a página inicial</a>
      <button type="button" class="btn btn-label-secondary" onclick="window.history.back()">Voltar</button>
    </div>

    <div class="mt-2">
      <img src="{{ asset('assets/img/illustrations/page-misc-you-are-not-authorized.png') }}" alt="acesso-negado" width="170" class="img-fluid">
    </div>
  </div>
</div>
<div class="container-fluid misc-bg-wrapper">
  <img src="{{ asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png') }}" alt="fundo-acesso-negado" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
</div>
@endsection
