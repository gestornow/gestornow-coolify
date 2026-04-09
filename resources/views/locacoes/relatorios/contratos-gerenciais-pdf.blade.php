<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Gerencial de Contratos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #2e3a4d; font-size: 11px; }
        .header { width: 100%; margin-bottom: 14px; }
        .header table { width: 100%; border-collapse: collapse; }
        .logo { width: 120px; }
        .title { text-align: right; }
        .title h1 { margin: 0; font-size: 18px; color: #1f2d3d; }
        .title .meta { margin-top: 3px; color: #6b778c; font-size: 10px; }
        .cards { width: 100%; margin-bottom: 14px; }
        .cards td { border: 1px solid #d9dee3; border-radius: 6px; padding: 8px; width: 25%; vertical-align: top; }
        .label { color: #6b778c; font-size: 10px; text-transform: uppercase; }
        .value { color: #1f2d3d; font-size: 15px; font-weight: 700; margin-top: 2px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background: #f2f4f7; color: #566a7f; font-weight: 700; font-size: 10px; text-transform: uppercase; border: 1px solid #d9dee3; padding: 6px; }
        .table td { border: 1px solid #e3e8ef; padding: 6px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .status { font-size: 10px; font-weight: 700; }
        .muted { color: #8a93a6; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    @if(!empty($logoEmpresaDataUri))
                        <img class="logo" src="{{ $logoEmpresaDataUri }}" alt="Logo">
                    @endif
                </td>
                <td class="title">
                    <h1>
                        @if($tipo === 'agenda')
                            Agenda de Vencimentos
                        @elseif($tipo === 'lucratividade')
                            Lucratividade por Contrato
                        @elseif($tipo === 'filtros')
                            Locações por Filtros
                        @else
                            Carteira de Contratos
                        @endif
                    </h1>
                    <div class="meta">Gerado em {{ $geradoEm->format('d/m/Y H:i') }} • Aba: {{ ucfirst($aba) }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="cards" cellspacing="8" cellpadding="0">
        <tr>
            <td>
                <div class="label">Contratos</div>
                <div class="value">{{ number_format((int)($totais['quantidade'] ?? 0), 0, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Receita Total</div>
                <div class="value">R$ {{ number_format((float)($totais['valor_total'] ?? 0), 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Despesas</div>
                <div class="value">R$ {{ number_format((float)($totais['valor_despesas'] ?? 0), 2, ',', '.') }}</div>
            </td>
            <td>
                <div class="label">Lucro / Margem</div>
                <div class="value">R$ {{ number_format((float)($totais['valor_lucro'] ?? 0), 2, ',', '.') }} ({{ number_format((float)($totais['margem_media'] ?? 0), 2, ',', '.') }}%)</div>
            </td>
        </tr>
    </table>

    @if(!empty($filtrosResumo['status']) || !empty($filtrosResumo['cliente']) || !empty($filtrosResumo['produto']) || !empty($filtrosResumo['periodo']))
        <table class="table" style="margin-bottom: 12px;">
            <tbody>
                <tr>
                    <td><strong>Status:</strong> {{ $filtrosResumo['status'] ?? 'Todos' }}</td>
                    <td><strong>Cliente:</strong> {{ $filtrosResumo['cliente'] ?? 'Todos' }}</td>
                    <td><strong>Produto:</strong> {{ $filtrosResumo['produto'] ?? 'Todos' }}</td>
                    <td><strong>Período:</strong> {{ !empty($filtrosResumo['periodo']) ? $filtrosResumo['periodo'] : 'Todos' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($tipo === 'agenda')
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Início</th>
                    <th>Fim</th>
                    <th>Período</th>
                    <th>Valor</th>
                    <th>Status prazo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locacoesAgenda as $locacao)
                    <tr>
                        <td class="text-center">{{ $locacao->codigo_display ?? $locacao->numero_contrato ?? $locacao->id_locacao }}</td>
                        <td>{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                        <td class="text-center">{{ optional($locacao->data_inicio)->format('d/m/Y') }}</td>
                        <td class="text-center">{{ optional($locacao->data_fim)->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $locacao->periodo_exibicao ?? '-' }}</td>
                        <td class="text-right">R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}</td>
                        <td class="text-center status">{{ $locacao->prazo_status_agenda ?? 'Sem data fim' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center muted">Nenhum contrato encontrado para agenda.</td></tr>
                @endforelse
            </tbody>
        </table>
    @elseif($tipo === 'filtros')
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Status</th>
                    <th>Período</th>
                    <th>Produto(s)</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locacoes as $locacao)
                    @php
                        $nomesProdutos = collect($locacao->produtos ?? [])
                            ->map(fn($item) => $item->produto->nome ?? null)
                            ->filter()
                            ->unique()
                            ->values();
                    @endphp
                    <tr>
                        <td class="text-center">{{ $locacao->codigo_display ?? $locacao->numero_contrato ?? $locacao->id_locacao }}</td>
                        <td>{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                        <td class="text-center">{{ \App\Domain\Locacao\Models\Locacao::statusList()[$locacao->status] ?? ucfirst($locacao->status ?? '-') }}</td>
                        <td class="text-center">{{ optional($locacao->data_inicio)->format('d/m/Y') }} - {{ optional($locacao->data_fim)->format('d/m/Y') }}</td>
                        <td>{{ $nomesProdutos->isNotEmpty() ? $nomesProdutos->join(', ') : '-' }}</td>
                        <td class="text-right">R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center muted">Nenhuma locação encontrada com os filtros informados.</td></tr>
                @endforelse
            </tbody>
        </table>
    @elseif($tipo === 'lucratividade')
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Receita</th>
                    <th>Despesas</th>
                    <th>Lucro</th>
                    <th>Margem</th>
                    <th>Faturamentos</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locacoes as $locacao)
                    <tr>
                        <td class="text-center">{{ $locacao->codigo_display ?? $locacao->numero_contrato ?? $locacao->id_locacao }}</td>
                        <td>{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                        <td class="text-right">R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format((float)($locacao->subtotal_despesas_listagem ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format((float)($locacao->valor_lucro_listagem ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float)($locacao->margem_lucro_listagem ?? 0), 2, ',', '.') }}%</td>
                        <td class="text-center">{{ (int)($locacao->faturamentos_ativos_count ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center muted">Nenhum contrato encontrado para lucratividade.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <table class="table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Período</th>
                    <th>Valor total</th>
                    <th>Status</th>
                    <th>Faturamentos</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locacoes as $locacao)
                    <tr>
                        <td class="text-center">{{ $locacao->codigo_display ?? $locacao->numero_contrato ?? $locacao->id_locacao }}</td>
                        <td>{{ $locacao->cliente->nome ?? 'N/A' }}</td>
                        <td class="text-center">{{ optional($locacao->data_inicio)->format('d/m/Y') }} - {{ optional($locacao->data_fim)->format('d/m/Y') }}</td>
                        <td class="text-right">R$ {{ number_format((float)($locacao->valor_total_listagem ?? 0), 2, ',', '.') }}</td>
                        <td class="text-center">{{ \App\Domain\Locacao\Models\Locacao::statusList()[$locacao->status] ?? ucfirst($locacao->status ?? '-') }}</td>
                        <td class="text-center">{{ (int)($locacao->faturamentos_ativos_count ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center muted">Nenhum contrato encontrado para carteira.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
