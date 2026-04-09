<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Contas a Pagar</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
        }
        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 10px;
        }
        .info {
            margin-bottom: 15px;
            font-size: 9px;
        }
        .totais-resumo {
            margin-bottom: 15px;
            width: 100%;
        }
        .totais-resumo table {
            width: 100%;
        }
        .totais-resumo td {
            padding: 8px;
            text-align: center;
            border: 1px solid #ddd;
            font-weight: bold;
        }
        .totais-resumo .total-geral { background-color: #4285f4; color: white; }
        .totais-resumo .total-pago { background-color: #34a853; color: white; }
        .totais-resumo .total-pendente { background-color: #fbbc04; color: white; }
        .totais-resumo .total-vencido { background-color: #ea4335; color: white; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            table-layout: auto;
        }
        th {
            background-color: #f5f5f5;
            padding: 6px;
            text-align: left;
            border: 1px solid #ddd;
            font-size: 9px;
        }
        td {
            padding: 5px;
            border: 1px solid #ddd;
            font-size: 9px;
            height: auto;
            vertical-align: top;
            line-height: 1.35;
            word-wrap: break-word;
            word-break: break-word;
            white-space: normal;
            overflow-wrap: break-word;
        }
        tr {
            height: auto;
        }
        .descricao-cell {
            line-height: 1.35;
            white-space: pre-line;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 8px;
            font-weight: bold;
            color: white;
        }
        .badge-success { background-color: #34a853; }
        .badge-warning { background-color: #fbbc04; color: #000; }
        .badge-danger { background-color: #ea4335; }
        .badge-dark { background-color: #666; }
        tfoot {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Relatório de Contas a Pagar</h1>
    
    <div class="info">
        <strong>Data de Geração:</strong> {{ date('d/m/Y H:i:s') }}<br>
        @if(!empty($filtros['data_inicio']) && !empty($filtros['data_fim']))
        <strong>Período:</strong> {{ date('d/m/Y', strtotime($filtros['data_inicio'])) }} a {{ date('d/m/Y', strtotime($filtros['data_fim'])) }}<br>
        @endif
        @if(!empty($filtros['status']))
        <strong>Status:</strong> {{ ucfirst($filtros['status']) }}<br>
        @endif
    </div>

    <div class="totais-resumo">
        <table>
            <tr>
                <td class="total-geral">
                    Total Geral<br>
                    R$ {{ number_format($total_geral, 2, ',', '.') }}
                </td>
                <td class="total-pago">
                    Total Pago<br>
                    R$ {{ number_format($total_pago, 2, ',', '.') }}
                </td>
                <td class="total-pendente">
                    Total Pendente<br>
                    R$ {{ number_format($total_pendente, 2, ',', '.') }}
                </td>
                <td class="total-vencido">
                    Total Vencido<br>
                    R$ {{ number_format($total_vencido, 2, ',', '.') }}
                </td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 9%;">Vencimento</th>
                <th style="width: 30%;">Descrição</th>
                <th style="width: 15%;">Fornecedor</th>
                <th style="width: 7%;">Doc</th>
                <th style="width: 12%;">Categoria</th>
                <th style="width: 8%;">Banco</th>
                <th class="text-right" style="width: 7%;">Valor</th>
                <th class="text-right" style="width: 7%;">Pago</th>
                <th class="text-right" style="width: 7%;">Restante</th>
                <th class="text-center" style="width: 8%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contas as $conta)
            <tr>
                <td>{{ date('d/m/Y', strtotime($conta->data_vencimento)) }}</td>
                <td><div class="descricao-cell">{{ $conta->descricao }}</div></td>
                <td>{{ $conta->fornecedor->razao_social ?? '-' }}</td>
                <td>{{ $conta->documento ?? '-' }}</td>
                <td>{{ $conta->categoria->nome ?? '-' }}</td>
                <td>{{ $conta->banco->nome_banco ?? '-' }}</td>
                <td class="text-right">R$ {{ number_format($conta->valor_total, 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($conta->valor_pago ?: 0, 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($conta->valor_total - ($conta->valor_pago ?: 0), 2, ',', '.') }}</td>
                <td class="text-center">
                    @php
                        $badgeClass = 'dark';
                        if ($conta->status == 'pago') $badgeClass = 'success';
                        elseif ($conta->status == 'vencido') $badgeClass = 'danger';
                        elseif ($conta->status == 'pendente') $badgeClass = 'warning';
                    @endphp
                    <span class="badge badge-{{ $badgeClass }}">{{ $conta->status_label }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center">Nenhum registro encontrado</td>
            </tr>
            @endforelse
        </tbody>
        @if($contas->isNotEmpty())
        <tfoot>
            <tr>
                <th colspan="6" class="text-right">TOTAIS:</th>
                <th class="text-right">R$ {{ number_format($total_geral, 2, ',', '.') }}</th>
                <th class="text-right">R$ {{ number_format($total_pago, 2, ',', '.') }}</th>
                <th class="text-right">R$ {{ number_format($total_restante, 2, ',', '.') }}</th>
                <th></th>
            </tr>
        </tfoot>
        @endif
    </table>

    <div class="footer">
        Gerado por GestorNow em {{ date('d/m/Y H:i:s') }}
    </div>
</body>
</html>
