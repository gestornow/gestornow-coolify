<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Orçamento {{ $locacao->numero_contrato }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; }
        .header { border: 1px solid #d0d7e2; border-radius: 8px; padding: 10px; margin-bottom: 12px; background: #f8fbff; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: middle; border: none; }
        .logo { max-height: 58px; max-width: 180px; }
        .title { text-align: center; font-size: 16px; font-weight: 700; color: #0f3f7d; }
        .budget-no { text-align: right; font-size: 12px; font-weight: 700; color: #0f3f7d; background: #eef6ff; border: 1px solid #cdddf5; border-radius: 6px; padding: 6px 8px; }
        .box { border-left: 4px solid #2563eb; background: #f8fbff; padding: 8px 10px; margin-bottom: 10px; }
        .box-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .box-table td { border: none; padding: 2px 0; font-size: 10.5px; }
        .section-title { margin-top: 14px; font-weight: 700; font-size: 11px; text-transform: uppercase; background: #eff6ff; border: 1px solid #cbdffb; color: #1e3a8a; padding: 6px 8px; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #2f3d55; padding: 6px; font-size: 10.5px; }
        th { background: #334155; color: #fff; font-weight: 700; }
        .zebra { background: #f8fafc; }
        .sala-row td { background: #eaf2ff; color: #1e3a8a; font-weight: 700; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .foto-item { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; display: block; margin: 0 auto; }
        .totais { margin-top: 10px; }
        .totais td { border: 1px solid #cbd5e1; }
        .totais .label { background: #f8fafc; font-weight: 700; }
        .totais .destaque { background: #eef6ff; font-weight: 700; color: #1e3a8a; }
        .texto-livre { margin-top: 8px; border: 1px solid #dbe4f0; border-radius: 6px; background: #fff; padding: 10px; line-height: 1.55; }
        .clausulas-box { margin-top: 8px; border: 1px solid #dbe4f0; border-radius: 6px; background: #fff; padding: 10px; }
        .clausulas-box .clausula-box { margin-bottom: 8px; border: 1px solid #dbe4f0; border-radius: 5px; overflow: hidden; }
        .clausulas-box .clausula-box:last-child { margin-bottom: 0; }
        .clausulas-box .clausula-head { background: #eaf2ff; color: #1e3a8a; font-size: 10px; font-weight: 700; padding: 6px 8px; text-transform: uppercase; }
        .clausulas-box .clausula-body { padding: 8px; font-size: 10px; line-height: 1.5; color: #1f2937; }
        .assinaturas { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .assinaturas td { width: 50%; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; text-align: center; padding: 10px 8px; vertical-align: bottom; }
        .assinatura-img { max-height: 50px; max-width: 180px; margin-bottom: 6px; }
        .linha { margin-top: 26px; border-top: 1px solid #111; padding-top: 6px; font-weight: 700; font-size: 10px; }
        .footer-note { margin-top: 12px; font-size: 9px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
@php
    $logoSrc = $logoEmpresaPdfSrc ?? null;

    $clienteNome = $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-';
    $clienteDoc = $locacao->cliente->cpf_cnpj ?? '-';
    $empresaNome = $empresa->razao_social ?? $empresa->nome_empresa ?? '-';
    $empresaDocumento = $empresa->cnpj ?? '-';

    $inicioPeriodo = $locacao->data_inicio ? \Carbon\Carbon::parse($locacao->data_inicio) : null;
    if ($inicioPeriodo && !empty($locacao->hora_inicio)) {
        $inicioPeriodo->setTimeFromTimeString((string) $locacao->hora_inicio);
    }
    $fimPeriodo = $locacao->data_fim ? \Carbon\Carbon::parse($locacao->data_fim) : null;
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

    $apiFilesBase = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com')), '/');
    $startsWith = function (string $texto, string $prefixo): bool {
        return strpos($texto, $prefixo) === 0;
    };
    $resolverFotoProduto = function ($produto) use ($apiFilesBase, $locacao, $startsWith) {
        if (!is_object($produto)) {
            return null;
        }

        $fotoUrl = trim((string) ($produto->foto_url ?? ''));
        $fotoFilename = trim((string) ($produto->foto_filename ?? ''));

        if ($fotoFilename !== '') {
            return $apiFilesBase . '/uploads/produtos/imagens/' . (int) ($locacao->id_empresa ?? 0) . '/' . ltrim($fotoFilename, '/');
        }

        if ($fotoUrl !== '') {
            if ($startsWith($fotoUrl, 'http://') || $startsWith($fotoUrl, 'https://')) {
                return $fotoUrl;
            }

            $path = '/' . ltrim($fotoUrl, '/');
            if ($startsWith($path, '/api/produtos/imagens/')) {
                $path = '/uploads/produtos/imagens/' . ltrim(substr($path, strlen('/api/produtos/imagens/')), '/');
            } elseif ($startsWith($path, '/produtos/imagens/')) {
                $path = '/uploads/produtos/imagens/' . ltrim(substr($path, strlen('/produtos/imagens/')), '/');
            } elseif (!$startsWith($path, '/uploads/produtos/imagens/')) {
                $path = '/uploads/produtos/imagens/' . (int) ($locacao->id_empresa ?? 0) . '/' . ltrim($path, '/');
            }

            return $apiFilesBase . $path;
        }

        return null;
    };

    $linhas = collect();
    $totalProdutosProprios = 0.0;
    foreach ($locacao->produtos as $item) {
        $qtd = max(1, (int) ($item->quantidade ?? 1));
        $unit = (float) ($item->preco_unitario ?? 0);
        $subtotal = (bool) ($item->valor_fechado ?? false)
            ? (float) ($item->preco_total ?? 0)
            : ($unit * $qtd * $qtdPeriodo);
        $totalProdutosProprios += $subtotal;
        $linhas->push([
            'sala_id' => (int) ($item->id_sala ?? 0),
            'descricao' => $item->produto->nome ?? 'Item',
            'qtd' => $qtd,
            'periodo' => $qtdPeriodo,
            'unit' => $unit,
            'subtotal' => $subtotal,
            'foto' => $resolverFotoProduto($item->produto ?? null),
        ]);
    }

    $totalProdutosTerceiros = 0.0;
    foreach ($locacao->produtosTerceiros as $item) {
        $qtd = max(1, (int) ($item->quantidade ?? 1));
        $unit = (float) ($item->preco_unitario ?? 0);
        $subtotal = (bool) ($item->valor_fechado ?? false)
            ? (float) ($item->valor_total ?? 0)
            : ($unit * $qtd * $qtdPeriodo);
        $totalProdutosTerceiros += $subtotal;
        $linhas->push([
            'sala_id' => (int) ($item->id_sala ?? 0),
            'descricao' => $item->nome_produto ?? 'Produto de Terceiro',
            'qtd' => $qtd,
            'periodo' => $qtdPeriodo,
            'unit' => $unit,
            'subtotal' => $subtotal,
            'foto' => null,
        ]);
    }

    $totalServicos = 0.0;
    foreach ($locacao->servicos as $servico) {
        $idSalaServico = (int) ($servico->id_sala ?? 0);
        if ($idSalaServico <= 0 && !empty($servico->observacoes) && preg_match('/\[GN_META\](\{.*\})/s', (string) $servico->observacoes, $metaMatch)) {
            $metaServico = json_decode($metaMatch[1], true);
            if (is_array($metaServico) && !empty($metaServico['id_sala'])) {
                $idSalaServico = (int) $metaServico['id_sala'];
            }
        }

        $qtd = max(1, (int) ($servico->quantidade ?? 1));
        $unit = (float) ($servico->preco_unitario ?? 0);
        $subtotal = (float) ($servico->valor_total ?? ($unit * $qtd));
        $totalServicos += $subtotal;
        $linhas->push([
            'sala_id' => $idSalaServico,
            'descricao' => $servico->descricao ?? 'Serviço',
            'qtd' => $qtd,
            'periodo' => 1,
            'unit' => $unit,
            'subtotal' => $subtotal,
            'foto' => null,
        ]);
    }

    $freteEntrega = (float) ($locacao->valor_frete_entrega ?? $locacao->valor_acrescimo ?? 0);
    $freteRetirada = (float) ($locacao->valor_frete_retirada ?? 0);
    $freteTotal = $freteEntrega + $freteRetirada;

    $observacoesOrcamento = trim((string) ($locacao->observacoes_orcamento ?? $locacao->observacoes ?? ''));
    $clausulasOrcamentoHtml = null;
    if (!empty($modeloDocumentoOrcamento) && method_exists($modeloDocumentoOrcamento, 'getClausulasHtmlRenderizadas')) {
        $clausulasOrcamentoHtml = trim((string) $modeloDocumentoOrcamento->getClausulasHtmlRenderizadas());
    }

    $salasOrdenadas = collect($locacao->salas ?? [])->sortBy(function ($sala) {
        return [
            (int) ($sala->ordem ?? 999999),
            (int) ($sala->id_sala ?? 0),
        ];
    })->values();

    $salasNome = $salasOrdenadas->mapWithKeys(function ($sala) {
        return [(int) ($sala->id_sala ?? 0) => ($sala->nome ?? 'Sala')];
    });
    $salasPosicao = $salasOrdenadas->values()->pluck('id_sala')->flip();

    $linhasAgrupadas = $linhas
        ->sortBy(function ($linha) use ($salasPosicao) {
            return [
                $linha['sala_id'] === 0 ? 999999 : ((int) ($salasPosicao[$linha['sala_id']] ?? 999998)),
                $linha['descricao'],
            ];
        })
        ->groupBy('sala_id');

    $totalFinal = $totalProdutosProprios
        + $totalProdutosTerceiros
        + $totalServicos
        + $freteTotal
        - (float) ($locacao->valor_desconto ?? 0);
@endphp

<div class="header">
    <table class="header-table">
        <tr>
            <td style="width:30%;">
                @if($logoSrc)
                    <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                @endif
            </td>
            <td style="width:45%;"><div class="title">Orçamento de Locação</div></td>
            <td style="width:25%;" class="budget-no">Código: {{ $locacao->numero_contrato }}</td>
        </tr>
    </table>
</div>

<div class="box">
    <table class="box-table">
        <tr>
            <td style="width:50%;"><strong>Locadora:</strong> {{ $empresaNome }}</td>
            <td style="width:50%;"><strong>Cliente:</strong> {{ $clienteNome }}</td>
        </tr>
        <tr>
            <td><strong>Documento:</strong> {{ $clienteDoc }}</td>
            <td><strong>Período:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ substr((string) ($locacao->hora_inicio ?? '00:00'), 0, 5) }} até {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ substr((string) ($locacao->hora_fim ?? '23:59'), 0, 5) }}</td>
        </tr>
        <tr>
            <td><strong>Totalizando:</strong> {{ $qtdPeriodo }} {{ $labelPeriodo }}</td>
            <td><strong>Responsável:</strong> {{ $responsavelContrato ?? ($locacao->responsavel ?? '-') }}</td>
        </tr>
    </table>
</div>

<div class="section-title">Itens do Orçamento</div>
<table>
    <thead>
        <tr>
            @if(!empty($imprimirComFoto))
                <th class="text-center" style="width:48px;">Foto</th>
            @endif
            <th>Descrição</th>
            <th class="text-center" style="width:70px;">Qtd</th>
            <th class="text-center" style="width:90px;">Período</th>
            <th class="text-right" style="width:120px;">Valor Unit.</th>
            <th class="text-right" style="width:130px;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @php $temLinha = false; @endphp
        @foreach($linhasAgrupadas as $idSala => $linhasSala)
            @php
                $temLinha = true;
                $nomeSala = $idSala > 0 ? ($salasNome[$idSala] ?? ('Sala #' . $idSala)) : 'Sem sala';
            @endphp
            <tr class="sala-row">
                <td colspan="{{ !empty($imprimirComFoto) ? 6 : 5 }}">{{ $nomeSala }}</td>
            </tr>
            @foreach($linhasSala as $linha)
                <tr class="{{ $loop->even ? 'zebra' : '' }}">
                    @if(!empty($imprimirComFoto))
                        <td class="text-center">
                            @if(!empty($linha['foto']))
                                <img src="{{ $linha['foto'] }}" alt="Foto" class="foto-item">
                            @else
                                -
                            @endif
                        </td>
                    @endif
                    <td>{{ $linha['descricao'] }}</td>
                    <td class="text-center">{{ $linha['qtd'] }}</td>
                    <td class="text-center">{{ $linha['periodo'] }} {{ $linha['periodo'] > 1 ? $labelPeriodo : $labelPeriodo }}</td>
                    <td class="text-right">R$ {{ number_format((float) $linha['unit'], 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format((float) $linha['subtotal'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        @endforeach
        @if(!$temLinha)
            <tr><td colspan="{{ !empty($imprimirComFoto) ? 6 : 5 }}" class="text-center">Sem itens cadastrados.</td></tr>
        @endif
    </tbody>
</table>

<table class="totais">
    <tr><td class="label">Produtos</td><td class="text-right">R$ {{ number_format($totalProdutosProprios + $totalProdutosTerceiros, 2, ',', '.') }}</td></tr>
    <tr><td class="label">Serviços</td><td class="text-right">R$ {{ number_format($totalServicos, 2, ',', '.') }}</td></tr>
    <tr><td class="label">Frete Entrega</td><td class="text-right">R$ {{ number_format($freteEntrega, 2, ',', '.') }}</td></tr>
    <tr><td class="label">Frete Retirada</td><td class="text-right">R$ {{ number_format($freteRetirada, 2, ',', '.') }}</td></tr>
    <tr><td class="label">Desconto</td><td class="text-right">- R$ {{ number_format((float) ($locacao->valor_desconto ?? 0), 2, ',', '.') }}</td></tr>
    <tr><td class="destaque">Total Final</td><td class="text-right destaque">R$ {{ number_format(max(0, $totalFinal), 2, ',', '.') }}</td></tr>
</table>

@if($observacoesOrcamento !== '')
    <div class="section-title">Observações do Orçamento</div>
    <div class="texto-livre">{!! nl2br(e($observacoesOrcamento)) !!}</div>
@endif

@if(!empty($clausulasOrcamentoHtml))
    <div class="section-title">Cláusulas</div>
    <div class="clausulas-box">{!! $clausulasOrcamentoHtml !!}</div>
@endif

<table class="assinaturas">
    <tr>
        <td>
            @if(!empty($assinaturaLocadoraPdfSrc ?? null))
                <img class="assinatura-img" src="{{ $assinaturaLocadoraPdfSrc }}" alt="Assinatura Locadora">
            @endif
            <div class="linha">
                {{ $empresaNome }}<br>
                CNPJ: {{ $empresaDocumento }}
            </div>
        </td>
        <td>
            @if(!empty($assinaturaClientePdfSrc ?? null))
                <img class="assinatura-img" src="{{ $assinaturaClientePdfSrc }}" alt="Assinatura Cliente">
            @endif
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
