@extends('layouts/layoutMaster')

@section('title', 'Onboarding da Assinatura')

@section('content')
<div class="row mb-4">
  <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div>
      <h4 class="mb-1">Onboarding da Assinatura</h4>
      <p class="text-muted mb-0">Complete os passos obrigatórios para liberar o uso do sistema.</p>
    </div>
    <a href="{{ route('billing.meu-financeiro.index') }}" class="btn btn-label-primary">
      Meu Financeiro
    </a>
  </div>
</div>

@if(session('success'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-success" role="alert">
      <div>{{ session('success') }}</div>
      @if(session('recibo_url'))
      <div class="mt-2">
        <a href="{{ session('recibo_url') }}" class="btn btn-sm btn-success" target="_blank">
          Baixar Recibo de Adesao
        </a>
      </div>
      @endif
    </div>
  </div>
</div>
@endif

@if(session('warning'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
  </div>
</div>
@endif

@if(session('error'))
<div class="row mb-3">
  <div class="col-12">
    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
  </div>
</div>
@endif

<div class="row g-4 mb-4">
  <div class="col-md-4">
    <div class="card h-100 border {{ $dadosCompletos ? 'border-success' : 'border-warning' }}">
      <div class="card-body">
        <h6 class="mb-1">Passo 1</h6>
        <p class="mb-2">Dados cadastrais</p>
        <span class="badge bg-label-{{ $dadosCompletos ? 'success' : 'warning' }}">
          {{ $dadosCompletos ? 'Concluído' : 'Pendente' }}
        </span>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    @php
      $statusPasso2 = $contratoAssinado ? 'concluido' : ($podeAssinarContrato ? 'pendente' : 'bloqueado');
      $classePasso2 = $statusPasso2 === 'concluido' ? 'success' : ($statusPasso2 === 'pendente' ? 'warning' : 'secondary');
      $labelPasso2 = $statusPasso2 === 'concluido' ? 'Concluido' : ($statusPasso2 === 'pendente' ? 'Pendente' : 'Bloqueado');
    @endphp
    <div class="card h-100 border border-{{ $classePasso2 }}">
      <div class="card-body">
        <h6 class="mb-1">Passo 2</h6>
        <p class="mb-2">Assinatura do contrato</p>
        <span class="badge bg-label-{{ $classePasso2 }}">
          {{ $labelPasso2 }}
        </span>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    @php
      $statusMap = [
        'pendente_pagamento' => ['label' => 'Pendente de Pagamento', 'class' => 'warning'],
        'onboarding_dados' => ['label' => 'Aguardando Dados', 'class' => 'info'],
        'onboarding_contrato' => ['label' => 'Aguardando Contrato', 'class' => 'info'],
        'ativa' => ['label' => 'Ativa', 'class' => 'success'],
        'cancelamento_agendado' => ['label' => 'Cancelamento Agendado', 'class' => 'warning'],
        'suspensa' => ['label' => 'Suspensa', 'class' => 'danger'],
      ];
      $statusInfo = $statusMap[$assinatura->status] ?? ['label' => ucfirst((string) $assinatura->status), 'class' => 'secondary'];
    @endphp
    <div class="card h-100 border border-{{ $statusInfo['class'] }}">
      <div class="card-body">
        <h6 class="mb-1">Status da assinatura</h6>
        <p class="mb-2">Plano atual: {{ $assinatura->plano?->nome ?? '-' }}</p>
        <span class="badge bg-label-{{ $statusInfo['class'] }}">{{ $statusInfo['label'] }}</span>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">1. Dados Cadastrais Obrigatórios</h5>
        @if($dadosCompletos)
        <span class="badge bg-label-success">Concluído</span>
        @endif
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('onboarding.dados') }}">
          @csrf
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Razão Social</label>
              <input type="text" name="razao_social" class="form-control" value="{{ old('razao_social', $empresa->razao_social) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Nome Fantasia</label>
              <input type="text" name="nome_empresa" class="form-control" value="{{ old('nome_empresa', $empresa->nome_empresa) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">CNPJ</label>
              <input type="text" name="cnpj" class="form-control" value="{{ old('cnpj', $empresa->cnpj) }}" placeholder="00.000.000/0000-00">
            </div>
            <div class="col-md-6">
              <label class="form-label">CPF</label>
              <input type="text" name="cpf" class="form-control" value="{{ old('cpf', $empresa->cpf) }}" placeholder="000.000.000-00">
            </div>
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control" value="{{ old('email', $empresa->email) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Telefone</label>
              <input type="text" name="telefone" class="form-control" value="{{ old('telefone', $empresa->telefone) }}" required>
            </div>
            <div class="col-md-8">
              <label class="form-label">Endereço</label>
              <input type="text" name="endereco" class="form-control" value="{{ old('endereco', $empresa->endereco) }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Número</label>
              <input type="text" name="numero" class="form-control" value="{{ old('numero', $empresa->numero) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Bairro</label>
              <input type="text" name="bairro" class="form-control" value="{{ old('bairro', $empresa->bairro) }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cidade</label>
              <input type="text" name="cidade" class="form-control" value="{{ old('cidade', $empresa->cidade) }}" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">UF</label>
              <input type="text" name="uf" class="form-control" maxlength="2" value="{{ old('uf', $empresa->uf) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">CEP</label>
              <input type="text" name="cep" class="form-control" value="{{ old('cep', $empresa->cep) }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Complemento</label>
              <input type="text" name="complemento" class="form-control" value="{{ old('complemento', $empresa->complemento) }}">
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary">Salvar Dados Cadastrais</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">2. Assinatura Digital do Contrato</h5>
        @if($contratoAssinado)
        <span class="badge bg-label-success">Concluído</span>
        @endif
      </div>
      <div class="card-body">
        @if(!$podeAssinarContrato)
        <div class="alert alert-warning mb-0" role="alert">
          <div class="fw-semibold mb-1">Passo 2 bloqueado ate concluir os dados cadastrais obrigatorios.</div>
          @if(!empty($camposPendentes))
          <div>Campos pendentes: {{ implode(', ', $camposPendentes) }}.</div>
          @endif
        </div>
        @else
        <div class="mb-3">
          <label class="form-label">{{ $tituloContrato }} (v{{ $versaoContrato }})</label>
          <textarea class="form-control" rows="16" readonly>{{ $corpoContrato }}</textarea>
        </div>

        <form method="POST" action="{{ route('onboarding.contrato') }}" id="form-contrato-onboarding">
          @csrf
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nome do responsável</label>
              <input type="text" name="assinado_por_nome" class="form-control" value="{{ old('assinado_por_nome') }}" required>
            </div>
            <div class="col-12">
              <label class="form-label">Documento do responsável (CPF/CNPJ)</label>
              <input type="text" name="assinado_por_documento" class="form-control" value="{{ old('assinado_por_documento') }}" required>
            </div>
            <div class="col-12">
              <label class="form-label d-block">Assinatura digital</label>

              <div class="d-flex gap-2 mb-2">
                <button type="button" class="btn btn-sm btn-primary" data-assinatura-mode="desenhar">
                  Desenhar assinatura (principal)
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-assinatura-mode="escrever">
                  Escrever assinatura
                </button>
              </div>

              <div id="assinatura-pane-desenhar" class="border rounded p-3 bg-light">
                <p class="text-muted small mb-2">Desenhe sua assinatura no quadro abaixo.</p>
                <canvas id="assinatura-canvas" class="w-100 border rounded bg-white" width="700" height="180" style="touch-action:none;"></canvas>
                <input type="hidden" name="assinatura_desenhada_base64" id="assinatura_desenhada_base64" value="{{ old('assinatura_desenhada_base64') }}">
                <div class="mt-2 text-end">
                  <button type="button" class="btn btn-sm btn-label-secondary" id="limpar-assinatura">Limpar desenho</button>
                </div>
              </div>

              <div id="assinatura-pane-escrever" class="border rounded p-3 mt-2 d-none">
                <p class="text-muted small mb-2">Se preferir, digite a assinatura (nome completo).</p>
                <input type="text" id="assinatura_texto" name="assinatura_texto" class="form-control" value="{{ old('assinatura_texto') }}" maxlength="255">
              </div>

              <small class="text-muted d-block mt-2">Voce pode concluir com assinatura desenhada ou escrita.</small>
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="aceite_contrato" name="aceite_contrato" required>
                <label class="form-check-label" for="aceite_contrato">
                  Declaro que li e aceito integralmente os termos do contrato.
                </label>
              </div>
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-success">Assinar Contrato</button>
          </div>
        </form>
        @endif

        @if($contratoAssinado)
        <hr>
        <div class="d-flex justify-content-end">
          <a href="{{ route('onboarding.contrato.pdf') }}" target="_blank" class="btn btn-label-primary">
            <i class="ti ti-file-download me-1"></i>
            Baixar Recibo de Adesao (PDF)
          </a>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('form-contrato-onboarding');
  const canvas = document.getElementById('assinatura-canvas');
  const hiddenInput = document.getElementById('assinatura_desenhada_base64');
  const inputTexto = document.getElementById('assinatura_texto');
  const btnLimpar = document.getElementById('limpar-assinatura');
  const btnDesenhar = document.querySelector('[data-assinatura-mode="desenhar"]');
  const btnEscrever = document.querySelector('[data-assinatura-mode="escrever"]');
  const paneDesenhar = document.getElementById('assinatura-pane-desenhar');
  const paneEscrever = document.getElementById('assinatura-pane-escrever');

  if (!form || !canvas || !hiddenInput || !btnDesenhar || !btnEscrever || !paneDesenhar || !paneEscrever) {
    return;
  }

  const contexto = canvas.getContext('2d');
  let desenhando = false;

  const configurarPincel = function () {
    contexto.lineWidth = 2;
    contexto.lineCap = 'round';
    contexto.lineJoin = 'round';
    contexto.strokeStyle = '#2f3349';
  };

  const restaurarImagemAssinatura = function (assinaturaDataUrl) {
    if (!assinaturaDataUrl || !assinaturaDataUrl.startsWith('data:image/')) {
      return;
    }

    const imagem = new Image();
    imagem.onload = function () {
      contexto.drawImage(imagem, 0, 0, canvas.width, canvas.height);
    };
    imagem.src = assinaturaDataUrl;
  };

  const ajustarCanvas = function () {
    const larguraRender = Math.max(200, Math.floor(canvas.getBoundingClientRect().width));
    const alturaRender = 180;
    const assinaturaAtual = hiddenInput.value;

    canvas.width = larguraRender;
    canvas.height = alturaRender;
    configurarPincel();
    restaurarImagemAssinatura(assinaturaAtual);
  };

  configurarPincel();
  ajustarCanvas();
  window.addEventListener('resize', ajustarCanvas);

  const obterPosicao = function (evento) {
    const retangulo = canvas.getBoundingClientRect();
    const origem = evento.touches ? evento.touches[0] : evento;

    return {
      x: origem.clientX - retangulo.left,
      y: origem.clientY - retangulo.top
    };
  };

  const iniciarDesenho = function (evento) {
    desenhando = true;
    const posicao = obterPosicao(evento);
    contexto.beginPath();
    contexto.moveTo(posicao.x, posicao.y);
    evento.preventDefault();
  };

  const desenhar = function (evento) {
    if (!desenhando) {
      return;
    }

    const posicao = obterPosicao(evento);
    contexto.lineTo(posicao.x, posicao.y);
    contexto.stroke();
    evento.preventDefault();
  };

  const encerrarDesenho = function (evento) {
    if (!desenhando) {
      return;
    }

    desenhando = false;
    contexto.closePath();
    salvarAssinaturaDesenhada();

    if (evento) {
      evento.preventDefault();
    }
  };

  const canvasEstaVazio = function () {
    const pixels = contexto.getImageData(0, 0, canvas.width, canvas.height).data;

    for (let indice = 3; indice < pixels.length; indice += 4) {
      if (pixels[indice] !== 0) {
        return false;
      }
    }

    return true;
  };

  const salvarAssinaturaDesenhada = function () {
    hiddenInput.value = canvasEstaVazio() ? '' : canvas.toDataURL('image/png');
  };

  const limparDesenho = function () {
    contexto.clearRect(0, 0, canvas.width, canvas.height);
    hiddenInput.value = '';
  };

  const ativarModo = function (modo) {
    const modoDesenhar = modo === 'desenhar';

    paneDesenhar.classList.toggle('d-none', !modoDesenhar);
    paneEscrever.classList.toggle('d-none', modoDesenhar);

    btnDesenhar.classList.toggle('btn-primary', modoDesenhar);
    btnDesenhar.classList.toggle('btn-outline-secondary', !modoDesenhar);
    btnEscrever.classList.toggle('btn-primary', !modoDesenhar);
    btnEscrever.classList.toggle('btn-outline-secondary', modoDesenhar);
  };

  canvas.addEventListener('mousedown', iniciarDesenho);
  canvas.addEventListener('mousemove', desenhar);
  canvas.addEventListener('mouseup', encerrarDesenho);
  canvas.addEventListener('mouseleave', encerrarDesenho);

  canvas.addEventListener('touchstart', iniciarDesenho, { passive: false });
  canvas.addEventListener('touchmove', desenhar, { passive: false });
  canvas.addEventListener('touchend', encerrarDesenho, { passive: false });

  btnLimpar.addEventListener('click', limparDesenho);
  btnDesenhar.addEventListener('click', function () { ativarModo('desenhar'); });
  btnEscrever.addEventListener('click', function () { ativarModo('escrever'); });

  form.addEventListener('submit', function () {
    salvarAssinaturaDesenhada();
    if (hiddenInput.value !== '' && inputTexto) {
      inputTexto.value = '';
    }
  });

  const modoInicial = @json(old('assinatura_desenhada_base64') ? 'desenhar' : (old('assinatura_texto') ? 'escrever' : 'desenhar'));
  ativarModo(modoInicial);
});
</script>
@endsection
