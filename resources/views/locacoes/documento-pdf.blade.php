<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $tipo = $tipo ?? 'contrato';
        $titulos = [
            'contrato' => 'Contrato de Locação',
            'orcamento' => 'Orçamento de Locação',
            'checklist' => 'Checklist de Conferência',
            'romaneio' => 'Romaneio de Entrega',
            'entrega' => 'Comprovante de Entrega',
            'recibo' => 'Recibo de Locação',
        ];
        $tituloDocumento = $titulos[$tipo] ?? 'Documento de Locação';

        $statusList = \App\Domain\Locacao\Models\Locacao::statusList();
        $statusLabel = $statusList[$locacao->status] ?? ucfirst($locacao->status ?? '-');

        $valorProdutos = (float) ($locacao->produtos->sum('preco_total') ?? 0);
        $valorProdutosTerceiros = (float) ($locacao->produtosTerceiros->sum('valor_total') ?? 0);
        $valorServicos = (float) ($locacao->servicos->sum('valor_total') ?? 0);
        $valorDespesas = (float) ($locacao->despesas->sum('valor') ?? 0);

        $valorBase = $valorProdutos + $valorProdutosTerceiros + $valorServicos + $valorDespesas;
        $valorFinal = (float) ($locacao->valor_final ?? $valorBase);

        $qtdItens = (int) ($locacao->produtos->sum('quantidade') ?? 0) + (int) ($locacao->produtosTerceiros->sum('quantidade') ?? 0);

        $periodoInicio = $locacao->data_inicio ? $locacao->data_inicio->format('d/m/Y') : '-';
        $periodoFim = $locacao->data_fim ? $locacao->data_fim->format('d/m/Y') : '-';
    @endphp
    <title>{{ $tituloDocumento }} #{{ $locacao->numero_contrato }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            margin: 18px;
        }
        .header {
            text-align: center;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .header h2 {
            margin: 4px 0;
            font-size: 14px;
        }
        .meta {
            margin-top: 6px;
            font-size: 9px;
            color: #4b5563;
        }
        .info {
            margin: 10px 0;
            font-size: 9px;
            line-height: 1.5;
        }
        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin: 10px 0 14px;
        }
        .cards td {
            color: #fff;
            font-weight: bold;
            text-align: center;
            padding: 8px;
            border-radius: 4px;
        }
        .bg-blue { background: #0d6efd; }
        .bg-green { background: #198754; }
        .bg-yellow { background: #ffc107; color: #111827 !important; }
        .bg-red { background: #dc3545; }

        table.grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .grid th {
            background: #374151;
            color: #fff;
            font-size: 9px;
            padding: 6px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        .grid td {
            border: 1px solid #d1d5db;
            padding: 5px;
            font-size: 9px;
        }
        .grid tr:nth-child(even) {
            background: #f9fafb;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            margin-top: 14px;
            margin-bottom: 4px;
            color: #111827;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
            padding-top: 4px;
            background: #fff;
        }
        .assinaturas {
            margin-top: 24px;
            width: 100%;
            border-collapse: separate;
            border-spacing: 30px 0;
        }
        .assinaturas td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
        }
        .linha {
            margin-top: 40px;
            border-top: 1px solid #111827;
            padding-top: 5px;
            font-size: 9px;
        }
        @media print {
            body { margin: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $empresa->razao_social ?? $empresa->nome_fantasia ?? 'GestorNow' }}</h1>
        <h2>{{ mb_strtoupper($tituloDocumento) }}</h2>
        <div class="meta">
            Documento: {{ $locacao->numero_contrato }} | Emissão: {{ now()->format('d/m/Y H:i') }}
            @if(!empty($empresa?->cnpj)) | CNPJ: {{ $empresa->cnpj }} @endif
        </div>
    </div>

    <div class="info">
        <strong>Cliente:</strong> {{ $locacao->cliente->nome ?? $locacao->cliente->razao_social ?? '-' }}<br>
        @if(!empty($locacao->cliente?->cpf_cnpj))
            <strong>CPF/CNPJ:</strong> {{ $locacao->cliente->cpf_cnpj }}<br>
        @endif
        <strong>Período:</strong> {{ $periodoInicio }} {{ $locacao->hora_inicio ? 'às ' . $locacao->hora_inicio : '' }} até {{ $periodoFim }} {{ $locacao->hora_fim ? 'às ' . $locacao->hora_fim : '' }}<br>
        <strong>Status:</strong> {{ $statusLabel }}
        @if(!empty($locacao->local_entrega))
            <br><strong>Local:</strong> {{ $locacao->local_entrega }}
        @endif
    </div>

    <table class="cards">
        <tr>
            <td class="bg-blue">Itens<br>{{ $qtdItens }}</td>
            <td class="bg-green">Produtos<br>R$ {{ number_format($valorProdutos + $valorProdutosTerceiros, 2, ',', '.') }}</td>
            <td class="bg-yellow">Serviços/Despesas<br>R$ {{ number_format($valorServicos + $valorDespesas, 2, ',', '.') }}</td>
            <td class="bg-red">Total Final<br>R$ {{ number_format($valorFinal, 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="section-title">Itens da Locação</div>
    <table class="grid">
        <thead>
            <tr>
                <th>Item</th>
                <th>Sala</th>
                <th class="text-center">Qtd</th>
                <th class="text-right">Valor Unit.</th>
                <th class="text-right">Subtotal</th>
                @if($tipo === 'checklist')
                    <th class="text-center">Saída</th>
                    <th class="text-center">Retorno</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($locacao->produtos as $item)
                <tr>
                    <td>
                        {{ $item->produto->nome ?? 'Produto' }}
                        @if(!empty($item->patrimonio))
                            <br>Patrimônio: {{ $item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? ('PAT-' . $item->id_patrimonio) }}
                        @endif
                    </td>
                    <td>{{ $item->sala->nome ?? '-' }}</td>
                    <td class="text-center">{{ $item->quantidade ?? 1 }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item->preco_total ?? 0), 2, ',', '.') }}</td>
                    @if($tipo === 'checklist')
                        <td class="text-center">[ ]</td>
                        <td class="text-center">[ ]</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $tipo === 'checklist' ? 7 : 5 }}" class="text-center">Nenhum item próprio informado.</td>
                </tr>
            @endforelse

            @foreach($locacao->produtosTerceiros as $item)
                <tr>
                    <td>
                        {{ $item->nome_produto }}
                        @if(!empty($item->fornecedor?->nome))
                            <br>Fornecedor: {{ $item->fornecedor->nome }}
                        @endif
                    </td>
                    <td>{{ $item->sala->nome ?? '-' }}</td>
                    <td class="text-center">{{ $item->quantidade ?? 1 }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item->preco_unitario ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format((float) ($item->valor_total ?? 0), 2, ',', '.') }}</td>
                    @if($tipo === 'checklist')
                        <td class="text-center">[ ]</td>
                        <td class="text-center">[ ]</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($tipo === 'romaneio')
        <div class="section-title">Dados de Entrega</div>
        <table class="grid">
            <tbody>
                <tr>
                    <th style="width: 180px;">Responsável no local</th>
                    <td>{{ $locacao->contato_responsavel ?? $locacao->contato_local ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Telefone</th>
                    <td>{{ $locacao->telefone_responsavel ?? $locacao->telefone_contato ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Endereço de entrega</th>
                    <td>{{ $locacao->endereco_entrega ?? $locacao->local_entrega ?? '-' }}</td>
                </tr>
                <tr>
                    <th>Cidade/UF</th>
                    <td>{{ trim(($locacao->cidade ?? '') . ' / ' . ($locacao->estado ?? ''), ' /') ?: '-' }}</td>
                </tr>
                <tr>
                    <th>CEP</th>
                    <td>{{ $locacao->cep ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($tipo === 'entrega')
        <div class="section-title">Comprovante de Entrega</div>
        <table class="grid">
            <tbody>
                <tr>
                    <th style="width:220px;">Data Emissão</th>
                    <td>{{ now()->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                    <th>Período</th>
                    <td>{{ $periodoInicio }} {{ $locacao->hora_inicio ? 'às ' . $locacao->hora_inicio : '' }} até {{ $periodoFim }} {{ $locacao->hora_fim ? 'às ' . $locacao->hora_fim : '' }}</td>
                </tr>
                <tr>
                    <th>Contato no local</th>
                    <td>{{ $locacao->contato_local ?? $locacao->contato_responsavel ?? '-' }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($tipo === 'recibo')
        <div class="section-title">Recibo de Locação</div>
        <table class="grid">
            <tbody>
                <tr>
                    <td>
                        Recebemos de <strong>{{ $locacao->cliente->nome ?? $locacao->cliente->razao_social ?? '-' }}</strong>
                        o valor de <strong>R$ {{ number_format($valorFinal, 2, ',', '.') }}</strong>, referente ao contrato
                        <strong>{{ $locacao->numero_contrato }}</strong>.
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    @if(!empty($locacao->observacoes))
        <div class="section-title">Observações</div>
        <table class="grid">
            <tbody>
                <tr>
                    <td>{{ $locacao->observacoes }}</td>
                </tr>
            </tbody>
        </table>
    @endif

    @if(in_array($tipo, ['contrato', 'orcamento'], true))
        <div class="section-title">Condições Financeiras</div>
        <table class="grid">
            <tbody>
                <tr>
                    <th style="width: 220px;">Subtotal base</th>
                    <td class="text-right">R$ {{ number_format($valorBase, 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Desconto</th>
                    <td class="text-right">R$ {{ number_format((float) ($locacao->valor_desconto ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Acréscimo</th>
                    <td class="text-right">R$ {{ number_format((float) ($locacao->valor_acrescimo ?? 0), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Frete + Despesas Extras</th>
                    <td class="text-right">R$ {{ number_format(((float) ($locacao->valor_frete ?? 0) + (float) ($locacao->valor_despesas_extras ?? 0)), 2, ',', '.') }}</td>
                </tr>
                <tr>
                    <th>Valor Final</th>
                    <td class="text-right"><strong>R$ {{ number_format($valorFinal, 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    @if($tipo === 'contrato')
        <div class="section-title">Termos Básicos</div>
        <table class="grid">
            <tbody>
                <tr><td>1. O locatário declara que recebeu os itens em bom estado e se responsabiliza pela guarda durante o período da locação.</td></tr>
                <tr><td>2. Danos, perdas ou extravios poderão ser cobrados conforme valor de reposição vigente.</td></tr>
                <tr><td>3. Atrasos na devolução poderão gerar cobrança adicional proporcional ao período excedente.</td></tr>
            </tbody>
        </table>

        <table class="assinaturas">
            <tr>
                <td>
                    <div class="linha">
                        {{ $empresa->razao_social ?? $empresa->nome_fantasia ?? 'Locador' }}<br>
                        Locador
                    </div>
                </td>
                <td>
                    <div class="linha">
                        {{ $locacao->cliente->nome ?? $locacao->cliente->razao_social ?? 'Locatário' }}<br>
                        Locatário
                    </div>
                </td>
            </tr>
        </table>
    @endif

    <div class="footer">
        {{ $tituloDocumento }} • {{ $locacao->numero_contrato }} • Gerado por GestorNow em {{ now()->format('d/m/Y H:i') }}
    </div>
</body>
</html>
