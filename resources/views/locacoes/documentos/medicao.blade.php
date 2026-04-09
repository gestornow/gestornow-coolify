<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Medição {{ $locacao->numero_contrato }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; }
        .header {
            border: 1px solid #d0d7e2;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 12px;
            background: #f8fbff;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; }
        .logo { max-height: 62px; max-width: 190px; }
        .subtitle { text-align: center; font-size: 16px; font-weight: 700; color: #0f3f7d; }
        .contract-no {
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            color: #0f3f7d;
            background: #e8f0fc;
            border: 1px solid #cdddf5;
            border-radius: 6px;
            padding: 6px 8px;
        }
        .bloco-info {
            border-left: 4px solid #2563eb;
            background: #f8fbff;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .bloco-info-table { width: 100%; border-collapse: collapse; }
        .bloco-info-table td { width: 50%; vertical-align: top; padding-right: 12px; border: none; }
        .bloco-info-inicio { margin-top: 6px; }
        .cards-grid {
            width: 100%;
            margin-top: 10px;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .cards-grid td {
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            padding: 10px;
            vertical-align: top;
            background: #fff;
        }
        .card-titulo {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 4px;
            font-weight: 700;
        }
        .card-valor {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.1;
        }
        .card-valor-sm {
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }
        .card-sub {
            margin-top: 4px;
            font-size: 9px;
            color: #64748b;
        }
        .card-limite {
            border-color: #86efac !important;
            background: #f0fdf4 !important;
        }
        .card-limite .card-titulo { color: #166534; }
        .card-limite .card-valor { color: #166534; }
        .bloco-observacoes {
            margin-top: 10px;
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }
        .local-data {
            margin-top: 18px;
            text-align: center;
            font-size: 10px;
            color: #334155;
        }
        .assinaturas { width: 100%; margin-top: 34px; }
        .assinaturas td { width: 50%; text-align: center; vertical-align: bottom; border: none; }
        .assinatura-card {
            border: 1px solid #d8e0ea;
            border-radius: 8px;
            background: #fff;
            padding: 12px 10px;
            margin: 0 6px;
            height: 140px;
            box-sizing: border-box;
        }
        .assinatura-area {
            height: 72px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .assinatura-img { max-height: 75px; max-width: 220px; margin-bottom: 8px; }
        .linha { margin-top: 8px; border-top: 1px solid #111; padding-top: 6px; font-size: 10px; }
        .footer-note {
            margin-top: 14px;
            font-size: 9px;
            text-align: center;
            color: #64748b;
        }
    </style>
</head>
<body>
@php
    $config = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
    $logo = $config['logo_url'] ?? null;
    $logoSrc = null;
    if ($logo) {
        $logoPath = parse_url($logo, PHP_URL_PATH);
        $logoFileLocal = $logoPath ? public_path(ltrim($logoPath, '/')) : null;
        $logoSrc = ($logoFileLocal && file_exists($logoFileLocal)) ? $logoFileLocal : $logo;
    }

    $clienteNome = $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-';
    $clienteDoc = $locacao->cliente->cpf_cnpj ?? '-';
    $empresaNome = $empresa->razao_social ?? $empresa->nome_empresa ?? '-';
    $empresaCnpj = $empresa->cnpj ?? '-';
    $modeloMedicao = $modeloContratoMedicao ?? null;
    $clausulasModeloMedicao = trim((string) ($modeloMedicao->conteudo_html ?? ''));
    $assinaturaLocadoraUrl = trim((string) ($modeloMedicao->assinatura_locadora_url ?? ''));
    $assinaturaLocadoraSrc = null;
    if ($assinaturaLocadoraUrl !== '') {
        $assinaturaPath = parse_url($assinaturaLocadoraUrl, PHP_URL_PATH);
        $assinaturaArquivoLocal = $assinaturaPath ? public_path(ltrim($assinaturaPath, '/')) : null;
        $assinaturaLocadoraSrc = ($assinaturaArquivoLocal && file_exists($assinaturaArquivoLocal)) ? $assinaturaArquivoLocal : $assinaturaLocadoraUrl;
    }

    $assinaturaClienteSrc = $assinaturaClientePdfSrc ?? null;

    $itensAtivos = collect($locacao->produtos ?? [])->filter(function ($item) {
        return (int) ($item->estoque_status ?? 0) !== 2
            && in_array($item->status_retorno, [null, '', 'pendente'], true);
    });

    $itensAtivosCount = (int) $itensAtivos->count();
    $itensAtivosQuantidade = (int) $itensAtivos->sum(function ($item) {
        return max(1, (int) ($item->quantidade ?? 1));
    });
    $limiteMedicao = (float) ($locacao->valor_limite_medicao ?? 0);
    $meses = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
        7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
    ];
    $diaAtual = now()->day;
    $mesAtual = $meses[now()->month] ?? now()->format('m');
    $anoAtual = now()->year;
    $cidadeContrato = trim((string) ($empresa->cidade ?? ''));
    $cidadeDataExtenso = ($cidadeContrato !== '' ? $cidadeContrato . ', ' : '') . $diaAtual . ' de ' . $mesAtual . ' de ' . $anoAtual;
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:28%;">
                @if($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                @endif
            </td>
            <td style="width:48%;">
                <div class="subtitle">Contrato de Medição</div>
            </td>
            <td style="width:24%;" class="contract-no">
                Contrato Nº:<br>{{ $locacao->numero_contrato }}
            </td>
        </tr>
    </table>
</div>

<div class="bloco-info">
    <table class="bloco-info-table">
        <tr>
            <td>
                <strong>Empresa:</strong> {{ $empresaNome }}<br>
                <strong>CNPJ Empresa:</strong> {{ $empresaCnpj }}
            </td>
            <td>
                <strong>Cliente:</strong> {{ $clienteNome }}<br>
                <strong>CNPJ/CPF Cliente:</strong> {{ $clienteDoc }}
            </td>
        </tr>
    </table>
    <div class="bloco-info-inicio">
        <strong>Início do contrato:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }}
    </div>
</div>

<table class="cards-grid">
    <tr>
        <td class="card-limite" style="width:34%;">
            <div class="card-titulo">Limite Total da Medição</div>
            <div class="card-valor">
                @if($limiteMedicao > 0)
                    R$ {{ number_format($limiteMedicao, 2, ',', '.') }}
                @else
                    Sem limite
                @endif
            </div>
            <div class="card-sub">Valor configurado para controle financeiro do contrato de medição.</div>
        </td>
        <td style="width:33%;">
            <div class="card-titulo">Itens Ativos</div>
            <div class="card-valor">{{ $itensAtivosCount }}</div>
            <div class="card-sub">Registros ativos vinculados ao contrato.</div>
        </td>
        <td style="width:33%;">
            <div class="card-titulo">Quantidade em Uso</div>
            <div class="card-valor">{{ $itensAtivosQuantidade }}</div>
            <div class="card-sub">Soma das quantidades dos itens ativos.</div>
        </td>
    </tr>
    <tr>
        <td>
            <div class="card-titulo">Status do Contrato</div>
            <div class="card-valor-sm">{{ strtoupper((string) ($locacao->status ?? 'medicao')) }}</div>
            <div class="card-sub">Situação atual do contrato de medição.</div>
        </td>
        <td>
            <div class="card-titulo">Data de Emissão</div>
            <div class="card-valor-sm">{{ now()->format('d/m/Y H:i') }}</div>
            <div class="card-sub">Data/hora de geração deste documento.</div>
        </td>
        <td>
            <div class="card-titulo">Empresa</div>
            <div class="card-valor-sm">{{ $empresaNome }}</div>
            <div class="card-sub">Locadora responsável pelo contrato.</div>
        </td>
    </tr>
</table>

<div class="bloco-observacoes">
    <div class="card-titulo">Cláusulas do Contrato</div>
    <div style="font-size: 10px; color: #334155; line-height: 1.4;">
        @if($clausulasModeloMedicao !== '')
            {!! $clausulasModeloMedicao !!}
        @else
            Sem cláusulas cadastradas no modelo de medição.
        @endif
    </div>
</div>

<div class="local-data">{{ $cidadeDataExtenso }}</div>

<table class="assinaturas">
    <tr>
        <td>
            <div class="assinatura-card">
                <div class="assinatura-area">
                    @if($assinaturaLocadoraSrc)
                        <img src="{{ $assinaturaLocadoraSrc }}" alt="Assinatura da empresa" class="assinatura-img">
                    @endif
                </div>
                <div class="linha">
                    {{ $empresaNome }}<br>
                    CNPJ: {{ $empresaCnpj }}
                </div>
            </div>
        </td>
        <td>
            <div class="assinatura-card">
                <div class="assinatura-area">
                    @if(!empty($assinaturaClienteSrc))
                        <img src="{{ $assinaturaClienteSrc }}" alt="Assinatura do cliente" class="assinatura-img">
                    @endif
                </div>
                <div class="linha">
                    {{ $clienteNome }}<br>
                    CNPJ/CPF: {{ $clienteDoc }}
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>

</body>
</html>
