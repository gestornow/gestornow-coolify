<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Assinado #{{ $locacao->numero_contrato ?? $locacao->id_locacao }}</title>
    <style>
        @page {
            margin-top: 20mm;
            margin-right: 18mm;
            margin-bottom: 20mm;
            margin-left: 18mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            line-height: 1.5;
            color: #333;
            background: #fff;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .badge-assinado-topo {
            background: #10b981;
            color: #fff;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .badge-assinado-topo small {
            display: block;
            font-weight: normal;
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.9;
        }

        /* Container do contrato armazenado */
        .contrato-espelho {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 0;
            margin-bottom: 20px;
            background: #fff;
            overflow: hidden;
        }
        
        .contrato-espelho-inner {
            padding: 0;
        }

        .validade-juridica {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
            border-left: 4px solid #10b981;
        }

        .validade-juridica h4 {
            font-size: 11px;
            color: #10b981;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .validade-info {
            font-size: 9px;
            color: #666;
            line-height: 1.6;
        }

        .validade-info strong {
            color: #333;
        }

        .hash-documento {
            font-family: 'Courier New', monospace;
            font-size: 8px;
            background: #fff;
            padding: 8px;
            border-radius: 3px;
            margin-top: 8px;
            word-break: break-all;
            color: #666;
            border: 1px solid #e0e0e0;
        }

        .assinatura-area {
            margin-top: 25px;
            text-align: center;
        }

        .assinatura-imagem {
            max-width: 200px;
            max-height: 80px;
            margin: 0 auto 10px;
        }

        .assinatura-linha {
            width: 60%;
            margin: 0 auto;
            border-top: 1px solid #333;
            padding-top: 8px;
        }

        .assinatura-nome {
            font-weight: bold;
            font-size: 11px;
        }

        .assinatura-doc {
            font-size: 10px;
            color: #666;
        }

        .footer {
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
        }

        .footer p {
            font-size: 9px;
            color: #888;
            margin-bottom: 4px;
        }

        .no-print {
            margin-bottom: 20px;
            text-align: center;
        }

        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
            }
        }

        /* ========== FALLBACK: Estilos para quando não há corpo armazenado ========== */
        .header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px solid #1f97ea;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 20px;
            color: #1f97ea;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .header .subtitulo {
            font-size: 12px;
            color: #666;
        }

        .header .logo {
            max-height: 50px;
            margin-bottom: 10px;
        }

        .badge-assinado {
            display: inline-block;
            background: #10b981;
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-top: 10px;
        }

        .numero-contrato {
            text-align: right;
            margin-bottom: 15px;
        }

        .numero-contrato span {
            background: #f0f7ff;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            color: #1f97ea;
            font-size: 11px;
        }

        .section {
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1f97ea;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 4px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .dados-grid {
            display: table;
            width: 100%;
        }

        .dados-row {
            display: table-row;
        }

        .dados-label {
            display: table-cell;
            width: 35%;
            padding: 4px 8px 4px 0;
            font-weight: bold;
            color: #555;
            vertical-align: top;
        }

        .dados-valor {
            display: table-cell;
            padding: 4px 0;
            color: #333;
        }

        .tabela-produtos {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
        }

        .tabela-produtos th,
        .tabela-produtos td {
            padding: 6px 10px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
            font-size: 10px;
        }

        .tabela-produtos th {
            background: #f8f9fa;
            color: #555;
            font-weight: bold;
            text-transform: uppercase;
        }

        .text-right {
            text-align: right;
        }

        .clausula-box {
            margin-top: 10px;
            border: 1px solid #bfd3f2;
            border-radius: 4px;
            overflow: hidden;
        }

        .clausula-head {
            background: #eaf1fb;
            color: #0f3f7d;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 10px;
            text-transform: uppercase;
        }

        .clausula-body {
            padding: 8px 10px;
            font-size: 10px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print">
            <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; background: #1f97ea; color: #fff; border: none; border-radius: 4px;">
                🖨️ Imprimir
            </button>
            <button onclick="window.close()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; margin-left: 10px; background: #6c757d; color: #fff; border: none; border-radius: 4px;">
                ✖️ Fechar
            </button>
        </div>

        {{-- Badge de Documento Assinado no Topo --}}
        <div class="badge-assinado-topo">
            ✓ DOCUMENTO ASSINADO DIGITALMENTE
            <small>Assinado em {{ optional($assinatura->assinado_em)->format('d/m/Y H:i:s') ?? '-' }}</small>
        </div>

        @if(!empty($assinatura->corpo_contrato_assinado) && strlen($assinatura->corpo_contrato_assinado) > 100)
            {{-- ================================================= --}}
            {{-- ESPELHO DO CONTRATO: HTML armazenado no momento da assinatura --}}
            {{-- Este conteúdo é IMUTÁVEL e representa exatamente o que foi assinado --}}
            {{-- ================================================= --}}
            <div class="contrato-espelho">
                <div class="contrato-espelho-inner">
                    {!! $assinatura->corpo_contrato_assinado !!}
                </div>
            </div>
        @else
            {{-- ================================================= --}}
            {{-- FALLBACK: Exibição padrão quando não há HTML armazenado --}}
            {{-- ================================================= --}}
            <div class="header">
                @if(!empty($logoSrc))
                <img src="{{ $logoSrc }}" alt="Logo" class="logo">
                @endif
                <h1>Contrato de Locação Assinado</h1>
                <div class="subtitulo">{{ $empresa->razao_social ?? $empresa->nome_fantasia ?? 'Empresa' }}</div>
                <div class="badge-assinado">✓ DOCUMENTO ASSINADO DIGITALMENTE</div>
            </div>

            <div class="numero-contrato">
                <span>Contrato Nº {{ $locacao->numero_contrato ?? $locacao->id_locacao }}</span>
            </div>

            <!-- Dados do Cliente -->
            <div class="section">
                <div class="section-title">Dados do Locatário</div>
                <div class="dados-grid">
                    <div class="dados-row">
                        <div class="dados-label">Nome / Razão Social:</div>
                        <div class="dados-valor">{{ $cliente->nome ?? $cliente->razao_social ?? '-' }}</div>
                    </div>
                    <div class="dados-row">
                        <div class="dados-label">CPF / CNPJ:</div>
                        <div class="dados-valor">{{ $cliente->cpf_cnpj ?? '-' }}</div>
                    </div>
                    @if($cliente->email)
                    <div class="dados-row">
                        <div class="dados-label">E-mail:</div>
                        <div class="dados-valor">{{ $cliente->email }}</div>
                    </div>
                    @endif
                    @if($cliente->endereco)
                    <div class="dados-row">
                        <div class="dados-label">Endereço:</div>
                        <div class="dados-valor">
                            {{ $cliente->endereco }}
                            @if($cliente->cidade), {{ $cliente->cidade }}@endif
                            @if($cliente->uf) - {{ $cliente->uf }}@endif
                            @if($cliente->cep) CEP: {{ $cliente->cep }}@endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Período da Locação -->
            <div class="section">
                <div class="section-title">Período da Locação</div>
                <div class="dados-grid">
                    <div class="dados-row">
                        <div class="dados-label">Data/Hora de Início:</div>
                        <div class="dados-valor">
                            {{ optional($locacao->data_inicio)->format('d/m/Y') ?? '-' }}
                            @if($locacao->hora_inicio) às {{ $locacao->hora_inicio }}@endif
                        </div>
                    </div>
                    <div class="dados-row">
                        <div class="dados-label">Data/Hora de Término:</div>
                        <div class="dados-valor">
                            {{ optional($locacao->data_fim)->format('d/m/Y') ?? '-' }}
                            @if($locacao->hora_fim) às {{ $locacao->hora_fim }}@endif
                        </div>
                    </div>
                    <div class="dados-row">
                        <div class="dados-label">Total de Dias:</div>
                        <div class="dados-valor">{{ $locacao->quantidade_dias ?? '-' }} dia(s)</div>
                    </div>
                </div>
            </div>

            <!-- Itens Locados -->
            <div class="section">
                <div class="section-title">Itens Locados</div>
                <table class="tabela-produtos">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-right">Qtd</th>
                            <th class="text-right">Valor Unit.</th>
                            <th class="text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locacao->produtos as $produto)
                        <tr>
                            <td>{{ $produto->produto->nome ?? 'Item' }}</td>
                            <td class="text-right">{{ $produto->quantidade ?? 1 }}</td>
                            <td class="text-right">R$ {{ number_format($produto->preco_unitario ?? 0, 2, ',', '.') }}</td>
                            <td class="text-right">R$ {{ number_format($produto->preco_total ?? 0, 2, ',', '.') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" style="text-align: center;">Nenhum item</td>
                        </tr>
                        @endforelse
                        <tr style="background: #f8f9fa; font-weight: bold;">
                            <td colspan="3" class="text-right">VALOR TOTAL:</td>
                            <td class="text-right">R$ {{ number_format($locacao->valor_total ?? 0, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Cláusulas Básicas (Fallback) -->
            <div class="section">
                <div class="section-title">Cláusulas Contratuais</div>
                
                <div class="clausula-box">
                    <div class="clausula-head">CLÁUSULA I - DO PRAZO CONTRATUAL</div>
                    <div class="clausula-body">
                        O objeto deste contrato refere-se à locação dos itens relacionados, pelo período de {{ $locacao->quantidade_dias ?? '-' }} dia(s), 
                        com início em {{ optional($locacao->data_inicio)->format('d/m/Y') }} às {{ $locacao->hora_inicio ?? '00:00' }} 
                        e término em {{ optional($locacao->data_fim)->format('d/m/Y') }} às {{ $locacao->hora_fim ?? '23:59' }}.
                    </div>
                </div>

                <div class="clausula-box">
                    <div class="clausula-head">CLÁUSULA II - DA CONSERVAÇÃO DOS BENS</div>
                    <div class="clausula-body">
                        A LOCATÁRIA se responsabiliza pela guarda e conservação dos bens durante toda a locação, comprometendo-se a devolvê-los em condições normais de uso.
                    </div>
                </div>

                <div class="clausula-box">
                    <div class="clausula-head">CLÁUSULA III - DO VALOR</div>
                    <div class="clausula-body">
                        Valor total pactuado: R$ {{ number_format($locacao->valor_total ?? 0, 2, ',', '.') }}.
                    </div>
                </div>
            </div>
        @endif

        <!-- Informações de Validade Jurídica -->
        <div class="validade-juridica">
            <h4>✓ Informações de Autenticidade e Validade Jurídica</h4>
            <div class="validade-info">
                <p><strong>Data do Aceite:</strong> {{ optional($assinatura->assinado_em)->format('d/m/Y H:i:s') ?? '-' }}</p>
                <p><strong>Assinado por:</strong> {{ $assinatura->assinado_por_nome ?? $cliente->nome ?? '-' }}</p>
                <p><strong>Documento:</strong> {{ $assinatura->assinado_por_documento ?? $cliente->cpf_cnpj ?? '-' }}</p>
                <p><strong>IP de Origem:</strong> {{ $assinatura->ip_assinatura ?? '-' }}</p>
                <p><strong>User Agent:</strong> {{ Str::limit($assinatura->user_agent ?? '-', 80) }}</p>
            </div>
            @if($assinatura->hash_documento)
            <div class="hash-documento">
                <strong>Hash de Integridade (SHA-256):</strong><br>
                {{ $assinatura->hash_documento }}
            </div>
            @endif
        </div>

        <!-- Área de Assinatura -->
        <div class="assinatura-area">
            @if(!empty($assinatura->assinatura_cliente_url))
            <img src="{{ $assinatura->assinatura_cliente_url }}" alt="Assinatura" class="assinatura-imagem">
            @endif
            <div class="assinatura-linha">
                <div class="assinatura-nome">{{ $assinatura->assinado_por_nome ?? $cliente->nome ?? 'Locatário' }}</div>
                <div class="assinatura-doc">{{ $assinatura->assinado_por_documento ?? $cliente->cpf_cnpj ?? '' }}</div>
            </div>
        </div>

        <div class="footer">
            <p>Este documento possui validade jurídica conforme Lei nº 14.063/2020 e MP nº 2.200-2/2001.</p>
            <p>O hash SHA-256 garante a integridade e autenticidade deste documento.</p>
            <p>Documento assinado digitalmente em {{ optional($assinatura->assinado_em)->format('d/m/Y H:i:s') ?? '-' }}</p>
        </div>
    </div>
</body>
</html>
