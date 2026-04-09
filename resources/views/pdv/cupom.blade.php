<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cupom Não Fiscal - Venda #{{ $venda->numero_venda }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.4;
            max-width: 80mm;
            margin: 0 auto;
            padding: 10px;
        }

        .cupom-header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }

        .empresa-nome {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .cupom-titulo {
            text-align: center;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .cupom-info {
            text-align: center;
            margin-bottom: 10px;
        }

        .divisor {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            font-weight: bold;
            border-bottom: 1px dashed #000;
            padding-bottom: 5px;
            margin-bottom: 5px;
        }

        .item {
            margin-bottom: 8px;
            padding-bottom: 5px;
        }

        .item-nome {
            font-weight: bold;
        }

        .item-detalhes {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }

        .totais {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }

        .total-linha {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }

        .total-final {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 1px dashed #000;
        }

        .cupom-footer {
            text-align: center;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #000;
            font-size: 11px;
        }

        .btn-imprimir {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
        }

        .btn-imprimir:hover {
            background: #218838;
        }

        @media print {
            .btn-imprimir {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="cupom-header">
        <div class="empresa-nome">{{ $empresa->nome_empresa ?? 'EMPRESA' }}</div>
        @if($empresa->endereco ?? false)
            <div>{{ $empresa->endereco }}</div>
        @endif
        @if($empresa->telefone ?? false)
            <div>Tel: {{ $empresa->telefone }}</div>
        @endif
        @if($empresa->cnpj ?? false)
            <div>CNPJ: {{ $empresa->cnpj }}</div>
        @endif
    </div>

    <div class="cupom-titulo">
        CUPOM NÃO FISCAL
    </div>

    <div class="cupom-info">
        <div>Venda #{{ $venda->numero_venda }}</div>
        <div>{{ $venda->data_venda->format('d/m/Y H:i:s') }}</div>
    </div>

    <div class="divisor"></div>

    <div class="item-header">
        <span>ITEM</span>
        <span>TOTAL</span>
    </div>

    @foreach($venda->itens as $item)
        <div class="item">
            <div class="item-nome">{{ $item->nome_produto }}</div>
            <div class="item-detalhes">
                <span>{{ $item->quantidade }} x R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</span>
                <span>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    <div class="totais">
        <div class="total-linha">
            <span>Subtotal:</span>
            <span>R$ {{ number_format($venda->subtotal, 2, ',', '.') }}</span>
        </div>

        @if($venda->desconto > 0)
            <div class="total-linha">
                <span>Desconto:</span>
                <span>- R$ {{ number_format($venda->desconto, 2, ',', '.') }}</span>
            </div>
        @endif

        @if($venda->acrescimo > 0)
            <div class="total-linha">
                <span>Acréscimo:</span>
                <span>+ R$ {{ number_format($venda->acrescimo, 2, ',', '.') }}</span>
            </div>
        @endif

        <div class="total-linha total-final">
            <span>TOTAL:</span>
            <span>R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
        </div>

        <div class="divisor"></div>

        <div class="total-linha">
            <span>Forma Pagamento:</span>
            <span>{{ $venda->formaPagamento->nome ?? 'Dinheiro' }}</span>
        </div>

        @if($venda->valor_recebido > 0)
            <div class="total-linha">
                <span>Valor Recebido:</span>
                <span>R$ {{ number_format($venda->valor_recebido, 2, ',', '.') }}</span>
            </div>
            <div class="total-linha">
                <span>Troco:</span>
                <span>R$ {{ number_format($venda->troco, 2, ',', '.') }}</span>
            </div>
        @endif
    </div>

    <div class="cupom-footer">
        <p>Obrigado pela preferência!</p>
        <p>Volte Sempre!</p>
        <br>
        <small>{{ $venda->data_venda->format('d/m/Y H:i:s') }}</small>
    </div>

    <button class="btn-imprimir" onclick="window.print()">
        🖨️ Imprimir Cupom
    </button>
</body>
</html>
