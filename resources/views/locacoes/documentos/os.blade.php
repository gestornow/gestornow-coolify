<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>OS {{ $locacao->numero_contrato }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; }
        .head { border: 1px solid #d0d7e2; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fafc; }
        .title { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: #0f3f7d; }
        .tag { margin-bottom: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; background: #eff6ff; color: #1e3a8a; border: 1px solid #cbdffb; border-radius: 6px; padding: 6px 8px; }
        .box { border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fbff; padding: 8px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #334155; padding: 6px; }
        th { background: #334155; color: #fff; }
        .linha-zebra { background: #f8fafc; }
        .footer-note { margin-top: 12px; font-size: 9px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    @php
        $cfg = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logo = $cfg['logo_url'] ?? null;
        $logoSrc = null;
        if ($logo) {
            $logoPath = parse_url($logo, PHP_URL_PATH);
            $logoFileLocal = $logoPath ? public_path(ltrim($logoPath, '/')) : null;
            $logoSrc = ($logoFileLocal && file_exists($logoFileLocal)) ? $logoFileLocal : $logo;
        }
    @endphp
    <div class="head">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 54px; max-width: 180px; margin-bottom: 8px;">
        @endif
        <div class="title">Ordem de Serviço</div>
        <div class="tag">Execução operacional de locação</div>
    </div>

    <div class="box">
        <strong>Contrato:</strong> {{ $locacao->numero_contrato }}<br>
        <strong>Cliente:</strong> {{ $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-' }}<br>
        <strong>Período:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ?? '' }} até {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $locacao->hora_fim ?? '' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Descrição</th>
                <th style="width:70px;">Qtd</th>
                <th style="width:120px;">Valor Unit.</th>
                <th style="width:120px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($locacao->servicos as $servico)
                <tr class="{{ $loop->even ? 'linha-zebra' : '' }}">
                    <td>{{ $servico->descricao }}</td>
                    <td>{{ $servico->quantidade ?? 1 }}</td>
                    <td>R$ {{ number_format((float) ($servico->preco_unitario ?? 0), 2, ',', '.') }}</td>
                    <td>R$ {{ number_format((float) ($servico->valor_total ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Nenhum serviço cadastrado.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
</body>
</html>
