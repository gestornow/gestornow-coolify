<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura de Locação</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .header p {
            font-size: 11px;
            color: #666;
        }
        .fatura-numero {
            font-size: 14px;
            font-weight: bold;
            margin-top: 10px;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            background-color: #f5f5f5;
            padding: 8px 10px;
            margin-bottom: 10px;
            border-left: 4px solid #333;
        }
        .info-row {
            display: flex;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            width: 150px;
            flex-shrink: 0;
        }
        .info-value {
            flex-grow: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totais {
            margin-top: 20px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }
        .totais table {
            width: 300px;
            margin-left: auto;
        }
        .totais td {
            border: none;
            padding: 5px;
        }
        .total-final {
            font-size: 14px;
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 3px;
        }
        .status-pendente {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-pago {
            background-color: #d4edda;
            color: #155724;
        }
        @media print {
            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    @php
        $cfg = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $contextoFatura = $contextoFatura ?? [];
        $isMedicaoFatura = (bool) ($contextoFatura['is_medicao'] ?? false);
        $itensFatura = collect($contextoFatura['itens'] ?? []);
        $parcelas = collect($contextoFatura['parcelas'] ?? []);
        $primeiroVencimento = optional($parcelas->sortBy('numero_parcela')->first())->data_vencimento ?? $faturamento->data_vencimento;
        $formatarData = static fn($data) => !empty($data)
            ? \Illuminate\Support\Carbon::parse((string) $data)->format('d/m/Y')
            : '-';
        $periodoFaturaRotulo = $contextoFatura['periodo_rotulo'] ?? (
            optional($faturamento->locacao->data_inicio)->format('d/m/Y') . ' até ' . optional($faturamento->locacao->data_fim)->format('d/m/Y')
        );
        $diasPeriodoFatura = $contextoFatura['dias_periodo'] ?? null;
        $logoSrc = \App\Helpers\PdfAssetHelper::resolveCompanyConfigImage($empresa, 'logo_url', true);
    @endphp
    <!-- Cabeçalho -->
    <div class="header">
        @if($logoSrc)
            <img src="{{ $logoSrc }}" alt="Logo" style="max-height: 60px; max-width: 200px; margin-bottom: 10px;">
        @endif
        @if($empresa)
            <h1>{{ $empresa->razao_social ?? $empresa->nome_fantasia ?? 'Empresa' }}</h1>
            @if($empresa->cnpj)
                <p>CNPJ: {{ $empresa->cnpj }}</p>
            @endif
            @if($empresa->endereco)
                <p>{{ $empresa->endereco }}@if($empresa->cidade), {{ $empresa->cidade }}@endif @if($empresa->uf)- {{ $empresa->uf }}@endif</p>
            @endif
            @if($empresa->telefone)
                <p>Tel: {{ $empresa->telefone }}</p>
            @endif
        @endif
        <p class="fatura-numero">{{ $isMedicaoFatura ? 'FATURA DE MEDIÇÃO' : 'FATURA DE LOCAÇÃO' }} Nº {{ str_pad($faturamento->id_faturamento_locacao, 6, '0', STR_PAD_LEFT) }}</p>
        <p style="font-size: 11px;">Data: {{ $faturamento->data_faturamento->format('d/m/Y') }}</p>
    </div>

    <!-- Informações do Cliente -->
    <div class="section">
        <div class="section-title">DADOS DO CLIENTE</div>
        @if($faturamento->locacao->cliente)
            <div class="info-row">
                <span class="info-label">Nome/Razão Social:</span>
                <span class="info-value">{{ $faturamento->locacao->cliente->nome ?? $faturamento->locacao->cliente->razao_social ?? '-' }}</span>
            </div>
            @if($faturamento->locacao->cliente->cpf ?? $faturamento->locacao->cliente->cnpj ?? null)
            <div class="info-row">
                <span class="info-label">CPF/CNPJ:</span>
                <span class="info-value">{{ $faturamento->locacao->cliente->cpf ?? $faturamento->locacao->cliente->cnpj ?? '-' }}</span>
            </div>
            @endif
            @if($faturamento->locacao->cliente->endereco ?? null)
            <div class="info-row">
                <span class="info-label">Endereço:</span>
                <span class="info-value">{{ $faturamento->locacao->cliente->endereco }}</span>
            </div>
            @endif
            @if($faturamento->locacao->cliente->telefone ?? null)
            <div class="info-row">
                <span class="info-label">Telefone:</span>
                <span class="info-value">{{ $faturamento->locacao->cliente->telefone }}</span>
            </div>
            @endif
        @endif
    </div>

    <!-- Informações da Locação -->
    <div class="section">
        <div class="section-title">DADOS DA LOCAÇÃO</div>
        <div class="info-row">
            <span class="info-label">Contrato:</span>
            <span class="info-value">#{{ $faturamento->locacao->numero_contrato ?? $faturamento->locacao->id_locacao }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Período:</span>
            <span class="info-value">{{ $periodoFaturaRotulo }}</span>
        </div>
        @if($isMedicaoFatura)
        <div class="info-row">
            <span class="info-label">Tipo de Fatura:</span>
            <span class="info-value">Medição @if($diasPeriodoFatura) ({{ $diasPeriodoFatura }} dia(s)) @endif</span>
        </div>
        @endif
        @if($faturamento->locacao->local_evento ?? null)
        <div class="info-row">
            <span class="info-label">Local do Evento:</span>
            <span class="info-value">{{ $faturamento->locacao->local_evento }}</span>
        </div>
        @endif
    </div>

    <!-- Produtos da Locação -->
    @if($itensFatura->count() > 0)
    <div class="section">
        <div class="section-title">ITENS DA LOCAÇÃO - PRODUTOS</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">Qtd</th>
                    <th>Produto</th>
                    @if($isMedicaoFatura)
                    <th class="text-center">Dias</th>
                    @endif
                    <th class="text-right">Valor Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($itensFatura as $item)
                <tr>
                    <td class="text-center">{{ $item['quantidade'] ?? 1 }}</td>
                    <td>{{ $item['produto'] ?? 'Produto' }}</td>
                    @if($isMedicaoFatura)
                    <td class="text-center">{{ $item['dias'] ?? '-' }}</td>
                    @endif
                    <td class="text-right">R$ {{ number_format((float) ($item['valor_unitario'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item['subtotal'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Serviços da Locação -->
    @if($faturamento->locacao->servicos && $faturamento->locacao->servicos->count() > 0)
    <div class="section">
        <div class="section-title">SERVIÇOS ADICIONAIS</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center">Qtd</th>
                    <th>Serviço</th>
                    <th class="text-right">Valor Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($faturamento->locacao->servicos as $servico)
                <tr>
                    <td class="text-center">{{ $servico->quantidade ?? 1 }}</td>
                    <td>{{ $servico->descricao ?? 'Serviço' }}</td>
                    <td class="text-right">R$ {{ number_format($servico->valor ?? 0, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format(($servico->quantidade ?? 1) * ($servico->valor ?? 0), 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Totais -->
    <div class="totais">
        <table>
            @if($faturamento->locacao->valor_desconto ?? 0 > 0)
            <tr>
                <td>Desconto:</td>
                <td class="text-right">- R$ {{ number_format($faturamento->locacao->valor_desconto, 2, ',', '.') }}</td>
            </tr>
            @endif
            @if($faturamento->locacao->valor_acrescimo ?? 0 > 0)
            <tr>
                <td>Acréscimo:</td>
                <td class="text-right">R$ {{ number_format($faturamento->locacao->valor_acrescimo, 2, ',', '.') }}</td>
            </tr>
            @endif
            @if($faturamento->locacao->valor_frete ?? 0 > 0)
            <tr>
                <td>Frete:</td>
                <td class="text-right">R$ {{ number_format($faturamento->locacao->valor_frete, 2, ',', '.') }}</td>
            </tr>
            @endif
            <tr class="total-final">
                <td><strong>VALOR TOTAL:</strong></td>
                <td class="text-right"><strong>R$ {{ number_format($faturamento->valor_total, 2, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    <!-- Informações de Pagamento -->
    <div class="section">
        <div class="section-title">INFORMAÇÕES DE PAGAMENTO</div>
        <div class="info-row">
            <span class="info-label">{{ $parcelas->count() > 1 ? '1º Vencimento:' : 'Vencimento:' }}</span>
            <span class="info-value">{{ $formatarData($primeiroVencimento) }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Status:</span>
            <span class="info-value">
                @php
                    $status = $faturamento->contaReceber->status ?? 'pendente';
                    $statusClass = $status === 'pago' ? 'status-pago' : 'status-pendente';
                    $statusLabel = $status === 'pago' ? 'PAGO' : 'PENDENTE';
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </span>
        </div>
        @if($faturamento->locacao->forma_pagamento ?? null)
        <div class="info-row">
            <span class="info-label">Forma de Pagamento:</span>
            <span class="info-value">{{ $faturamento->locacao->forma_pagamento }}</span>
        </div>
        @endif
    </div>

    <!-- Vencimentos das Contas -->
    @if($parcelas->count() > 0)
    <div class="section">
        <div class="section-title">VENCIMENTOS DAS CONTAS</div>
        <table>
            <thead>
                <tr>
                    <th class="text-center" style="width: 15%;">Parcela</th>
                    <th style="width: 45%;">Documento/Referência</th>
                    <th class="text-center" style="width: 20%;">Vencimento</th>
                    <th class="text-right" style="width: 20%;">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($parcelas as $parcela)
                <tr>
                    <td class="text-center">{{ $parcela->numero_parcela ?? 1 }}/{{ $parcela->total_parcelas ?? 1 }}</td>
                    <td>{{ $parcela->documento ?? '-' }}</td>
                    <td class="text-center">{{ $formatarData($parcela->data_vencimento ?? null) }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($parcela->valor_total ?? 0), 2, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr class="total-final">
                    <td colspan="3" class="text-right"><strong>Total das Parcelas:</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($parcelas->sum('valor_total'), 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
        @if($parcelas->count() > 1)
        <p style="font-size: 10px; color: #666; margin-top: 8px;">
            <em>* Esta fatura foi parcelada em {{ $parcelas->count() }} vezes. Cada parcela possui data de vencimento própria conforme tabela acima.</em>
        </p>
        @endif
    </div>
    @endif

    <!-- Observações -->
    @if($faturamento->observacoes || $faturamento->locacao->observacoes)
    <div class="section">
        <div class="section-title">OBSERVAÇÕES</div>
        <p>{{ $faturamento->observacoes ?? $faturamento->locacao->observacoes }}</p>
    </div>
    @endif

    <!-- Rodapé -->
    <div class="footer">
        <p>
            {{ $empresa->cidade ?? '' }}, {{ now()->format('d') }} de {{ now()->translatedFormat('F') }} de {{ now()->format('Y') }}
        </p>
        <p>
            Documento gerado em {{ now()->format('d/m/Y H:i') }}
        </p>
    </div>
</body>
</html>
