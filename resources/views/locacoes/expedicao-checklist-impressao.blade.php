<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Checklist {{ strtoupper($tipo) }} {{ $locacao->numero_contrato }}</title>
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
        .line-box { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; padding: 8px; margin-top: 8px; }
        .split { width: 100%; border-collapse: collapse; }
        .split td { border: none; padding: 2px 0; }
        .section-title { margin: 12px 0 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #eff6ff; border: 1px solid #cbdffb; color: #1e3a8a; padding: 6px 8px; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #334155; padding: 5px; vertical-align: top; }
        th { background: #334155; color: #fff; }
        .text-center { text-align: center; }
        .foto-grid { width: 100%; border-collapse: separate; border-spacing: 4px; }
        .foto-grid td { border: none; width: 25%; }
        .foto-box { border: 1px solid #d2d8e0; border-radius: 6px; overflow: hidden; }
        .foto-img-wrap { width: 88px; height: 88px; margin: 0 auto; text-align: center; line-height: 88px; background: #f8fafc; }
        .foto-box img { max-width: 88px; max-height: 88px; width: auto; height: auto; vertical-align: middle; display: inline-block; }
        .foto-cap { font-size: 8px; color: #64748b; border-top: 1px solid #e2e8f0; padding: 3px 4px; min-height: 13px; }
        .assinaturas { width: 100%; margin-top: 14px; border-collapse: separate; border-spacing: 8px 0; }
        .assinaturas td { width: 50%; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; vertical-align: bottom; padding: 8px; background: #fff; }
        .assinatura-img { max-height: 58px; max-width: 220px; margin-bottom: 6px; }
        .linha-assinatura { margin-top: 20px; border-top: 1px solid #111; padding-top: 5px; font-weight: 700; }
        .footer-note { margin-top: 10px; font-size: 9px; text-align: center; color: #64748b; }
    </style>
</head>
<body>
@php
    $nomeEmpresa = $empresa->nome_fantasia ?? $empresa->razao_social ?? 'Locadora';
    $nomeCliente = $locacao->cliente->razao_social ?? $locacao->cliente->nome ?? '-';
    $titulo = $tipo === 'entrada' ? 'Checklist de Entrada' : 'Checklist de Saída';
    $fotosTipo = $tipo === 'entrada' ? $fotosEntrada : $fotosSaida;
    $statusLogisticaMap = [
        'para_separar' => 'Para Separar',
        'pronto_patio' => 'Pronto / No Pátio',
        'em_rota' => 'Em Rota',
        'entregue' => 'Entregue',
        'aguardando_coleta' => 'Aguardando Coleta',
    ];
    $statusLogisticaPdf = $statusLogisticaMap[$locacao->status_logistica ?? ''] ?? 'Não informado';
@endphp

<div class="page">
    <div class="faixa-topo"></div>
    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width:26%;">
                    @if(!empty($logoEmpresaPdfSrc))
                        <img class="logo" src="{{ $logoEmpresaPdfSrc }}" alt="Logo Empresa">
                    @endif
                </td>
                <td style="width:44%;">
                    <div class="subtitle">{{ $titulo }}</div>
                </td>
                <td style="width:30%;" class="document-id">
                    Contrato Nº:<br>{{ $locacao->numero_contrato }}
                </td>
            </tr>
        </table>
    </div>

    <div class="line-box">
        <table class="split">
            <tr>
                <td style="width:50%;"><strong>Locadora:</strong> {{ $nomeEmpresa }}</td>
                <td style="width:50%;"><strong>Cliente:</strong> {{ $nomeCliente }}</td>
            </tr>
            <tr>
                <td><strong>Data da emissão:</strong> {{ now()->format('d/m/Y H:i') }}</td>
                <td><strong>Tipo:</strong> {{ $tipo === 'entrada' ? 'Entrada' : 'Saída' }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Status logístico:</strong> {{ $statusLogisticaPdf }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Endereço:</strong> {{ $locacao->endereco_entrega ?? $locacao->local_entrega ?? $locacao->local_evento ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section-title">Itens e Evidências Fotográficas</div>
    <table>
        <thead>
            <tr>
                <th style="width:42%;">Produto</th>
                <th class="text-center" style="width:8%;">Qtd</th>
                @if($tipo === 'entrada')
                    <th style="width:25%;">Antes (Saída)</th>
                    <th style="width:25%;">Depois (Entrada)</th>
                @else
                    <th style="width:50%;">Fotos de Saída</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($locacao->produtos as $item)
                @php
                    $listaSaida = ($fotosSaida[$item->id_produto_locacao] ?? collect())->values();
                    $listaEntrada = ($fotosEntrada[$item->id_produto_locacao] ?? collect())->values();
                    $listaAtual = ($fotosTipo[$item->id_produto_locacao] ?? collect())->values();
                @endphp
                <tr>
                    <td>{{ $item->produto->nome ?? 'Item sem nome' }}</td>
                    <td class="text-center">{{ (int) ($item->quantidade ?? 1) }}</td>
                    @if($tipo === 'entrada')
                        <td>
                            <table class="foto-grid">
                                <tr>
                                    @forelse($listaSaida->take(4) as $foto)
                                        <td>
                                            <div class="foto-box">
                                                @if(!empty($foto['src_pdf']))
                                                    <div class="foto-img-wrap">
                                                        <img src="{{ $foto['src_pdf'] }}" alt="Saída">
                                                    </div>
                                                @else
                                                    <div style="padding:8px; font-size:8px;">Sem imagem</div>
                                                @endif
                                                <div class="foto-cap">{{ $foto['capturado_em'] ?? '' }}</div>
                                            </div>
                                        </td>
                                    @empty
                                        <td>Sem fotos</td>
                                    @endforelse
                                </tr>
                            </table>
                        </td>
                        <td>
                            <table class="foto-grid">
                                <tr>
                                    @forelse($listaEntrada->take(4) as $foto)
                                        <td>
                                            <div class="foto-box">
                                                @if(!empty($foto['src_pdf']))
                                                    <div class="foto-img-wrap">
                                                        <img src="{{ $foto['src_pdf'] }}" alt="Entrada">
                                                    </div>
                                                @else
                                                    <div style="padding:8px; font-size:8px;">Sem imagem</div>
                                                @endif
                                                <div class="foto-cap">{{ $foto['capturado_em'] ?? '' }}</div>
                                            </div>
                                        </td>
                                    @empty
                                        <td>Sem fotos</td>
                                    @endforelse
                                </tr>
                            </table>
                        </td>
                    @else
                        <td>
                            <table class="foto-grid">
                                <tr>
                                    @forelse($listaAtual->take(4) as $foto)
                                        <td>
                                            <div class="foto-box">
                                                @if(!empty($foto['src_pdf']))
                                                    <div class="foto-img-wrap">
                                                        <img src="{{ $foto['src_pdf'] }}" alt="Foto">
                                                    </div>
                                                @else
                                                    <div style="padding:8px; font-size:8px;">Sem imagem</div>
                                                @endif
                                                <div class="foto-cap">{{ $foto['capturado_em'] ?? '' }}</div>
                                            </div>
                                        </td>
                                    @empty
                                        <td>Sem fotos</td>
                                    @endforelse
                                </tr>
                            </table>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $tipo === 'entrada' ? 4 : 3 }}" class="text-center">Sem produtos vinculados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="line-box">
        <strong>Observações:</strong> {{ $checklist?->observacoes_gerais ?: 'Sem observações.' }}
    </div>

    <div class="section-title">Assinaturas</div>
    <table class="assinaturas">
        <tr>
            <td>
                @if(!empty($assinaturaOperadorPdfSrc))
                    <img class="assinatura-img" src="{{ $assinaturaOperadorPdfSrc }}" alt="Assinatura Operador">
                @endif
                <div class="linha-assinatura">{{ $operadorNome ?: 'Operador' }}</div>
                <small>Assinado em: {{ optional($checklist?->assinado_em)->format('d/m/Y H:i') ?: '-' }}</small>
            </td>
            <td>
                <div class="linha-assinatura">{{ $nomeCliente }}</div>
                <small>Recebedor / Responsável</small>
            </td>
        </tr>
    </table>

    <div class="footer-note">Documento gerado em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
</div>
</body>
</html>
