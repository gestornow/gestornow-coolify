<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Contrato {{ $locacao->numero_contrato }}</title>
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
        .intro-box {
            border-left: 4px solid #2563eb;
            background: #f8fbff;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        p { margin: 8px 0; text-align: justify; line-height: 1.45; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #2f3d55; padding: 6px; font-size: 10.5px; }
        th { background: #334155; color: #fff; font-weight: 700; }
        .linha-zebra { background: #f8fafc; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .section-title {
            margin-top: 14px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            background: #eff6ff;
            border: 1px solid #cbdffb;
            color: #1e3a8a;
            padding: 6px 8px;
            border-radius: 6px;
        }
        .clausula { margin-top: 8px; }
        .assinaturas { width: 100%; margin-top: 34px; }
        .assinaturas td { width: 50%; text-align: center; vertical-align: bottom; border: none; }
        .linha { margin-top: 46px; border-top: 1px solid #111; padding-top: 6px; font-size: 10px; }
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
    $totalProdutos = (float) ($locacao->produtos->sum('preco_total') ?? 0) + (float) ($locacao->produtosTerceiros->sum('valor_total') ?? 0);
    $valorFinal = (float) ($locacao->valor_final ?? $locacao->valor_total ?? 0);
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
                <div class="subtitle">Contrato de Locação de Bens Móveis</div>
            </td>
            <td style="width:24%;" class="contract-no">
                Contrato Nº:<br>{{ $locacao->numero_contrato }}
            </td>
        </tr>
    </table>
</div>

<div class="intro-box">
<p style="margin:0;">
    Pelo presente instrumento de locação de bens móveis, de um lado: <strong>{{ $empresaNome }}</strong>
    (CNPJ: <strong>{{ $empresa->cnpj }}</strong>), e de outro: <strong>{{ $clienteNome }}</strong>
    (CPF/CNPJ: <strong>{{ $clienteDoc }}</strong>), as partes ajustam a locação dos materiais abaixo descritos.
</p>
</div>

<table>
    <thead>
        <tr>
            <th>Descrição</th>
            <th class="text-center" style="width:80px;">Qtd</th>
            <th class="text-right" style="width:110px;">Preço</th>
            <th class="text-right" style="width:120px;">Sub Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($locacao->produtos as $item)
            <tr class="{{ $loop->even ? 'linha-zebra' : '' }}">
                <td>{{ $item->produto->nome ?? 'Item' }}</td>
                <td class="text-center">{{ $item->quantidade ?? 1 }}</td>
                <td class="text-right">R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format((float) ($item->preco_total ?? 0), 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="text-center">Sem itens cadastrados.</td>
            </tr>
        @endforelse
        @foreach($locacao->produtosTerceiros as $item)
            <tr class="{{ $loop->even ? 'linha-zebra' : '' }}">
                <td>{{ $item->nome_produto }}</td>
                <td class="text-center">{{ $item->quantidade ?? 1 }}</td>
                <td class="text-right">R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format((float) ($item->valor_total ?? 0), 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="3" class="text-right"><strong>Total Locado:</strong></td>
            <td class="text-right"><strong>R$ {{ number_format($totalProdutos, 2, ',', '.') }}</strong></td>
        </tr>
    </tbody>
</table>

<p>
    <strong>PERÍODO:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} às {{ $locacao->hora_inicio ?? '00:00' }} horas
    até {{ optional($locacao->data_fim)->format('d/m/Y') }} às {{ $locacao->hora_fim ?? '23:59' }} horas,
    totalizando {{ $locacao->quantidade_dias ?? '-' }} dias e valor final de <strong>R$ {{ number_format($valorFinal, 2, ',', '.') }}</strong>.
</p>

<div class="section-title">CLÁUSULA I - DO PRAZO CONTRATUAL</div>
<p class="clausula">O prazo de vigência do presente contrato corresponde ao período informado, podendo as partes ajustar prorrogação mediante comum acordo.</p>

<div class="section-title">CLÁUSULA II - DA CONSERVAÇÃO DOS BENS</div>
<p class="clausula">A parte contratante se responsabiliza pela guarda e conservação dos bens durante toda a locação, comprometendo-se a devolvê-los em condições normais de uso.</p>

<div class="section-title">CLÁUSULA III - AVARIAS, EXTRAVIO E INADIMPLEMENTO</div>
<p class="clausula">Havendo avaria, perda, extravio ou atraso na devolução, poderão ser aplicadas cobranças adicionais de acordo com os valores de reposição e regras comerciais da empresa contratada.</p>

<table class="assinaturas">
    <tr>
        <td>
            <div class="linha">
                {{ $empresaNome }}<br>
                CNPJ: {{ $empresa->cnpj ?? '-' }}
            </div>
        </td>
        <td>
            <div class="linha">
                {{ $clienteNome }}<br>
                CPF/CNPJ: {{ $clienteDoc }}
            </div>
        </td>
    </tr>
</table>

<div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>

</body>
</html>
