<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Vendas PDV</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #2e3a4d;
            font-size: 11px;
            line-height: 1.4;
            padding: 20px;
        }
        
        /* Header */
        .header {
            width: 100%;
            margin-bottom: 20px;
            border-bottom: 2px solid #566a7f;
            padding-bottom: 15px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo {
            max-width: 80px;
            max-height: 60px;
            width: auto;
            height: auto;
            object-fit: contain;
        }
        .company-info {
            padding-left: 15px;
            vertical-align: middle;
        }
        .company-name {
            font-size: 14px;
            font-weight: 700;
            color: #2e3a4d;
            margin-bottom: 2px;
        }
        .company-details {
            font-size: 9px;
            color: #6b778c;
            line-height: 1.3;
        }
        .title-section {
            text-align: right;
            vertical-align: middle;
        }
        .title-section h1 {
            margin: 0;
            font-size: 18px;
            color: #566a7f;
            font-weight: 700;
        }
        .title-section .subtitle {
            margin-top: 4px;
            color: #6b778c;
            font-size: 11px;
        }
        .title-section .meta {
            margin-top: 3px;
            color: #8a93a6;
            font-size: 10px;
        }
        
        /* Cards de Resumo */
        .summary-cards {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .summary-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e3e8ef;
            border-radius: 8px;
            padding: 12px;
            width: 25%;
            vertical-align: top;
        }
        .summary-card.highlight {
            background: linear-gradient(135deg, #566a7f 0%, #6b778c 100%);
            color: white;
            border: none;
        }
        .summary-card.highlight .card-label {
            color: rgba(255,255,255,0.85);
        }
        .summary-card.highlight .card-value {
            color: white;
        }
        .card-label {
            color: #6b778c;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .card-value {
            color: #1f2d3d;
            font-size: 16px;
            font-weight: 700;
            margin-top: 4px;
        }
        .card-value.success {
            color: #28c76f;
        }
        .card-value.info {
            color: #00cfe8;
        }
        .card-value.warning {
            color: #ff9f43;
        }
        .card-sublabel {
            color: #8a93a6;
            font-size: 8px;
            margin-top: 2px;
        }
        .summary-card.highlight .card-sublabel {
            color: rgba(255,255,255,0.7);
        }
        
        /* Seções */
        .section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section-title {
            background: linear-gradient(135deg, #566a7f 0%, #6b778c 100%);
            color: white;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-radius: 6px 6px 0 0;
            margin-bottom: 0;
        }
        .section-content {
            border: 1px solid #e3e8ef;
            border-top: none;
            border-radius: 0 0 6px 6px;
            padding: 12px;
            background: #fff;
        }
        
        /* Tabelas */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        .data-table th {
            background: #f2f4f7;
            color: #566a7f;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #e3e8ef;
            padding: 8px 6px;
            text-align: left;
        }
        .data-table td {
            border: 1px solid #e3e8ef;
            padding: 7px 6px;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background: #fafbfc;
        }
        .data-table .text-right {
            text-align: right;
        }
        .data-table .text-center {
            text-align: center;
        }
        .data-table tfoot {
            background: #f8f9fa;
            font-weight: 700;
        }
        .data-table tfoot td {
            border-top: 2px solid #566a7f;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
        }
        .badge-gold {
            background: linear-gradient(135deg, #ffd700 0%, #ffb347 100%);
            color: #5a4a00;
        }
        .badge-silver {
            background: linear-gradient(135deg, #c0c0c0 0%, #a8a8a8 100%);
            color: #333;
        }
        .badge-bronze {
            background: linear-gradient(135deg, #cd7f32 0%, #b87333 100%);
            color: #fff;
        }
        .badge-primary {
            background: #566a7f;
            color: white;
        }
        .badge-success {
            background: #28c76f;
            color: white;
        }
        .badge-danger {
            background: #ea5455;
            color: white;
        }
        
        /* Valor monetário */
        .money {
            font-weight: 600;
        }
        .money.positive {
            color: #28c76f;
        }
        .money.negative {
            color: #ea5455;
        }
        
        /* Barra de progresso simples */
        .progress-bar-container {
            background: #e9ecef;
            border-radius: 4px;
            height: 6px;
            width: 60px;
            display: inline-block;
            vertical-align: middle;
            margin-right: 5px;
        }
        .progress-bar-fill {
            background: linear-gradient(90deg, #566a7f 0%, #6b778c 100%);
            height: 100%;
            border-radius: 4px;
        }
        
        /* Grid 2 colunas */
        .two-columns {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
        }
        .two-columns > tbody > tr > td {
            width: 50%;
            vertical-align: top;
        }
        
        /* Rodapé */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e3e8ef;
            text-align: center;
            color: #8a93a6;
            font-size: 9px;
        }
        
        /* Cancelados */
        .alert-box {
            background: #fff5f5;
            border: 1px solid #ea5455;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .alert-title {
            color: #ea5455;
            font-weight: 700;
            font-size: 11px;
            margin-bottom: 8px;
        }
        .alert-stats {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
        }
        .alert-stat {
            background: #ffebeb;
            border-radius: 6px;
            padding: 8px 12px;
            text-align: center;
        }
        
        .muted {
            color: #8a93a6;
        }
        .small {
            font-size: 9px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <table>
            <tr>
                <td style="width: 90px; vertical-align: middle;">
                    @if(!empty($logoEmpresaDataUri))
                        <img class="logo" src="{{ $logoEmpresaDataUri }}" alt="Logo">
                    @endif
                </td>
                <td class="company-info">
                    @if(!empty($empresa))
                        <div class="company-name">{{ $empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome ?? 'Empresa' }}</div>
                        <div class="company-details">
                            @if($empresa->cnpj)
                                CNPJ: {{ $empresa->cnpj }}<br>
                            @endif
                            @if($empresa->endereco || $empresa->cidade)
                                {{ $empresa->endereco }}{{ $empresa->numero ? ', ' . $empresa->numero : '' }}
                                {{ $empresa->bairro ? ' - ' . $empresa->bairro : '' }}
                                {{ $empresa->cidade ? ' - ' . $empresa->cidade : '' }}{{ $empresa->uf ? '/' . $empresa->uf : '' }}
                                <br>
                            @endif
                            @if($empresa->telefone)
                                Tel: {{ $empresa->telefone }}
                            @endif
                            @if($empresa->email)
                                {{ $empresa->telefone ? ' | ' : '' }}{{ $empresa->email }}
                            @endif
                        </div>
                    @endif
                </td>
                <td class="title-section">
                    <h1>Relatório de Vendas PDV</h1>
                    <div class="subtitle">
                        @if(!empty($filters['data_inicio']) && !empty($filters['data_fim']))
                            Período: {{ \Carbon\Carbon::parse($filters['data_inicio'])->format('d/m/Y') }} até {{ \Carbon\Carbon::parse($filters['data_fim'])->format('d/m/Y') }}
                        @else
                            Período: Todo o histórico
                        @endif
                    </div>
                    <div class="meta">Gerado em {{ $geradoEm->format('d/m/Y \à\s H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Cards de Resumo -->
    <table class="summary-cards">
        <tr>
            <td class="summary-card highlight">
                <div class="card-label">Total de Vendas</div>
                <div class="card-value">{{ number_format($stats['total_vendas'], 0, ',', '.') }}</div>
                <div class="card-sublabel">vendas finalizadas</div>
            </td>
            <td class="summary-card">
                <div class="card-label">Faturamento Total</div>
                <div class="card-value success">R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}</div>
            </td>
            <td class="summary-card">
                <div class="card-label">Ticket Médio</div>
                <div class="card-value info">R$ {{ number_format($stats['ticket_medio'], 2, ',', '.') }}</div>
            </td>
            <td class="summary-card">
                <div class="card-label">Itens Vendidos</div>
                <div class="card-value warning">{{ number_format($stats['total_itens'], 0, ',', '.') }}</div>
            </td>
        </tr>
    </table>

    <!-- Vendas Canceladas (se houver) -->
    @if($stats['vendas_canceladas'] > 0)
    <div class="alert-box">
        <div class="alert-title">⚠ Vendas Canceladas no Período</div>
        <table class="alert-stats">
            <tr>
                <td class="alert-stat" style="width: 33%;">
                    <div class="card-label">Quantidade</div>
                    <div style="font-size: 14px; font-weight: 700; color: #ea5455;">{{ $stats['vendas_canceladas'] }}</div>
                </td>
                <td class="alert-stat" style="width: 33%;">
                    <div class="card-label">Valor Cancelado</div>
                    <div style="font-size: 14px; font-weight: 700; color: #ea5455;">R$ {{ number_format($stats['valor_cancelado'], 2, ',', '.') }}</div>
                </td>
                <td class="alert-stat" style="width: 33%;">
                    <div class="card-label">Taxa de Cancelamento</div>
                    <div style="font-size: 14px; font-weight: 700; color: #ea5455;">
                        {{ $stats['total_vendas'] > 0 ? number_format(($stats['vendas_canceladas'] / ($stats['total_vendas'] + $stats['vendas_canceladas'])) * 100, 1) : 0 }}%
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    <!-- Duas colunas: Top Produtos e Formas de Pagamento -->
    <table class="two-columns">
        <tr>
            <td>
                <!-- Top 10 Produtos -->
                <div class="section">
                    <div class="section-title">🏆 Top 10 Produtos Mais Vendidos</div>
                    <div class="section-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 30px;">#</th>
                                    <th>Produto</th>
                                    <th class="text-center" style="width: 50px;">Qtd</th>
                                    <th class="text-right" style="width: 80px;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topProdutos as $index => $produto)
                                    <tr>
                                        <td class="text-center">
                                            @if($index == 0)
                                                <span class="badge badge-gold">1º</span>
                                            @elseif($index == 1)
                                                <span class="badge badge-silver">2º</span>
                                            @elseif($index == 2)
                                                <span class="badge badge-bronze">3º</span>
                                            @else
                                                {{ $index + 1 }}º
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($produto->nome_produto, 30) }}</td>
                                        <td class="text-center">{{ intval($produto->total_quantidade) }}</td>
                                        <td class="text-right money positive">R$ {{ number_format($produto->total_valor, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center muted">Nenhum produto vendido</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </td>
            <td>
                <!-- Formas de Pagamento -->
                <div class="section">
                    <div class="section-title">💳 Vendas por Forma de Pagamento</div>
                    <div class="section-content">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Forma de Pagamento</th>
                                    <th class="text-center" style="width: 50px;">Vendas</th>
                                    <th class="text-right" style="width: 80px;">Total</th>
                                    <th class="text-right" style="width: 50px;">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vendasPorFormaPagamento as $item)
                                    <tr>
                                        <td>{{ $item->forma_pagamento }}</td>
                                        <td class="text-center">
                                            <span class="badge badge-primary">{{ $item->total_vendas }}</span>
                                        </td>
                                        <td class="text-right money positive">R$ {{ number_format($item->total_valor, 2, ',', '.') }}</td>
                                        <td class="text-right">
                                            {{ $stats['valor_total'] > 0 ? number_format(($item->total_valor / $stats['valor_total']) * 100, 1) : 0 }}%
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center muted">Nenhuma venda registrada</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(count($vendasPorFormaPagamento) > 0)
                            <tfoot>
                                <tr>
                                    <td><strong>Total</strong></td>
                                    <td class="text-center"><strong>{{ $stats['total_vendas'] }}</strong></td>
                                    <td class="text-right"><strong class="money positive">R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}</strong></td>
                                    <td class="text-right"><strong>100%</strong></td>
                                </tr>
                            </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Vendas por Operador -->
    <div class="section">
        <div class="section-title">👤 Desempenho por Operador</div>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 200px;">Operador</th>
                        <th class="text-center" style="width: 80px;">Total Vendas</th>
                        <th class="text-right" style="width: 110px;">Valor Total</th>
                        <th class="text-right" style="width: 100px;">Ticket Médio</th>
                        <th class="text-right" style="width: 130px;">Participação</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vendasPorOperador as $operador)
                        @php
                            $percentual = $stats['valor_total'] > 0 ? ($operador->total_valor / $stats['valor_total']) * 100 : 0;
                            $ticketOperador = $operador->total_vendas > 0 ? $operador->total_valor / $operador->total_vendas : 0;
                        @endphp
                        <tr>
                            <td>{{ $operador->nome_operador ?? 'Não identificado' }}</td>
                            <td class="text-center">
                                <span class="badge badge-primary">{{ $operador->total_vendas }}</span>
                            </td>
                            <td class="text-right money positive">R$ {{ number_format($operador->total_valor, 2, ',', '.') }}</td>
                            <td class="text-right">R$ {{ number_format($ticketOperador, 2, ',', '.') }}</td>
                            <td class="text-right">
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: {{ min($percentual, 100) }}%;"></div>
                                </div>
                                {{ number_format($percentual, 1) }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center muted">Nenhuma venda registrada</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Vendas por Dia -->
    @if(count($vendasPorDia) > 0)
    <div class="section">
        <div class="section-title">📅 Vendas por Dia</div>
        <div class="section-content">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 100px;">Data</th>
                        <th class="text-center" style="width: 80px;">Vendas</th>
                        <th class="text-right" style="width: 120px;">Faturamento</th>
                        <th class="text-right" style="width: 100px;">Ticket Médio</th>
                        <th class="text-right">Participação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($vendasPorDia as $dia)
                        @php
                            $percentual = $stats['valor_total'] > 0 ? ($dia->total_valor / $stats['valor_total']) * 100 : 0;
                            $ticketDia = $dia->total_vendas > 0 ? $dia->total_valor / $dia->total_vendas : 0;
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($dia->data)->format('d/m/Y') }}</td>
                            <td class="text-center">{{ $dia->total_vendas }}</td>
                            <td class="text-right money positive">R$ {{ number_format($dia->total_valor, 2, ',', '.') }}</td>
                            <td class="text-right">R$ {{ number_format($ticketDia, 2, ',', '.') }}</td>
                            <td class="text-right">
                                <div class="progress-bar-container">
                                    <div class="progress-bar-fill" style="width: {{ min($percentual, 100) }}%;"></div>
                                </div>
                                {{ number_format($percentual, 1) }}%
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td class="text-center"><strong>{{ $stats['total_vendas'] }}</strong></td>
                        <td class="text-right"><strong class="money positive">R$ {{ number_format($stats['valor_total'], 2, ',', '.') }}</strong></td>
                        <td class="text-right"><strong>R$ {{ number_format($stats['ticket_medio'], 2, ',', '.') }}</strong></td>
                        <td class="text-right"><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        @if(!empty($empresa))
            <p><strong>{{ $empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome ?? '' }}</strong></p>
        @endif
        <p class="small muted">Este documento é apenas para fins informativos e gerenciais</p>
    </div>
</body>
</html>
