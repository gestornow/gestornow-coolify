<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Romaneio {{ $locacao->numero_contrato }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #1f2937; }
        .faixa-topo { height: 7px; background: {{ $corPrimariaDocumento ?? '#1f97ea' }}; border-radius: 6px 6px 0 0; margin-bottom: 6px; }
        .header { border: 1px solid #d0d7e2; border-radius: 8px; padding: 10px; margin-bottom: 10px; background: #f8fbff; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; vertical-align: middle; }
        .logo { max-height: 54px; max-width: 180px; }
        .subtitle { text-align: center; font-size: 16px; font-weight: 700; color: #0f3f7d; }
        .document-id { text-align: right; font-size: 12px; font-weight: 700; color: #0f3f7d; background: #e8f0fc; border: 1px solid #cdddf5; border-radius: 6px; padding: 6px 8px; }
        .meta { margin-top: 8px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #334155; padding: 6px; }
        th { background: #334155; color: #fff; }
        .linha-zebra { background: #f8fafc; }
        .text-center { text-align: center; }
        .bloco { margin-top: 12px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fbff; padding: 8px; }
        .assinaturas td { border: none; text-align: center; width: 33%; }
        .linha { margin-top: 40px; border-top: 1px solid #111; padding-top: 6px; }
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

        $resolverFoto = function (?string $url) {
            if (empty($url)) {
                return null;
            }
            $path = parse_url($url, PHP_URL_PATH);
            $local = $path ? public_path(ltrim($path, '/')) : null;
            return ($local && file_exists($local)) ? $local : $url;
        };

        $salasNome = collect($locacao->salas ?? [])->mapWithKeys(function ($sala) {
            return [(int) ($sala->id_sala ?? 0) => ($sala->nome ?? 'Sala')];
        });

        $linhasRomaneio = collect();
        foreach ($locacao->produtos as $item) {
            $linhasRomaneio->push([
                'sala_id' => (int) ($item->id_sala ?? 0),
                'nome' => $item->produto->nome ?? 'Item',
                'qtd' => (int) ($item->quantidade ?? 1),
                'obs' => $item->patrimonio->codigo_patrimonio ?? '-',
                'foto' => $resolverFoto($item->produto->foto_url ?? null),
            ]);
        }
        foreach ($locacao->produtosTerceiros as $item) {
            $linhasRomaneio->push([
                'sala_id' => (int) ($item->id_sala ?? 0),
                'nome' => $item->nome_produto ?? 'Produto de Terceiro',
                'qtd' => (int) ($item->quantidade ?? 1),
                'obs' => 'Fornecedor: ' . ($item->fornecedor->nome ?? '-'),
                'foto' => null,
            ]);
        }
        $gruposRomaneio = $linhasRomaneio->groupBy('sala_id');
    @endphp
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
                    <div class="subtitle">Romaneio de Entrega</div>
                </td>
                <td style="width:24%;" class="document-id">
                    Contrato Nº:<br>{{ $locacao->numero_contrato }}
                </td>
            </tr>
        </table>
        <div class="meta">
            Cliente: {{ $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-' }}
        </div>
    </div>

    <div class="bloco">
        <strong>Local de Entrega:</strong> {{ $locacao->endereco_entrega ?? $locacao->local_entrega ?? '-' }}<br>
        <strong>Contato:</strong> {{ $locacao->contato_responsavel ?? $locacao->contato_local ?? '-' }}<br>
        <strong>Telefone:</strong> {{ $locacao->telefone_responsavel ?? $locacao->telefone_contato ?? '-' }}<br>
        <strong>Data/Hora da Entrega:</strong> {{ optional($locacao->data_inicio)->format('d/m/Y') }} {{ $locacao->hora_inicio ?? '' }}
    </div>

    <table>
        <thead>
            <tr>
                @if(!empty($imprimirComFoto))
                    <th class="text-center" style="width:58px;">Foto</th>
                @endif
                <th>Item</th>
                <th class="text-center" style="width:80px;">Qtd</th>
                <th style="width:180px;">Patrimônio/Obs</th>
                <th style="width:170px;">Conferência</th>
            </tr>
        </thead>
        <tbody>
            @php $temLinhas = false; @endphp
            @foreach($gruposRomaneio as $idSala => $linhasSala)
                @php
                    $temLinhas = true;
                    $nomeSala = $idSala > 0 ? ($salasNome[$idSala] ?? ('Sala #' . $idSala)) : 'Sem sala';
                @endphp
                <tr>
                    <td colspan="{{ !empty($imprimirComFoto) ? 5 : 4 }}"><strong>Sala:</strong> {{ $nomeSala }}</td>
                </tr>
                @foreach($linhasSala as $linha)
                    <tr class="{{ $loop->even ? 'linha-zebra' : '' }}">
                        @if(!empty($imprimirComFoto))
                            <td class="text-center">
                                @if(!empty($linha['foto']))
                                    <img src="{{ $linha['foto'] }}" alt="Foto" style="width:36px;height:36px;object-fit:cover;border-radius:4px;">
                                @else
                                    -
                                @endif
                            </td>
                        @endif
                        <td>{{ $linha['nome'] }}</td>
                        <td class="text-center">{{ $linha['qtd'] }}</td>
                        <td>{{ $linha['obs'] }}</td>
                        <td>[ ] Entregue [ ] Pendente</td>
                    </tr>
                @endforeach
            @endforeach
            @if(!$temLinhas)
                <tr><td colspan="{{ !empty($imprimirComFoto) ? 5 : 4 }}" class="text-center">Sem itens.</td></tr>
            @endif
        </tbody>
    </table>

    <table class="assinaturas">
        <tr>
            <td><div class="linha">Expedição</div></td>
            <td><div class="linha">Motorista</div></td>
            <td><div class="linha">Recebedor Cliente</div></td>
        </tr>
    </table>
    <div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
</body>
</html>
