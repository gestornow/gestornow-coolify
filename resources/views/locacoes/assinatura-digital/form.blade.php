<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Assinatura Digital do Contrato</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .assinatura-canvas { border: 1px solid #ced4da; border-radius: .5rem; background: #fff; width: 100%; height: 220px; touch-action: none; }
        .card-principal { max-width: 820px; margin: 2rem auto; }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="card shadow-sm card-principal">
        <div class="card-body p-4">
            <h4 class="mb-1">Assinatura Digital do Contrato</h4>
            <p class="text-muted mb-4">Contrato #{{ $locacao->numero_contrato ?? $locacao->id_locacao }} - {{ $cliente->nome ?? 'Cliente' }}</p>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if(session('info'))
                <div class="alert alert-info">{{ session('info') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="mb-3">
                <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinatura->token, 'tipo' => $tipoDocumento ?? 'contrato', 'id_modelo' => $idModeloDocumento]) }}">
                    Visualizar contrato em PDF
                </a>
            </div>

            @if($jaAssinado)
                <div class="alert alert-success mb-3">
                    <strong>✓ Este contrato já foi assinado digitalmente.</strong>
                    @if(!empty($assinatura->assinado_em))
                    <br><small>Assinado em: {{ $assinatura->assinado_em->format('d/m/Y H:i:s') }}</small>
                    @endif
                </div>
                @if(!empty($assinatura->assinatura_cliente_url))
                    <div class="text-center p-3 border rounded bg-white mb-3">
                        <img src="{{ $assinatura->assinatura_cliente_url }}" alt="Assinatura" style="max-width: 100%; max-height: 180px;">
                    </div>
                @endif
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-success" href="{{ route('locacoes.assinatura-digital.contrato', ['token' => $assinatura->token, 'tipo' => $tipoDocumento ?? 'contrato', 'id_modelo' => $idModeloDocumento]) }}" target="_blank">
                        <i class="bi bi-file-earmark-check"></i> Abrir PDF Assinado (com hash)
                    </a>
                    <a class="btn btn-outline-primary" href="{{ route('locacoes.assinatura-digital.contrato-assinado', ['token' => $assinatura->token]) }}" target="_blank">
                        Ver versão web do contrato assinado
                    </a>
                </div>
            @else
                <form method="POST" action="{{ route('locacoes.assinatura-digital.salvar', ['token' => $assinatura->token, 'tipo' => $tipoDocumento ?? 'contrato', 'id_modelo' => $idModeloDocumento]) }}" enctype="multipart/form-data" id="formAssinaturaDigital">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Como deseja assinar?</label>
                        <div class="d-flex flex-wrap gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assinatura_tipo" id="tipoDesenho" value="desenho" checked>
                                <label class="form-check-label" for="tipoDesenho">Desenhar</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assinatura_tipo" id="tipoUpload" value="upload">
                                <label class="form-check-label" for="tipoUpload">Enviar imagem</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="assinatura_tipo" id="tipoDigitada" value="digitada">
                                <label class="form-check-label" for="tipoDigitada">Digitar nome</label>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="assinatura_desenho" id="assinaturaDesenhoInput">

                    <div id="blocoDesenho" class="mb-3">
                        <canvas id="assinaturaCanvas" class="assinatura-canvas"></canvas>
                        <div class="mt-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnLimparCanvas">Limpar</button>
                        </div>
                    </div>

                    <div id="blocoUpload" class="mb-3 d-none">
                        <label class="form-label">Selecione a imagem da assinatura</label>
                        <input type="file" class="form-control" name="assinatura_upload" id="assinaturaUpload" accept=".png,.jpg,.jpeg,.webp">
                    </div>

                    <div id="blocoDigitada" class="mb-3 d-none">
                        <label class="form-label">Digite seu nome para gerar a assinatura</label>
                        <input type="text" class="form-control" id="nomeDigitado" maxlength="120" placeholder="Digite seu nome completo">
                        <small class="text-muted">Ao enviar, o nome digitado será convertido em assinatura.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Confirmar Assinatura</button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formAssinaturaDigital');
    if (!form) return;

    const canvas = document.getElementById('assinaturaCanvas');
    const ctx = canvas.getContext('2d');
    const inputDataUrl = document.getElementById('assinaturaDesenhoInput');
    const btnLimpar = document.getElementById('btnLimparCanvas');
    const tipoRadios = document.querySelectorAll('input[name="assinatura_tipo"]');
    const blocoDesenho = document.getElementById('blocoDesenho');
    const blocoUpload = document.getElementById('blocoUpload');
    const blocoDigitada = document.getElementById('blocoDigitada');
    const nomeDigitado = document.getElementById('nomeDigitado');
    const assinaturaUpload = document.getElementById('assinaturaUpload');

    function resizeCanvas() {
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        canvas.width = Math.floor(width * ratio);
        canvas.height = Math.floor(height * ratio);
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111';
        ctx.fillStyle = '#111';
        ctx.fillRect(0, 0, width, height);
        ctx.clearRect(0, 0, width, height);
    }

    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    let drawing = false;
    let hasStroke = false;

    function getPos(event) {
        const rect = canvas.getBoundingClientRect();
        if (event.touches && event.touches[0]) {
            return { x: event.touches[0].clientX - rect.left, y: event.touches[0].clientY - rect.top };
        }
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    function startDraw(event) {
        drawing = true;
        const pos = getPos(event);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        event.preventDefault();
    }

    function moveDraw(event) {
        if (!drawing) return;
        const pos = getPos(event);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        hasStroke = true;
        event.preventDefault();
    }

    function endDraw(event) {
        drawing = false;
        event.preventDefault();
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', moveDraw);
    canvas.addEventListener('mouseup', endDraw);
    canvas.addEventListener('mouseleave', endDraw);
    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', moveDraw, { passive: false });
    canvas.addEventListener('touchend', endDraw, { passive: false });

    btnLimpar.addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
        hasStroke = false;
        inputDataUrl.value = '';
    });

    function updateTipo() {
        const tipo = document.querySelector('input[name="assinatura_tipo"]:checked').value;
        blocoDesenho.classList.toggle('d-none', tipo === 'upload');
        blocoUpload.classList.toggle('d-none', tipo !== 'upload');
        blocoDigitada.classList.toggle('d-none', tipo !== 'digitada');
    }

    tipoRadios.forEach(radio => radio.addEventListener('change', updateTipo));
    updateTipo();

    function gerarAssinaturaDigitada() {
        const nome = (nomeDigitado.value || '').trim();
        if (!nome) return false;

        ctx.clearRect(0, 0, canvas.clientWidth, canvas.clientHeight);
        ctx.font = '42px cursive';
        ctx.fillStyle = '#111';
        ctx.fillText(nome, 20, 120);
        hasStroke = true;
        return true;
    }

    form.addEventListener('submit', function(event) {
        const tipo = document.querySelector('input[name="assinatura_tipo"]:checked').value;

        if (tipo === 'upload') {
            if (!assinaturaUpload.files || assinaturaUpload.files.length === 0) {
                event.preventDefault();
                alert('Selecione um arquivo de assinatura.');
            }
            return;
        }

        if (tipo === 'digitada') {
            const ok = gerarAssinaturaDigitada();
            if (!ok) {
                event.preventDefault();
                alert('Digite seu nome para gerar a assinatura.');
                return;
            }
        }

        if (!hasStroke) {
            event.preventDefault();
            alert('Desenhe ou gere sua assinatura antes de confirmar.');
            return;
        }

        inputDataUrl.value = canvas.toDataURL('image/png');
    });
})();
</script>
</body>
</html>
