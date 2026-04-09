<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Mensal de Movimentações</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #3b3b4f; margin: 0; }
        .container { padding: 18px 22px; }
        .header { border-bottom: 2px solid #5156be; padding-bottom: 8px; margin-bottom: 14px; }
        .header-top { display: table; width: 100%; margin-bottom: 6px; }
        .header-top .logo-wrap,
        .header-top .empresa-wrap { display: table-cell; vertical-align: middle; }
        .header-top .logo-wrap { width: 90px; }
        .logo {
            max-width: 78px;
            max-height: 60px;
            border-radius: 4px;
            border: 1px solid #d7dcf0;
            object-fit: contain;
            padding: 2px;
            background: #fff;
        }
        .empresa-nome { font-size: 12px; font-weight: 700; color: #2f3348; margin-bottom: 2px; }
        .empresa-info { font-size: 10px; color: #666e8a; }
        .titulo { font-size: 18px; font-weight: 700; color: #2c2f7f; margin: 0 0 4px 0; }
        .subtitulo { font-size: 11px; color: #6b6b86; margin: 0; }
        .meta { margin-top: 8px; font-size: 10px; color: #5b6078; }
        .meta strong { color: #2f3348; }

        .cards { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 0 -8px 14px -8px; }
        .cards td { width: 25%; vertical-align: top; }
        .card {
            border: 1px solid #dfe3f2;
            border-radius: 6px;
            padding: 8px 10px;
            min-height: 58px;
            background: #f8f9fe;
        }
        .card .label { font-size: 9px; color: #7b8098; text-transform: uppercase; margin-bottom: 4px; }
        .card .valor { font-size: 16px; color: #2f3348; font-weight: 700; line-height: 1.1; }

        .secao-titulo {
            font-size: 12px;
            font-weight: 700;
            color: #2f3348;
            margin: 2px 0 8px 0;
        }

        table.listagem { width: 100%; border-collapse: collapse; }
        table.listagem th {
            background: #eef1ff;
            color: #2d3152;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .2px;
            border: 1px solid #d9deee;
            padding: 7px 6px;
            text-align: left;
        }
        table.listagem td {
            border: 1px solid #e2e6f1;
            padding: 6px;
            font-size: 10px;
            vertical-align: middle;
        }
        table.listagem tbody tr:nth-child(even) { background: #fafbff; }

        .tipo-entrada { color: #177245; font-weight: 700; }
        .tipo-retorno { color: #996100; font-weight: 700; }
        .text-center { text-align: center; }

        .rodape {
            margin-top: 10px;
            font-size: 9px;
            color: #858aa0;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <div class="logo-wrap">
                    @if(!empty($logoEmpresaDataUri ?? null))
                        <img class="logo" src="{{ $logoEmpresaDataUri }}" alt="Logo empresa">
                    @endif
                </div>
                <div class="empresa-wrap">
                    <div class="empresa-nome">{{ $locacao->empresa->nome_fantasia ?? $locacao->empresa->razao_social ?? 'Empresa' }}</div>
                    <div class="empresa-info">
                        @if(!empty($locacao->empresa->cnpj ?? null)) CNPJ: {{ $locacao->empresa->cnpj }} @endif
                        @if(!empty($locacao->empresa->telefone ?? null)) • Tel: {{ $locacao->empresa->telefone }} @endif
                        @if(!empty($locacao->empresa->email ?? null)) • {{ $locacao->empresa->email }} @endif
                    </div>
                </div>
            </div>
            <p class="titulo">Relatório de Movimentações de Medição</p>
            <p class="subtitulo">Acompanhamento de entradas e retornos de produtos no período.</p>
            <div class="meta">
                <strong>Contrato:</strong> {{ $locacao->codigo_display }}
                &nbsp;&nbsp;•&nbsp;&nbsp;
                <strong>Cliente:</strong> {{ $locacao->cliente->nome ?? 'N/A' }}
                &nbsp;&nbsp;•&nbsp;&nbsp;
                <strong>Período:</strong> {{ ($periodoInicio ?? null) ? $periodoInicio->format('d/m/Y H:i') : '-' }} até {{ ($periodoFim ?? null) ? $periodoFim->format('d/m/Y H:i') : '-' }}
            </div>
        </div>

        <table class="cards">
            <tr>
                <td>
                    <div class="card">
                        <div class="label">Entradas</div>
                        <div class="valor">{{ $resumo['entradas_qtd'] ?? 0 }}</div>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="label">Itens de entrada</div>
                        <div class="valor">{{ $resumo['entradas_itens'] ?? 0 }}</div>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="label">Retornos</div>
                        <div class="valor">{{ $resumo['retornos_qtd'] ?? 0 }}</div>
                    </div>
                </td>
                <td>
                    <div class="card">
                        <div class="label">Itens de retorno</div>
                        <div class="valor">{{ $resumo['retornos_itens'] ?? 0 }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="cards" style="margin-top:4px;">
            <tr>
                <td style="width:100%;">
                    <div class="card" style="background:#eef7ff;border-color:#cfe4ff;">
                        <div class="label">Valor total do período medido</div>
                        <div class="valor" style="color:#0f4aa3;">R$ {{ number_format((float) ($valorTotalPeriodo ?? 0), 2, ',', '.') }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="secao-titulo">Movimentações no período</div>
        <table class="listagem">
            <thead>
                <tr>
                    <th style="width: 22%;">Data/Hora</th>
                    <th style="width: 14%;">Tipo</th>
                    <th style="width: 34%;">Produto</th>
                    <th style="width: 18%;">Patrimônio</th>
                    <th style="width: 12%;">Qtd</th>
                </tr>
            </thead>
            <tbody>
                @forelse($movimentacoes as $mov)
                    <tr>
                        <td>{{ $mov['data_hora']->format('d/m/Y H:i') }}</td>
                        <td class="{{ $mov['tipo'] === 'Entrada' ? 'tipo-entrada' : 'tipo-retorno' }}">{{ $mov['tipo'] }}</td>
                        <td>{{ $mov['produto'] }}</td>
                        <td>{{ $mov['patrimonio'] ?? '-' }}</td>
                        <td>{{ $mov['quantidade'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Sem movimentações no mês selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="secao-titulo" style="margin-top:10px;">Financeiro por produto (período selecionado)</div>
        <table class="listagem">
            <thead>
                <tr>
                    <th style="width: 24%;">Produto</th>
                    <th style="width: 14%;">Patrimônio</th>
                    <th style="width: 8%;">Qtd</th>
                    <th style="width: 12%;">Valor Unit.</th>
                    <th style="width: 8%;">Dias</th>
                    <th style="width: 14%;">Subtotal</th>
                    <th style="width: 20%;">Período</th>
                </tr>
            </thead>
            <tbody>
                @forelse($produtosResumo ?? [] as $item)
                    <tr>
                        <td>{{ $item['produto'] ?? '-' }}</td>
                        <td>{{ $item['patrimonio'] ?? '-' }}</td>
                        <td>{{ $item['quantidade'] ?? 1 }}</td>
                        <td>R$ {{ number_format((float) ($item['valor_unitario'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $item['dias_periodo'] ?? 0 }}</td>
                        <td>R$ {{ number_format((float) ($item['valor_periodo'] ?? 0), 2, ',', '.') }}</td>
                        <td>
                            {{ ($item['inicio'] ?? null) instanceof \Carbon\Carbon ? $item['inicio']->format('d/m/Y H:i') : '-' }}
                            até
                            {{ ($item['fim'] ?? null) instanceof \Carbon\Carbon ? $item['fim']->format('d/m/Y H:i') : '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center">Sem financeiro de produtos para o período selecionado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="secao-titulo" style="margin-top:10px;">Períodos já faturados</div>
        <table class="listagem">
            <thead>
                <tr>
                    <th style="width: 20%;">Fatura</th>
                    <th style="width: 30%;">Início</th>
                    <th style="width: 30%;">Fim</th>
                    <th style="width: 20%;">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse($periodosFaturados ?? [] as $periodo)
                    <tr>
                        <td>{{ !empty($periodo['numero_fatura']) ? ('#' . $periodo['numero_fatura']) : '-' }}</td>
                        <td>{{ ($periodo['inicio'] ?? null) instanceof \Carbon\Carbon ? $periodo['inicio']->format('d/m/Y') : '-' }}</td>
                        <td>{{ ($periodo['fim'] ?? null) instanceof \Carbon\Carbon ? $periodo['fim']->format('d/m/Y') : '-' }}</td>
                        <td>R$ {{ number_format((float) ($periodo['valor'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">Sem períodos faturados para este contrato.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="rodape">
            Gerado em {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
</body>
</html>
