<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informações do Produto - {{ $produto->nome }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 18px;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header h2 {
            margin: 4px 0;
            font-size: 13px;
            text-transform: uppercase;
        }
        .meta {
            margin-top: 5px;
            font-size: 9px;
            color: #4b5563;
        }
        .info {
            margin: 10px 0;
            font-size: 9px;
            line-height: 1.5;
        }
        table.cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 10px 0 14px;
        }
        .cards td {
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 9px;
            border-radius: 4px;
        }
        .bg-green { background: #198754; }
        .bg-red { background: #dc3545; }
        .bg-blue { background: #0d6efd; }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .grid th {
            background: #374151;
            color: #fff;
            font-size: 9px;
            padding: 6px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        .grid td {
            border: 1px solid #d1d5db;
            padding: 5px;
            font-size: 9px;
        }
        .grid tr:nth-child(even) {
            background: #f9fafb;
        }
        .text-right { text-align: right; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin-top: 14px;
            margin-bottom: 4px;
            color: #111827;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            background: #fff;
        }
    </style>
</head>
<body>
    @php
        $receita = (float) ($infoFinanceiraProduto['receita'] ?? 0);
        $gasto = (float) ($infoFinanceiraProduto['gasto_manutencao'] ?? 0);
        $lucro = (float) ($infoFinanceiraProduto['lucro'] ?? 0);

        $config = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logo = $config['logo_url'] ?? null;
        $logoSrc = null;
        if ($logo) {
            $logoPath = parse_url($logo, PHP_URL_PATH);
            $logoFileLocal = $logoPath ? public_path(ltrim($logoPath, '/')) : null;
            $logoSrc = ($logoFileLocal && file_exists($logoFileLocal)) ? $logoFileLocal : $logo;
        }

        $nomeEmpresa = $empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome_empresa ?? 'GestorNow';
    @endphp

    <div class="header">
        @if($logoSrc)
            <div style="margin-bottom: 6px;">
                <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 58px; max-width: 210px;">
            </div>
        @endif
        <h1>{{ $nomeEmpresa }}</h1>
        <h2>Informações do Produto</h2>
        <div class="meta">
            Emissão: {{ ($dataGeracao ?? now())->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="info">
        <strong>Produto:</strong> {{ $produto->nome }}<br>
        <strong>Código:</strong> {{ $produto->codigo ?? '-' }}<br>
        <strong>Itens de locação contabilizados:</strong> {{ (int) ($infoFinanceiraProduto['qtd_locacoes_rentaveis'] ?? 0) }}<br>
        <strong>Manutenções contabilizadas:</strong> {{ (int) ($infoFinanceiraProduto['qtd_manutencoes'] ?? 0) }}
    </div>

    <table class="cards">
        <tr>
            <td class="bg-green">Receita em Locações<br>R$ {{ number_format($receita, 2, ',', '.') }}</td>
            <td class="bg-red">Gasto com Manutenções<br>R$ {{ number_format($gasto, 2, ',', '.') }}</td>
            <td class="bg-blue">Lucratividade do Produto<br>R$ {{ number_format($lucro, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Rentabilidade por Patrimônio</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Patrimônio</th>
                <th>Status</th>
                <th class="text-right">Receita</th>
                <th class="text-right">Gasto Manutenção</th>
                <th class="text-right">Lucro</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($infoPatrimonios ?? []) as $item)
                <tr>
                    <td>{{ $item['numero_serie'] ?? '-' }}</td>
                    <td>{{ $item['status_locacao'] ?? '-' }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item['receita'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item['gasto_manutencao'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item['lucro'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center;">Nenhum patrimônio para cálculo de rentabilidade.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        {{ $nomeEmpresa }} • Relatório de Informações do Produto
    </div>
</body>
</html>
