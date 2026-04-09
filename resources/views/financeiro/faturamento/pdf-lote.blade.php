<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura em Lote - Múltiplas Locações</title>
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
        .lote-badge {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
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
        .locacao-box {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fafafa;
        }
        .locacao-header {
            font-size: 13px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #007bff;
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
        .subtotal-box {
            background-color: #e8f4f8;
            padding: 10px;
            margin-top: 10px;
            border: 1px solid #007bff;
        }
        .subtotal-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .subtotal-label {
            font-weight: bold;
            color: #555;
        }
        .subtotal-value {
            font-weight: bold;
            color: #333;
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
        $contextosFatura = is_array($contextosFatura ?? null) ? $contextosFatura : [];
        $contextoFaturaPrincipal = $contextosFatura[$faturamentos->first()->id_faturamento_locacao] ?? [];
        $parcelas = collect($contextoFaturaPrincipal['parcelas'] ?? []);
        $primeiroVencimento = optional($parcelas->sortBy('numero_parcela')->first())->data_vencimento ?? $faturamentos->first()->data_vencimento;
        $formatarData = static fn($data) => !empty($data)
            ? \Illuminate\Support\Carbon::parse((string) $data)->format('d/m/Y')
            : '-';
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
        <p class="fatura-numero">FATURA EM LOTE Nº {{ str_pad($faturamentos->first()->id_faturamento_locacao, 6, '0', STR_PAD_LEFT) }}</p>
        <p><span class="lote-badge">{{ $faturamentos->count() }} LOCAÇÕES</span></p>
        <p style="font-size: 11px;">Data: {{ $faturamentos->first()->data_faturamento->format('d/m/Y') }}</p>
    </div>

    <!-- Informações do Cliente -->
    <div class="section">
        <div class="section-title">DADOS DO CLIENTE</div>
        @if($faturamentos->first()->locacao->cliente)
            <div class="info-row">
                <span class="info-label">Nome/Razão Social:</span>
                <span class="info-value">{{ $faturamentos->first()->locacao->cliente->nome ?? $faturamentos->first()->locacao->cliente->razao_social ?? '-' }}</span>
            </div>
            @if($faturamentos->first()->locacao->cliente->cpf ?? $faturamentos->first()->locacao->cliente->cnpj ?? null)
            <div class="info-row">
                <span class="info-label">CPF/CNPJ:</span>
                <span class="info-value">{{ $faturamentos->first()->locacao->cliente->cpf ?? $faturamentos->first()->locacao->cliente->cnpj ?? '-' }}</span>
            </div>
            @endif
            @if($faturamentos->first()->locacao->cliente->endereco ?? null)
            <div class="info-row">
                <span class="info-label">Endereço:</span>
                <span class="info-value">{{ $faturamentos->first()->locacao->cliente->endereco }}</span>
            </div>
            @endif
            @if($faturamentos->first()->locacao->cliente->telefone ?? null)
            <div class="info-row">
                <span class="info-label">Telefone:</span>
                <span class="info-value">{{ $faturamentos->first()->locacao->cliente->telefone }}</span>
            </div>
            @endif
        @endif
    </div>

    <!-- Listagem de Locações -->
    <div class="section">
        <div class="section-title">DETALHAMENTO DAS LOCAÇÕES</div>
        
        @foreach($faturamentos as $index => $faturamento)
        @php
            $contexto = $contextosFatura[$faturamento->id_faturamento_locacao] ?? [];
            $isMedicaoFatura = (bool) ($contexto['is_medicao'] ?? false);
            $itensFatura = collect($contexto['itens'] ?? []);
            $periodoFaturaRotulo = $contexto['periodo_rotulo'] ?? (
                optional($faturamento->locacao->data_inicio)->format('d/m/Y') . ' até ' . optional($faturamento->locacao->data_fim)->format('d/m/Y')
            );
            $diasPeriodoFatura = $contexto['dias_periodo'] ?? null;
        @endphp
        <div class="locacao-box">
            <div class="locacao-header">
                Locação {{ $index + 1 }} de {{ $faturamentos->count() }} - 
                Contrato #{{ $faturamento->locacao->numero_contrato ?? $faturamento->locacao->id_locacao }}
            </div>
            
            <!-- Informações da Locação -->
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
            @if($faturamento->descricao ?? null)
            <div class="info-row">
                <span class="info-label">Descrição:</span>
                <span class="info-value">{{ $faturamento->descricao }}</span>
            </div>
            @endif

            <!-- Produtos da Locação -->
            @if($itensFatura->count() > 0)
            <div style="margin-top: 15px;">
                <strong style="color: #333; font-size: 12px;">Produtos</strong>
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
            <div style="margin-top: 15px;">
                <strong style="color: #333; font-size: 12px;">Serviços</strong>
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

            <!-- Subtotal da Locação -->
            <div class="subtotal-box">
                @if($faturamento->locacao->valor_desconto ?? 0 > 0)
                <div class="subtotal-row">
                    <span class="subtotal-label">Desconto:</span>
                    <span class="subtotal-value">- R$ {{ number_format($faturamento->locacao->valor_desconto, 2, ',', '.') }}</span>
                </div>
                @endif
                
                @if($faturamento->locacao->valor_acrescimo ?? 0 > 0)
                <div class="subtotal-row">
                    <span class="subtotal-label">Acréscimo:</span>
                    <span class="subtotal-value">+ R$ {{ number_format($faturamento->locacao->valor_acrescimo, 2, ',', '.') }}</span>
                </div>
                @endif
                
                @if($faturamento->locacao->valor_frete ?? 0 > 0)
                <div class="subtotal-row">
                    <span class="subtotal-label">Frete:</span>
                    <span class="subtotal-value">+ R$ {{ number_format($faturamento->locacao->valor_frete, 2, ',', '.') }}</span>
                </div>
                @endif

                <div class="subtotal-row" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #007bff;">
                    <span class="subtotal-label" style="font-size: 12px; color: #007bff;">Subtotal desta Locação:</span>
                    <span class="subtotal-value" style="font-size: 13px; color: #007bff;">R$ {{ number_format($faturamento->valor_total, 2, ',', '.') }}</span>
                </div>
            </div>

            @if($faturamento->observacoes)
            <div style="margin-top: 10px; padding: 8px; background-color: #fff9e6; border-left: 3px solid #ffc107;">
                <strong style="color: #856404;">Observações:</strong> {{ $faturamento->observacoes }}
            </div>
            @endif
            </div>
            @endforeach
        </div>
    </div>

    <!-- Totais do Lote -->
    <div class="totais">
        <table>
            <tr>
                <td>Quantidade de Locações:</td>
                <td class="text-right">{{ $faturamentos->count() }}</td>
            </tr>
            @php
                $totalGeral = $faturamentos->sum('valor_total');
            @endphp
            <tr class="total-final">
                <td><strong>VALOR TOTAL DO LOTE:</strong></td>
                <td class="text-right"><strong>R$ {{ number_format($totalGeral, 2, ',', '.') }}</strong></td>
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
                    $contaReceber = $faturamentos->first()->contaReceber;
                    $status = $contaReceber->status ?? 'pendente';
                    $statusClass = $status === 'pago' ? 'status-pago' : 'status-pendente';
                    $statusLabel = $status === 'pago' ? 'PAGO' : 'PENDENTE';
                @endphp
                <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
            </span>
        </div>
        @if($faturamentos->first()->locacao->forma_pagamento ?? null)
        <div class="info-row">
            <span class="info-label">Forma de Pagamento:</span>
            <span class="info-value">{{ $faturamentos->first()->locacao->forma_pagamento }}</span>
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
            <em>* Este lote foi parcelado em {{ $parcelas->count() }} vezes. Cada parcela possui data de vencimento própria conforme tabela acima.</em>
        </p>
        @endif
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
        <p>
            Esta é uma fatura consolidada em lote contendo {{ $faturamentos->count() }} locações do mesmo cliente
        </p>
    </div>
</body>
</html>
