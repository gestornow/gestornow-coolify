<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo de Locação {{ $locacao->numero_contrato }}</title>
    <style>
        @page { margin: 14px 16px; }
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; margin: 0; padding: 0; }
        .page { width: 100%; max-width: 100%; margin: 0 auto; overflow: hidden; }
        table { max-width: 100%; }
        img { max-width: 100%; height: auto; }
        .faixa-topo { height: 7px; background: {{ $corPrimariaDocumento ?? '#1f97ea' }}; border-radius: 6px 6px 0 0; margin-bottom: 6px; }
        .header { border: 1px solid #d0d7e2; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; vertical-align: middle; }
        .logo { max-height: 54px; max-width: 180px; }
        .subtitle { text-align: center; font-size: 16px; font-weight: 700; color: #0f3f7d; }
        .document-id { text-align: right; font-size: 12px; font-weight: 700; color: #0f3f7d; background: #e8f0fc; border: 1px solid #cdddf5; border-radius: 6px; padding: 6px 8px; }
        .bloco { border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fbff; padding: 10px; margin-top: 10px; line-height: 1.55; }
        .meta { margin-top: 8px; font-size: 10px; }
        .resumo-texto { margin-bottom: 8px; line-height: 1.6; }
        .split { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .split td { border: none; padding: 2px 0; vertical-align: top; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .grid th, .grid td { border: 1px solid #334155; padding: 6px; word-wrap: break-word; overflow-wrap: anywhere; }
        .grid th { background: #334155; color: #fff; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .clausulas-wrap { margin-top: 10px; }
        .clausula-box { margin-top: 8px; border: 1px solid #bfd3f2; border-radius: 4px; overflow: hidden; }
        .clausula-head { background: #eaf1fb; color: #0f3f7d; font-size: 10px; font-weight: 700; padding: 6px 8px; text-transform: uppercase; }
        .clausula-body { background: #fff; padding: 8px; font-size: 9.5px; line-height: 1.45; word-break: break-word; overflow-wrap: anywhere; }
        .assinaturas { width: 100%; margin-top: 22px; border-collapse: collapse; }
        .assinaturas td { width: 50%; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; text-align: center; padding: 10px 8px; vertical-align: bottom; }
        .assinatura-img { max-height: 50px; max-width: 180px; margin-bottom: 6px; }
        .linha { margin-top: 28px; border-top: 1px solid #111; padding-top: 6px; font-weight: 700; }
        .footer-note { margin-top: 12px; font-size: 9px; text-align: center; color: #64748b; }
        .header-table, .split, .grid, .assinaturas { table-layout: fixed; }
        .header, .bloco, .clausula-box, .assinaturas { page-break-inside: avoid; }
    </style>
</head>
<body>
    @php
        use Carbon\Carbon;

        $cfg = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logo = $cfg['logo_url'] ?? null;
        $logoSrc = null;
        if ($logo) {
            $logoPath = parse_url($logo, PHP_URL_PATH);
            $logoFileLocal = $logoPath ? public_path(ltrim($logoPath, '/')) : null;
            $logoSrc = ($logoFileLocal && file_exists($logoFileLocal)) ? $logoFileLocal : $logo;
        }

        $clienteNome = $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-';
        $inicioPeriodo = $locacao->data_inicio ? Carbon::parse($locacao->data_inicio) : null;
        if ($inicioPeriodo && !empty($locacao->hora_inicio)) {
            $inicioPeriodo->setTimeFromTimeString((string) $locacao->hora_inicio);
        }

        $fimPeriodo = $locacao->data_fim ? Carbon::parse($locacao->data_fim) : null;
        if ($fimPeriodo && !empty($locacao->hora_fim)) {
            $fimPeriodo->setTimeFromTimeString((string) $locacao->hora_fim);
        }
        $ehPorHora = (bool) ($locacao->locacao_por_hora ?? false);
        if (!$ehPorHora && $inicioPeriodo && $fimPeriodo) {
            $ehPorHora = $inicioPeriodo->format('Y-m-d') === $fimPeriodo->format('Y-m-d');
        }
        $qtdPeriodo = $ehPorHora
            ? max(1, (int) ceil(($inicioPeriodo && $fimPeriodo && $fimPeriodo->gte($inicioPeriodo)) ? ($inicioPeriodo->diffInMinutes($fimPeriodo) / 60) : 1))
            : max(1, (int) ($locacao->quantidade_dias ?? (($inicioPeriodo && $fimPeriodo) ? $inicioPeriodo->copy()->startOfDay()->diffInDays($fimPeriodo->copy()->startOfDay()) + 1 : 1)));
        $labelPeriodo = $ehPorHora ? 'hora(s)' : 'dia(s)';

        $valorProdutos = 0.0;
        foreach ($locacao->produtos as $item) {
            $qtd = max(1, (int) ($item->quantidade ?? 1));
            $unit = (float) ($item->preco_unitario ?? 0);
            $totalItem = (bool) ($item->valor_fechado ?? false)
                ? (float) ($item->preco_total ?? 0)
                : ($unit * $qtd * $qtdPeriodo);
            $valorProdutos += $totalItem;
        }
        foreach ($locacao->produtosTerceiros as $item) {
            $qtd = max(1, (int) ($item->quantidade ?? 1));
            $unit = (float) ($item->preco_unitario ?? 0);
            $totalItem = (bool) ($item->valor_fechado ?? false)
                ? (float) ($item->valor_total ?? 0)
                : ($unit * $qtd * $qtdPeriodo);
            $valorProdutos += $totalItem;
        }

        $valorServicos = (float) ($locacao->servicos->sum(function ($item) {
            $qtd = max(1, (int) ($item->quantidade ?? 1));
            $unit = (float) ($item->preco_unitario ?? 0);
            return (float) ($item->valor_total ?? ($unit * $qtd));
        }) ?? 0);
        $valorDespesas = (float) ($locacao->despesas->sum('valor') ?? 0);
        $freteEntrega = (float) ($locacao->valor_frete_entrega ?? $locacao->valor_acrescimo ?? 0);
        $freteRetirada = (float) ($locacao->valor_frete_retirada ?? 0);
        $freteTotal = $freteEntrega + $freteRetirada;
        $desconto = (float) ($locacao->valor_desconto ?? 0);
        $valorFinal = (float) ($locacao->valor_final ?? (($valorProdutos + $valorServicos + $freteTotal) - $desconto));
        $cidade = trim((string) ($locacao->cidade ?: ($locacao->cliente->cidade ?? ($empresa->cidade ?? ''))));
        $uf = trim((string) ($locacao->estado ?: ($locacao->cliente->uf ?? ($empresa->uf ?? ''))));
        $cidadeUf = trim($cidade . ($uf !== '' ? ' - ' . $uf : ''));
        $horaInicioFmt = substr((string) ($locacao->hora_inicio ?? '00:00'), 0, 5);
        $horaFimFmt = substr((string) ($locacao->hora_fim ?? '23:59'), 0, 5);
        $nomeLocadora = $empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome_empresa ?? 'Locadora';
        $docLocadora = $empresa->cnpj ?? '-';
        $docCliente = $locacao->cliente->cpf_cnpj ?? '-';
        $dataExtenso = now()->locale('pt_BR')->isoFormat('DD [de] MMMM [de] YYYY');
    @endphp

    <div class="page">
        <div class="faixa-topo"></div>
        <div class="header">
            <table class="header-table">
                <tr>
                    <td style="width:28%;">
                        @if($logoSrc)
                            <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                        @endif
                    </td>
                    <td style="width:48%;">
                        <div class="subtitle">RECIBO DE LOCAÇÃO</div>
                    </td>
                    <td style="width:24%;" class="document-id">
                        Contrato Nº:<br>{{ $locacao->numero_contrato }}
                    </td>
                </tr>
            </table>
            <div class="meta">Data de Emissão: {{ now()->format('d/m/Y H:i') }}</div>
        </div>

        <div class="bloco">
            <div class="resumo-texto">
                Recebemos de <strong>{{ $clienteNome }}</strong>, inscrito(a) no CPF/CNPJ <strong>{{ $docCliente }}</strong>, a importância
                de <strong>R$ {{ number_format($valorFinal, 2, ',', '.') }}</strong>, referente à locação dos equipamentos do contrato
                <strong>{{ $locacao->numero_contrato }}</strong>.
                Período: <strong>{{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $horaInicioFmt }}</strong>
                até <strong>{{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $horaFimFmt }}</strong>
                ({{ $qtdPeriodo }} {{ $labelPeriodo }}).
            </div>

            <table class="split">
                <tr>
                    <td style="width:50%;"><strong>Cliente:</strong> {{ $clienteNome }}</td>
                    <td style="width:50%;"><strong>Contrato Nº:</strong> {{ $locacao->numero_contrato }}</td>
                </tr>
                <tr>
                    <td><strong>Endereço:</strong> {{ $locacao->endereco_entrega ?? $locacao->local_entrega ?? '-' }}</td>
                    <td><strong>Telefone:</strong> {{ $locacao->cliente->telefone ?? $locacao->cliente->celular ?? '-' }}</td>
                </tr>
            </table>
        </div>

        <table class="grid">
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="text-center" style="width: 9%;">Qtd</th>
                    <th class="text-center" style="width: 13%;">Período</th>
                    <th class="text-right" style="width: 17%;">Valor Unit.</th>
                    <th class="text-right" style="width: 17%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locacao->produtos as $item)
                    @php
                        $qtd = max(1, (int) ($item->quantidade ?? 1));
                        $unit = (float) ($item->preco_unitario ?? 0);
                        $totalItem = (bool) ($item->valor_fechado ?? false)
                            ? (float) ($item->preco_total ?? 0)
                            : ($unit * $qtd * $qtdPeriodo);
                    @endphp
                    <tr>
                        <td>{{ $item->produto->nome ?? 'Item' }}</td>
                        <td class="text-center">{{ $qtd }}</td>
                        <td class="text-center">{{ $qtdPeriodo }} {{ $labelPeriodo }}</td>
                        <td class="text-right">R$ {{ number_format($unit, 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($totalItem, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">Sem itens.</td></tr>
                @endforelse
                @foreach($locacao->produtosTerceiros as $item)
                    @php
                        $qtd = max(1, (int) ($item->quantidade ?? 1));
                        $unit = (float) ($item->preco_unitario ?? 0);
                        $totalItem = (bool) ($item->valor_fechado ?? false)
                            ? (float) ($item->valor_total ?? 0)
                            : ($unit * $qtd * $qtdPeriodo);
                    @endphp
                    <tr>
                        <td>{{ $item->nome_produto ?? 'Produto terceiro' }}</td>
                        <td class="text-center">{{ $qtd }}</td>
                        <td class="text-center">{{ $qtdPeriodo }} {{ $labelPeriodo }}</td>
                        <td class="text-right">R$ {{ number_format($unit, 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($totalItem, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                @foreach($locacao->servicos as $item)
                    @php
                        $qtd = max(1, (int) ($item->quantidade ?? 1));
                        $unit = (float) ($item->preco_unitario ?? 0);
                        $totalItem = (float) ($item->valor_total ?? ($unit * $qtd));
                    @endphp
                    <tr>
                        <td>{{ $item->descricao ?? 'Serviço' }}</td>
                        <td class="text-center">{{ $qtd }}</td>
                        <td class="text-center">1 serviço</td>
                        <td class="text-right">R$ {{ number_format($unit, 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($totalItem, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" class="text-right"><strong>Subtotal Produtos</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($valorProdutos, 2, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><strong>Subtotal Serviços</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($valorServicos, 2, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><strong>Frete Entrega</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($freteEntrega, 2, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><strong>Frete Retirada</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($freteRetirada, 2, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><strong>Frete Total</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($freteTotal, 2, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><strong>Desconto</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($desconto, 2, ',', '.') }}</strong></td>
                </tr>
                <tr>
                    <td colspan="4" class="text-right"><strong>Total</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($valorFinal, 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>

        <div class="bloco">
            Para todos os fins legais, declara-se quitado o valor acima discriminado, referente à locação dos itens e serviços vinculados ao contrato informado.
            <br><br>
            {{ $cidadeUf ?: 'Limeira - SP' }}, {{ $dataExtenso }}.
        </div>

        <div class="bloco">
            <strong>Observações:</strong> {{ $locacao->observacoes_recibo ?? '-' }}
        </div>

        <div class="clausulas-wrap">
            <div class="clausula-box">
                <div class="clausula-head">PERÍODO</div>
                <div class="clausula-body">
                    {{ optional($locacao->data_inicio)->format('d/m/Y') }} às {{ $horaInicioFmt }} horas até {{ optional($locacao->data_fim)->format('d/m/Y') }} às {{ $horaFimFmt }} horas,
                    totalizando {{ $qtdPeriodo }} {{ $labelPeriodo }}, totalizando R$ {{ number_format($valorFinal, 2, ',', '.') }}.
                </div>
            </div>
            <div class="clausula-box">
                <div class="clausula-head">FORMA E PRAZO DE PAGAMENTO</div>
                <div class="clausula-body">
                    Forma de Pagamento: {{ $locacao->forma_pagamento ?? 'Conforme combinado' }} /
                    Prazo de Pagamento: {{ $locacao->prazo_pagamento ?? 'Conforme vencimento pactuado' }}.
                </div>
            </div>
        </div>

        <table class="assinaturas">
            <tr>
                <td>
                    @if(!empty($assinaturaLocadoraPdfSrc ?? null))
                        <img class="assinatura-img" src="{{ $assinaturaLocadoraPdfSrc }}" alt="Assinatura Locadora">
                    @endif
                    <div class="linha">
                        {{ $nomeLocadora }}<br>
                        CNPJ: {{ $docLocadora }}
                    </div>
                </td>
                <td>
                    @if(!empty($assinaturaClientePdfSrc ?? null))
                        <img class="assinatura-img" src="{{ $assinaturaClientePdfSrc }}" alt="Assinatura Cliente">
                    @endif
                    <div class="linha">
                        {{ $clienteNome }}<br>
                        CPF/CNPJ: {{ $docCliente }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">Emitido em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
    </div>
</body>
</html>
