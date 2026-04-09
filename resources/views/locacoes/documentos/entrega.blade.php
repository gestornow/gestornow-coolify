<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Entrega {{ $locacao->numero_contrato }}</title>
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
        .meta { margin-top: 8px; font-size: 10px; }
        .split { width: 100%; border-collapse: collapse; margin-top: 5px; }
        .split td { border: none; padding: 2px 0; vertical-align: top; }
        .mt-6 { margin-top: 6px; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid th, .grid td { border: 1px solid #334155; padding: 5px; }
        .grid th { background: #334155; color: #fff; }
        .section-title { margin: 12px 0 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #eff6ff; border: 1px solid #cbdffb; color: #1e3a8a; padding: 6px 8px; border-radius: 6px; }
        .bloco { border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fbff; padding: 8px; margin-top: 8px; }
        .text-center { text-align: center; }
        .assinaturas { width: 100%; margin-top: 18px; border-collapse: separate; border-spacing: 8px 0; }
        .assinaturas td { width: 33%; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; vertical-align: bottom; padding: 8px; background: #fff; }
        .assinatura-img { max-height: 46px; max-width: 160px; margin-bottom: 6px; }
        .linha { margin-top: 26px; border-top: 1px solid #111; padding-top: 6px; font-weight: 700; }
        .line-box { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 8px; margin-top: 8px; }
        .footer-note { margin-top: 12px; font-size: 9px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
    @php
        $logoSrc = $logoEmpresaPdfSrc ?? null;
        $clienteNome = $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-';
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
        $periodoInicio = optional($locacao->data_inicio)->format('d/m/Y') . ' - ' . ($locacao->hora_inicio ?? '00:00') . ' hrs';
        $periodoFim = optional($locacao->data_fim)->format('d/m/Y') . ' - ' . ($locacao->hora_fim ?? '23:59') . ' hrs';
        $nomeEmpresa = $empresa->nome_fantasia ?? $empresa->razao_social ?? 'ACTLOC';
        $horaInicioFmt = substr((string) ($locacao->hora_inicio ?? '00:00'), 0, 5);
        $horaFimFmt = substr((string) ($locacao->hora_fim ?? '23:59'), 0, 5);
        $cidade = trim((string) ($locacao->cidade ?: ($locacao->cliente->cidade ?? ($empresa->cidade ?? ''))));
        $uf = trim((string) ($locacao->estado ?: ($locacao->cliente->uf ?? ($empresa->uf ?? ''))));
        $cidadeUf = trim($cidade . ($uf !== '' ? ' - ' . $uf : ''));
        $vendedor = $responsavelContrato ?? ($locacao->responsavel ?? '-');
        $docLocadora = $empresa->cnpj ?? '-';
        $docCliente = $locacao->cliente->cpf_cnpj ?? '-';
        $nomeDia = now()->locale('pt_BR')->isoFormat('dddd');
        $nomeData = now()->locale('pt_BR')->isoFormat('DD [de] MMMM [de] YYYY');

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

        $linhasEntrega = collect();
        foreach ($locacao->produtos as $item) {
            $linhasEntrega->push([
                'sala_id' => (int) ($item->id_sala ?? 0),
                'codigo' => $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? '-',
                'descricao' => $item->produto->nome ?? 'Item',
                'qtd' => (int) ($item->quantidade ?? 1),
                'foto' => $resolverFotoProduto($item->produto ?? null),
            ]);
        }
        foreach ($locacao->produtosTerceiros as $item) {
            $linhasEntrega->push([
                'sala_id' => (int) ($item->id_sala ?? 0),
                'codigo' => '-',
                'descricao' => $item->nome_produto ?? 'Produto de Terceiro',
                'qtd' => (int) ($item->quantidade ?? 1),
                'foto' => null,
            ]);
        }
        $gruposEntrega = $linhasEntrega->groupBy('sala_id');
        $totalItens = (int) $linhasEntrega->sum('qtd');
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
                    <div class="subtitle">COMPROVANTE DE ENTREGA</div>
                </td>
                <td style="width:24%;" class="document-id">
                    Contrato Nº:<br>{{ $locacao->numero_contrato }}
                </td>
            </tr>
        </table>
        <div class="meta">Data Emissão: {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    <div class="bloco">
        <table class="split">
            <tr>
                <td style="width:50%;"><strong>Data de Início:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $horaInicioFmt }}</td>
                <td style="width:50%; text-align:right;"><strong>Data Fim:</strong> {{ optional($locacao->data_fim)->format('d/m/Y') }} {{ $horaFimFmt }}</td>
            </tr>
            <tr>
                <td><strong>Início do transporte:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} - {{ $horaInicioFmt }} hrs</td>
                <td style="text-align:right;"><strong>Volta do transporte:</strong> {{ optional($locacao->data_fim)->format('d/m/Y') }} - {{ $horaFimFmt }} hrs</td>
            </tr>
            <tr>
                <td><strong>Data da montagem:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} - {{ $horaInicioFmt }} hrs</td>
                <td style="text-align:right;"><strong>Data da desmontagem:</strong> {{ optional($locacao->data_fim)->format('d/m/Y') }} - {{ $horaFimFmt }} hrs</td>
            </tr>
            <tr>
                <td><strong>Totalizando:</strong> {{ $qtdPeriodo }} {{ $labelPeriodo }}.</td>
                <td style="text-align:right;"><strong>Vendedor:</strong> {{ $vendedor ?: '-' }}</td>
            </tr>
        </table>

        <table class="split" style="margin-top:10px;">
            <tr>
                <td style="width:50%;"><strong>Cliente:</strong> {{ $clienteNome }}</td>
                <td style="width:50%;"><strong>CNPJ/CPF:</strong> {{ $docCliente }}</td>
            </tr>
            <tr>
                <td><strong>Endereço:</strong> {{ $locacao->endereco_entrega ?? $locacao->local_entrega ?? '-' }}</td>
                <td><strong>IE/RG:</strong> {{ $locacao->cliente->rg ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Telefone:</strong> {{ $locacao->cliente->telefone ?? $locacao->cliente->celular ?? '-' }}</td>
                <td><strong>Email:</strong> {{ $locacao->cliente->email ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Contato no Local:</strong> {{ $locacao->contato_local ?? $locacao->contato_responsavel ?? '-' }}</td>
                <td><strong>Motorista:</strong> __________________________ &nbsp; <strong>Hora:</strong> ____________</td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <thead>
            <tr>
                @if(!empty($imprimirComFoto))
                    <th class="text-center" style="width:55px;">Foto</th>
                @endif
                <th>Código / Série</th>
                <th>Descrição</th>
                <th class="text-center">Qtd</th>
                <th class="text-center">Dias</th>
                <th class="text-center">Qtd Entregue</th>
            </tr>
        </thead>
        <tbody>
            @php $temLinhasEntrega = false; @endphp
            @foreach($gruposEntrega as $idSala => $linhasSala)
                @php
                    $temLinhasEntrega = true;
                    $nomeSala = $idSala > 0 ? ($salasNome[$idSala] ?? ('Sala #' . $idSala)) : 'Sem sala';
                @endphp
                <tr>
                    <td colspan="{{ !empty($imprimirComFoto) ? 6 : 5 }}"><strong>Sala:</strong> {{ $nomeSala }}</td>
                </tr>
                @foreach($linhasSala as $linha)
                    <tr>
                        @if(!empty($imprimirComFoto))
                            <td class="text-center">
                                @if(!empty($linha['foto']))
                                    <img src="{{ $linha['foto'] }}" alt="Foto" style="width:32px;height:32px;object-fit:cover;border-radius:4px;">
                                @else
                                    -
                                @endif
                            </td>
                        @endif
                        <td>{{ $linha['codigo'] }}</td>
                        <td>{{ $linha['descricao'] }}</td>
                        <td class="text-center">{{ $linha['qtd'] }}</td>
                        <td class="text-center">{{ $qtdPeriodo }}</td>
                        <td></td>
                    </tr>
                @endforeach
            @endforeach
            @if(!$temLinhasEntrega)
                <tr>
                    <td colspan="{{ !empty($imprimirComFoto) ? 6 : 5 }}" class="text-center">Sem itens.</td>
                </tr>
            @endif
            <tr>
                <td colspan="{{ !empty($imprimirComFoto) ? 5 : 4 }}" class="text-center"><strong>Total</strong></td>
                <td class="text-center"><strong>{{ $totalItens }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="line-box">
        <strong>Local Entrega:</strong> {{ $locacao->local_entrega ?? $locacao->endereco_entrega ?? '-' }}
    </div>

    <div class="line-box">
        <strong>Observações:</strong> {{ $locacao->observacoes_entrega ?? '-' }}
    </div>

    <div class="line-box">
        <strong>Observações Adicionais:</strong>
    </div>

    <div class="bloco">
        <strong>Declaro para os devidos fins que recebemos os equipamentos acima em perfeito estado de funcionamento e deverão ser devolvidos em perfeito estado de conservação, sob pena da contratante arcar com os valores de reposição.</strong>
        <div class="mt-6">{{ $cidadeUf ?: 'Limeira - SP' }}, {{ ucfirst($nomeDia) }}, {{ $nomeData }}</div>
    </div>

    <table class="assinaturas">
        <tr>
            <td>
                @if(!empty($assinaturaLocadoraPdfSrc ?? null))
                    <img class="assinatura-img" src="{{ $assinaturaLocadoraPdfSrc }}" alt="Assinatura Locadora">
                @endif
                <div class="linha">ENTREGUE POR:<br>{{ $nomeEmpresa }}</div>
                <small>CNPJ: {{ $docLocadora }}</small>
            </td>
            <td>
                @if(!empty($assinaturaClientePdfSrc ?? null))
                    <img class="assinatura-img" src="{{ $assinaturaClientePdfSrc }}" alt="Assinatura Cliente">
                @endif
                <div class="linha">RECEBIDO POR:<br>{{ $clienteNome }}</div>
                <small>CPF/CNPJ: {{ $docCliente }}</small>
            </td>
            <td><div class="linha">CONFERIDO POR:</div></td>
        </tr>
    </table>

    <div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
    </div>
</body>
</html>
