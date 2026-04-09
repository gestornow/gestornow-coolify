<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Locação #{{ $locacao->numero_contrato }}</title>
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
        .contrato-numero {
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
        .termos {
            margin-top: 30px;
            font-size: 10px;
            text-align: justify;
        }
        .assinaturas {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .assinatura-box {
            width: 45%;
            text-align: center;
        }
        .assinatura-linha {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 10px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 10px;
            border-radius: 3px;
        }
        .badge-terceiro {
            background-color: #ffc107;
            color: #000;
        }
        .badge-sala {
            background-color: #17a2b8;
            color: #fff;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
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
    @endphp
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; cursor: pointer;">
            🖨️ Imprimir Contrato
        </button>
        <button onclick="window.close()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; margin-left: 10px;">
            ✖️ Fechar
        </button>
    </div>

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
        <p class="contrato-numero">CONTRATO DE LOCAÇÃO Nº {{ $locacao->numero_contrato }}</p>
    </div>

    <div class="section">
        <div class="section-title">DADOS DO CLIENTE (LOCATÁRIO)</div>
        @if($locacao->cliente)
            <div class="info-row">
                <span class="info-label">Nome/Razão Social:</span>
                <span class="info-value">{{ $locacao->cliente->nome }}</span>
            </div>
            @if($locacao->cliente->cpf_cnpj)
                <div class="info-row">
                    <span class="info-label">CPF/CNPJ:</span>
                    <span class="info-value">{{ $locacao->cliente->cpf_cnpj }}</span>
                </div>
            @endif
            @if($locacao->cliente->endereco)
                <div class="info-row">
                    <span class="info-label">Endereço:</span>
                    <span class="info-value">
                        {{ $locacao->cliente->endereco }}
                        @if($locacao->cliente->cidade), {{ $locacao->cliente->cidade }}@endif
                        @if($locacao->cliente->uf) - {{ $locacao->cliente->uf }}@endif
                        @if($locacao->cliente->cep) CEP: {{ $locacao->cliente->cep }}@endif
                    </span>
                </div>
            @endif
            @if($locacao->cliente->celular || $locacao->cliente->telefone)
                <div class="info-row">
                    <span class="info-label">Telefone:</span>
                    <span class="info-value">{{ $locacao->cliente->celular ?? $locacao->cliente->telefone }}</span>
                </div>
            @endif
            @if($locacao->cliente->email)
                <div class="info-row">
                    <span class="info-label">E-mail:</span>
                    <span class="info-value">{{ $locacao->cliente->email }}</span>
                </div>
            @endif
        @endif
    </div>

    <div class="section">
        <div class="section-title">PERÍODO DA LOCAÇÃO</div>
        <div class="info-row">
            <span class="info-label">Data/Hora de Saída:</span>
            <span class="info-value">
                {{ optional($locacao->data_inicio)->format('d/m/Y') }}
                @if($locacao->hora_saida) às {{ $locacao->hora_saida }}@endif
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Data/Hora de Retorno:</span>
            <span class="info-value">
                {{ optional($locacao->data_fim)->format('d/m/Y') }}
                @if($locacao->hora_retorno) às {{ $locacao->hora_retorno }}@endif
            </span>
        </div>
        <div class="info-row">
            <span class="info-label">Total de Dias:</span>
            <span class="info-value">{{ $locacao->total_dias ?? '-' }} dias</span>
        </div>
        @if($locacao->local_entrega)
            <div class="info-row">
                <span class="info-label">Local de Entrega:</span>
                <span class="info-value">{{ $locacao->local_entrega }}</span>
            </div>
        @endif
        @if($locacao->data_transporte_ida)
            <div class="info-row">
                <span class="info-label">Transporte Ida:</span>
                <span class="info-value">
                    {{ optional($locacao->data_transporte_ida)->format('d/m/Y') }}
                    @if($locacao->hora_transporte_ida) às {{ $locacao->hora_transporte_ida }}@endif
                </span>
            </div>
        @endif
        @if($locacao->data_transporte_volta)
            <div class="info-row">
                <span class="info-label">Transporte Volta:</span>
                <span class="info-value">
                    {{ optional($locacao->data_transporte_volta)->format('d/m/Y') }}
                    @if($locacao->hora_transporte_volta) às {{ $locacao->hora_transporte_volta }}@endif
                </span>
            </div>
        @endif
    </div>

    <div class="section">
        <div class="section-title">ITENS LOCADOS</div>
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Sala/Ambiente</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-right">Valor Unit.</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse($locacao->produtos as $produto)
                    <tr>
                        <td>
                            {{ $produto->produto->nome ?? $produto->descricao ?? 'Item' }}
                            @if(($produto->tipo_item ?? 'proprio') === 'terceiro')
                                <span class="badge badge-terceiro">Terceiro</span>
                            @endif
                            @if($produto->patrimonio)
                                <br><small>Patrimônio: {{ $produto->patrimonio->numero_serie ?? ('PAT-' . $produto->id_patrimonio) }}</small>
                            @endif
                        </td>
                        <td>
                            @if($produto->sala)
                                <span class="badge badge-sala">{{ $produto->sala->nome }}</span>
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">{{ $produto->quantidade }}</td>
                        <td class="text-right">R$ {{ number_format($produto->valor_unitario, 2, ',', '.') }}</td>
                        <td class="text-right">R$ {{ number_format($produto->valor_total, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Nenhum item</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($locacao->servicos && $locacao->servicos->count() > 0)
        <div class="section">
            <div class="section-title">SERVIÇOS ADICIONAIS</div>
            <table>
                <thead>
                    <tr>
                        <th>Descrição</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-right">Valor Unit.</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($locacao->servicos as $servico)
                        <tr>
                            <td>
                                {{ $servico->descricao }}
                                @if(($servico->tipo_item ?? 'proprio') === 'terceiro')
                                    <span class="badge badge-terceiro">Terceiro</span>
                                @endif
                            </td>
                            <td class="text-center">{{ $servico->quantidade }}</td>
                            <td class="text-right">R$ {{ number_format($servico->valor_unitario, 2, ',', '.') }}</td>
                            <td class="text-right">R$ {{ number_format($servico->valor_total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="totais">
        <table>
            <tr>
                <td>Subtotal Itens:</td>
                <td class="text-right">R$ {{ number_format($locacao->produtos->sum('valor_total'), 2, ',', '.') }}</td>
            </tr>
            @if($locacao->servicos && $locacao->servicos->sum('valor_total') > 0)
                <tr>
                    <td>Subtotal Serviços:</td>
                    <td class="text-right">R$ {{ number_format($locacao->servicos->sum('valor_total'), 2, ',', '.') }}</td>
                </tr>
            @endif
            @if($locacao->desconto > 0)
                <tr>
                    <td>Desconto:</td>
                    <td class="text-right">- R$ {{ number_format($locacao->desconto, 2, ',', '.') }}</td>
                </tr>
            @endif
            @if($locacao->taxa_entrega > 0)
                <tr>
                    <td>Taxa de Entrega:</td>
                    <td class="text-right">R$ {{ number_format($locacao->taxa_entrega, 2, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="total-final">
                <td><strong>VALOR TOTAL:</strong></td>
                <td class="text-right"><strong>R$ {{ number_format($locacao->valor_total, 2, ',', '.') }}</strong></td>
            </tr>
        </table>
    </div>

    @if($locacao->observacoes)
        <div class="section">
            <div class="section-title">OBSERVAÇÕES</div>
            <p>{{ $locacao->observacoes }}</p>
        </div>
    @endif

    <div class="termos">
        <div class="section-title">TERMOS E CONDIÇÕES</div>
        <p>
            1. O LOCATÁRIO se responsabiliza pela guarda, conservação e manutenção dos bens locados, obrigando-se a devolvê-los nas mesmas condições em que os recebeu, salvo o desgaste natural pelo uso normal.
        </p>
        <p>
            2. Em caso de danos, avarias, extravio ou furto dos bens locados, o LOCATÁRIO se obriga a ressarcir o LOCADOR pelo valor de mercado dos bens, independentemente de culpa ou dolo.
        </p>
        <p>
            3. O atraso na devolução dos bens acarretará multa diária equivalente ao valor da diária de locação, acrescida de juros de 1% ao mês.
        </p>
        <p>
            4. O LOCATÁRIO declara ter recebido os bens locados em perfeito estado de conservação e funcionamento.
        </p>
        <p>
            5. Fica eleito o foro da comarca de {{ $empresa->cidade ?? 'da sede do LOCADOR' }} para dirimir quaisquer dúvidas oriundas deste contrato.
        </p>
    </div>

    <div class="assinaturas">
        <div class="assinatura-box">
            <div class="assinatura-linha">
                <strong>{{ $empresa->razao_social ?? $empresa->nome_fantasia ?? 'LOCADOR' }}</strong><br>
                <small>LOCADOR</small>
            </div>
        </div>
        <div class="assinatura-box">
            <div class="assinatura-linha">
                <strong>{{ $locacao->cliente->nome ?? 'LOCATÁRIO' }}</strong><br>
                <small>LOCATÁRIO</small>
            </div>
        </div>
    </div>

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
