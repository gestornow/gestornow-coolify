<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Checklist {{ $locacao->numero_contrato }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; }
        .page { max-width: 760px; margin: 0 auto; }
        .faixa-topo { height: 7px; background: {{ $corPrimariaDocumento ?? '#1f97ea' }}; border-radius: 6px 6px 0 0; margin-bottom: 6px; }
        .header { border: 1px solid #d0d7e2; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; vertical-align: middle; }
        .logo { max-height: 54px; max-width: 180px; }
        .subtitle { text-align: center; font-size: 16px; font-weight: 700; color: #0f3f7d; }
        .document-id { text-align: right; font-size: 12px; font-weight: 700; color: #0f3f7d; background: #e8f0fc; border: 1px solid #cdddf5; border-radius: 6px; padding: 6px 8px; }
        .meta { margin-top: 8px; }
        .line-box { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 8px; margin-top: 8px; }
        .split { width: 100%; border-collapse: collapse; }
        .split td { border: none; padding: 2px 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #334155; padding: 5px; }
        th { background: #334155; color: #fff; }
        .linha-zebra { background: #f8fafc; }
        .text-center { text-align: center; }
        .section-title { margin: 12px 0 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #eff6ff; border: 1px solid #cbdffb; color: #1e3a8a; padding: 6px 8px; border-radius: 6px; }
        .assinaturas { width: 100%; margin-top: 14px; border-collapse: separate; border-spacing: 8px 0; }
        .assinaturas td { width: 33%; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; vertical-align: bottom; padding: 8px; background: #fff; }
        .assinatura-img { max-height: 46px; max-width: 160px; margin-bottom: 6px; }
        .linha-assinatura { margin-top: 26px; border-top: 1px solid #111; padding-top: 6px; font-weight: 700; }
        .footer-note { margin-top: 12px; font-size: 9px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    @php
        $logoSrc = $logoEmpresaPdfSrc ?? null;
        $inicio = $locacao->data_inicio ? \Carbon\Carbon::parse($locacao->data_inicio) : null;
        if ($inicio && !empty($locacao->hora_inicio)) {
            $inicio->setTimeFromTimeString((string) $locacao->hora_inicio);
        }

        $fim = $locacao->data_fim ? \Carbon\Carbon::parse($locacao->data_fim) : null;
        if ($fim && !empty($locacao->hora_fim)) {
            $fim->setTimeFromTimeString((string) $locacao->hora_fim);
        }
        $ehPorHora = (bool) ($locacao->locacao_por_hora ?? false);
        if (!$ehPorHora && $inicio && $fim) {
            $ehPorHora = $inicio->format('Y-m-d') === $fim->format('Y-m-d');
        }
        $qtdPeriodo = $ehPorHora
            ? max(1, (int) ceil(($inicio && $fim && $fim->gte($inicio)) ? ($inicio->diffInMinutes($fim) / 60) : 1))
            : max(1, (int) ($locacao->quantidade_dias ?? (($inicio && $fim) ? $inicio->copy()->startOfDay()->diffInDays($fim->copy()->startOfDay()) + 1 : 1)));
        $labelPeriodo = $ehPorHora ? 'hora(s)' : 'dia(s)';
        $vendedor = $responsavelContrato ?? ($locacao->responsavel ?? '-');
        $horaInicioFmt = substr((string) ($locacao->hora_inicio ?? '00:00'), 0, 5);
        $horaFimFmt = substr((string) ($locacao->hora_fim ?? '23:59'), 0, 5);
        $nomeLocadora = $empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome_empresa ?? 'Locadora';
        $docLocadora = $empresa->cnpj ?? '-';
        $docCliente = $locacao->cliente->cpf_cnpj ?? '-';
        $cidade = trim((string) ($locacao->cidade ?: ($locacao->cliente->cidade ?? ($empresa->cidade ?? ''))));
        $uf = trim((string) ($locacao->estado ?: ($locacao->cliente->uf ?? ($empresa->uf ?? ''))));
        $cidadeUf = trim($cidade . ($uf !== '' ? ' - ' . $uf : ''));

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

        $salasNome = collect($locacao->salas ?? [])->mapWithKeys(function ($sala) {
            return [(int) ($sala->id_sala ?? 0) => ($sala->nome ?? 'Sala')];
        });

        $linhasChecklist = collect();
        foreach ($locacao->produtos as $item) {
            $linhasChecklist->push([
                'sala_id' => (int) ($item->id_sala ?? 0),
                'descricao' => $item->produto->nome ?? 'Item',
                'qtd' => (int) ($item->quantidade ?? 1),
                'foto' => $resolverFotoProduto($item->produto ?? null),
            ]);
        }
        foreach ($locacao->produtosTerceiros as $item) {
            $linhasChecklist->push([
                'sala_id' => (int) ($item->id_sala ?? 0),
                'descricao' => $item->nome_produto ?? 'Produto de Terceiro',
                'qtd' => (int) ($item->quantidade ?? 1),
                'foto' => null,
            ]);
        }
        $gruposChecklist = $linhasChecklist->groupBy('sala_id');
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
                    <div class="subtitle">Checklist</div>
                </td>
                <td style="width:24%;" class="document-id">
                    Contrato Nº:<br>{{ $locacao->numero_contrato }}
                </td>
            </tr>
        </table>
    </div>

    <div class="line-box">
        <table class="split">
            <tr>
                <td style="width:50%;"><strong>Nome:</strong> {{ $locacao->cliente->nome ?? $locacao->cliente->razao_social ?? '-' }}</td>
                <td style="width:50%;"><strong>Vendedor:</strong> {{ $vendedor }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Nome do Evento:</strong> {{ $locacao->nome_obra ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Stand:</strong> -</td>
                <td><strong>Lugar de Entrega:</strong> {{ $locacao->local_entrega ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Contato no Local:</strong> {{ $locacao->contato_local ?? $locacao->contato_responsavel ?? '-' }}</td>
                <td><strong>Período:</strong> {{ $qtdPeriodo }} {{ $labelPeriodo }}.</td>
            </tr>
            <tr>
                <td><strong>Data de Entrega:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} - {{ $horaInicioFmt }}</td>
                <td><strong>Data de Devolução:</strong> {{ optional($locacao->data_fim)->format('d/m/Y') }} - {{ $horaFimFmt }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Itens para Conferência</div>

    <table>
        <thead>
            <tr>
                @if(!empty($imprimirComFoto))
                    <th style="width:65px;">Imagem</th>
                @endif
                <th>Descrição</th>
                <th class="text-center" style="width:70px;">Qde</th>
                <th class="text-center" style="width:80px;">{{ $ehPorHora ? 'Horas' : 'Dias' }}</th>
                <th class="text-center" style="width:85px;">Saída</th>
                <th class="text-center" style="width:85px;">Entrega</th>
                <th class="text-center" style="width:95px;">Devolução</th>
            </tr>
        </thead>
        <tbody>
            @php $temLinhasChecklist = false; @endphp
            @foreach($gruposChecklist as $idSala => $linhasSala)
                @php
                    $temLinhasChecklist = true;
                    $nomeSala = $idSala > 0 ? ($salasNome[$idSala] ?? ('Sala #' . $idSala)) : 'Sem sala';
                @endphp
                <tr>
                    <td colspan="{{ !empty($imprimirComFoto) ? 7 : 6 }}"><strong>{{ $nomeSala }}</strong></td>
                </tr>
                @foreach($linhasSala as $linha)
                    <tr class="{{ $loop->even ? 'linha-zebra' : '' }}">
                        @if(!empty($imprimirComFoto))
                            <td class="text-center">
                                @if(!empty($linha['foto']))
                                    <img src="{{ $linha['foto'] }}" alt="Foto" style="width:42px;height:42px;object-fit:cover;border-radius:4px;">
                                @else
                                    -
                                @endif
                            </td>
                        @endif
                        <td>{{ $linha['descricao'] }}</td>
                        <td class="text-center">{{ $linha['qtd'] }}</td>
                        <td class="text-center">{{ $qtdPeriodo }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
            @if(!$temLinhasChecklist)
                <tr><td colspan="{{ !empty($imprimirComFoto) ? 7 : 6 }}" class="text-center">Sem itens.</td></tr>
            @endif
            <tr><td colspan="{{ !empty($imprimirComFoto) ? 7 : 6 }}" style="height:20px;"></td></tr>
            <tr><td colspan="{{ !empty($imprimirComFoto) ? 7 : 6 }}" style="height:20px;"></td></tr>
        </tbody>
    </table>

    <div class="line-box"><strong>Observação:</strong> {{ $locacao->observacoes_checklist ?? '-' }}</div>

    <div class="section-title">Responsáveis</div>
    <table>
        <tbody>
            <tr>
                <td style="height: 34px;"><strong>Responsável do Almoxarifado</strong><br>Nome legível:</td>
                <td><strong>Data:</strong> ____/____/______</td>
                <td><strong>Horário:</strong> ________</td>
            </tr>
            <tr>
                <td style="height: 34px;"><strong>Responsável pela Entrega</strong><br>Nome legível:</td>
                <td><strong>Data:</strong> ____/____/______</td>
                <td><strong>Horário:</strong> ________</td>
            </tr>
            <tr>
                <td style="height: 34px;"><strong>Responsável pelo Recebimento (Cliente)</strong><br>Nome legível:</td>
                <td><strong>Data:</strong> ____/____/______</td>
                <td><strong>RG:</strong> ___________________</td>
            </tr>
            <tr>
                <td style="height: 34px;"><strong>Responsável pela Retirada</strong><br>Nome legível:</td>
                <td><strong>Data:</strong> ____/____/______</td>
                <td><strong>Horário:</strong> ________</td>
            </tr>
            <tr>
                <td style="height: 34px;"><strong>Entrada Almoxarifado</strong><br>Nome legível:</td>
                <td><strong>Data:</strong> ____/____/______</td>
                <td><strong>Horário:</strong> ________</td>
            </tr>
            <tr>
                <td colspan="3" style="height: 56px;"><strong>Observações e ocorrências na retirada</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="assinaturas">
        <tr>
            <td>
                @if(!empty($assinaturaLocadoraPdfSrc ?? null))
                    <img class="assinatura-img" src="{{ $assinaturaLocadoraPdfSrc }}" alt="Assinatura Locadora">
                @endif
                <div class="linha-assinatura">{{ $nomeLocadora }}</div>
                <small>CNPJ: {{ $docLocadora }}</small>
            </td>
            <td>
                @if(!empty($assinaturaClientePdfSrc ?? null))
                    <img class="assinatura-img" src="{{ $assinaturaClientePdfSrc }}" alt="Assinatura Cliente">
                @endif
                <div class="linha-assinatura">{{ $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-' }}</div>
                <small>CPF/CNPJ: {{ $docCliente }}</small>
            </td>
            <td>
                <div class="linha-assinatura">Conferente</div>
            </td>
        </tr>
    </table>

    <div class="line-box"><strong>Cidade/Data:</strong> {{ $cidadeUf ?: 'Limeira - SP' }}, {{ now()->format('d/m/Y') }}</div>

    <div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
    </div>
</body>
</html>
