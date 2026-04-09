<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Adesão - {{ $numero_recibo }}</title>
    <style>
        @page {
            margin: 16mm;
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
            margin: 0;
            padding: 0;
        }

        .container {
            width: auto;
            max-width: none;
            margin: 0 7mm;
            padding: 0;
        }

        /* Cabeçalho */
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #1f97ea;
            margin-bottom: 25px;
        }

        .header h1 {
            font-size: 22px;
            color: #1f97ea;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .header .subtitulo {
            font-size: 14px;
            color: #666;
        }

        .header .logo {
            max-height: 60px;
            margin-bottom: 10px;
        }

        /* Número do Recibo */
        .numero-recibo {
            text-align: right;
            margin-bottom: 20px;
        }

        .numero-recibo span {
            background: #f0f7ff;
            padding: 8px 15px;
            border-radius: 4px;
            font-weight: bold;
            color: #1f97ea;
            font-size: 12px;
        }

        /* Seções */
        .section {
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #1f97ea;
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 5px;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        /* Dados em grid */
        .dados-grid {
            display: table;
            width: 100%;
        }

        .dados-row {
            display: table-row;
        }

        .dados-label {
            display: table-cell;
            width: 32%;
            padding: 5px 10px 5px 0;
            font-weight: bold;
            color: #555;
            vertical-align: top;
            word-break: break-word;
        }

        .dados-valor {
            display: table-cell;
            padding: 5px 0;
            color: #333;
            word-break: break-word;
            word-wrap: break-word;
        }

        /* Tabela de limites */
        .tabela-limites {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 8px 0;
        }

        .tabela-limites th,
        .tabela-limites td {
            padding: 8px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .tabela-limites th {
            background: #f8f9fa;
            color: #555;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .tabela-limites td {
            font-size: 11px;
            word-break: break-word;
            word-wrap: break-word;
        }

        .tabela-limites tr:last-child td {
            border-bottom: none;
        }

        .tabela-limites tr:nth-child(even) {
            background: #fafafa;
        }

        /* Informações de validade jurídica */
        .validade-juridica {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-top: 12px;
            border-left: 4px solid #1f97ea;
            page-break-inside: avoid;
        }

        .validade-juridica h4 {
            font-size: 11px;
            color: #555;
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
            page-break-inside: avoid;
        }

        /* Rodapé */
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
        }

        .footer p {
            font-size: 9px;
            color: #888;
            margin-bottom: 5px;
        }

        .assinatura-area {
            margin-top: 20px;
            text-align: center;
            page-break-inside: avoid;
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

        /* Imagem da assinatura */
        .assinatura-imagem {
            max-width: 200px;
            max-height: 80px;
            margin: 0 auto 10px;
        }

        .assinatura-texto {
            display: inline-block;
            min-width: 220px;
            max-width: 70%;
            margin: 0 auto 10px;
            padding: 8px 12px;
            border: 1px solid #c9d2dd;
            border-radius: 6px;
            font-size: 15px;
            font-style: italic;
            color: #2f3349;
            background: #fff;
        }

        /* Data e local */
        .data-local {
            text-align: right;
            margin-top: 16px;
            font-size: 11px;
            color: #555;
        }

        .clausulas-aceitas {
            margin-top: 12px;
            padding: 0;
            page-break-inside: auto;
        }

        .clausulas-aceitas .titulo {
            font-size: 10px;
            font-weight: bold;
            color: #0f3f7d;
            margin-bottom: 8px;
            text-transform: uppercase;
            page-break-after: avoid;
        }

        .clausula-box {
            margin-top: 8px;
            border: 1px solid #bfd3f2;
            border-radius: 4px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .clausula-head {
            background: #eaf1fb;
            color: #0f3f7d;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 8px;
            text-transform: uppercase;
        }

        .clausula-body {
            background: #fff;
            padding: 8px;
            font-size: 9.5px;
            line-height: 1.45;
            color: #334155;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .section {
            page-break-inside: avoid;
        }

        .tabela-limites tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }

        /* Print styles */
        @media print {
            body {
                padding: 0;
            }
            .container {
                width: auto;
                max-width: none;
                margin: 0 7mm;
                padding: 0;
            }
        }

        /* Duas colunas para limites */
        .limites-grid {
            display: table;
            width: 100%;
        }

        .limites-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }

        .limites-col:last-child {
            padding-right: 0;
            padding-left: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Cabeçalho -->
        <div class="header">
            @if(!empty($logo_gestornow))
            <img src="{{ $logo_gestornow }}" alt="Logo da Prestadora" class="logo">
            @endif
            @if(!empty($eh_troca_plano) && $eh_troca_plano)
            <h1>Recibo de Troca de Plano</h1>
            <div class="subtitulo">{{ ucfirst($tipo_troca ?? 'Migração') }} de Plano - Licenciamento de Software SaaS</div>
            @else
            <h1>Recibo de Serviço Prestado</h1>
            <div class="subtitulo">Taxa de Adesão - Licenciamento de Software SaaS</div>
            @endif
        </div>

        <!-- Número do Recibo -->
        <div class="numero-recibo">
            <span>Nº {{ $numero_recibo }}</span>
        </div>

        <!-- Dados do Cliente -->
        <div class="section">
            <div class="section-title">Dados do Cliente</div>
            <div class="dados-grid">
                <div class="dados-row">
                    <div class="dados-label">Razão Social / Nome:</div>
                    <div class="dados-valor">{{ $cliente['razao_social'] }}</div>
                </div>
                <div class="dados-row">
                    <div class="dados-label">CNPJ / CPF:</div>
                    <div class="dados-valor">{{ $cliente['cnpj_cpf'] }}</div>
                </div>
                @if($cliente['email'])
                <div class="dados-row">
                    <div class="dados-label">E-mail:</div>
                    <div class="dados-valor">{{ $cliente['email'] }}</div>
                </div>
                @endif
                @if($cliente['endereco'])
                <div class="dados-row">
                    <div class="dados-label">Endereço:</div>
                    <div class="dados-valor">{{ $cliente['endereco'] }}</div>
                </div>
                @endif
            </div>
        </div>

        <!-- Plano Contratado -->
        <div class="section">
            @if(!empty($eh_troca_plano) && $eh_troca_plano)
            <div class="section-title">Detalhes da Troca de Plano</div>
            @else
            <div class="section-title">Plano Contratado</div>
            @endif
            <div class="dados-grid">
                @if(!empty($eh_troca_plano) && $eh_troca_plano)
                <div class="dados-row">
                    <div class="dados-label">Tipo da Operação:</div>
                    <div class="dados-valor"><strong style="color:#1f97ea;">{{ strtoupper($tipo_troca ?? 'MIGRAÇÃO') }}</strong></div>
                </div>
                @endif
                <div class="dados-row">
                    <div class="dados-label">{{ (!empty($eh_troca_plano) && $eh_troca_plano) ? 'Novo Plano:' : 'Plano:' }}</div>
                    <div class="dados-valor"><strong>{{ $plano['nome'] }}</strong></div>
                </div>
                @if(!empty($eh_troca_plano) && $eh_troca_plano && !empty($valor_mensal_anterior))
                <div class="dados-row">
                    <div class="dados-label">Mensalidade Anterior:</div>
                    <div class="dados-valor"><span style="text-decoration:line-through;color:#888;">{{ $valor_mensal_anterior }}</span></div>
                </div>
                @endif
                <div class="dados-row">
                    <div class="dados-label">{{ (!empty($eh_troca_plano) && $eh_troca_plano) ? 'Nova Mensalidade:' : 'Mensalidade:' }}</div>
                    <div class="dados-valor">{{ $valor_mensalidade_formatado }} / mês</div>
                </div>
                @if($valor_adesao > 0)
                <div class="dados-row">
                    <div class="dados-label">{{ (!empty($eh_troca_plano) && $eh_troca_plano) ? 'Taxa de Migração:' : 'Taxa de Adesão:' }}</div>
                    <div class="dados-valor"><strong>{{ $valor_adesao_formatado }}</strong> <span style="font-size:10px;color:#667;">({{ $valor_extenso }})</span></div>
                </div>
                @endif
            </div>
        </div>

        <!-- Limites do Plano -->
        <div class="section">
            <div class="section-title">Recursos e Limites Contratados</div>
            <table class="tabela-limites">
                <thead>
                    <tr>
                        <th>Recursos do Plano</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recursos_plano as $recurso)
                    <tr>
                        <td>{{ $recurso }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Informações de Validade Jurídica -->
        <div class="validade-juridica">
            <h4>Informações de Autenticidade e Validade Jurídica</h4>
            <div class="validade-info">
                <p><strong>Data do Aceite:</strong> {{ $data_aceite }}</p>
                <p><strong>Aceito por:</strong> {{ $assinado_por }}</p>
                <p><strong>IP de Origem:</strong> {{ $ip_aceite }}</p>
                <p><strong>Data de Emissão:</strong> {{ $data_emissao }}</p>
            </div>
            <div class="hash-documento">
                <strong>Hash de Integridade (SHA-256):</strong><br>
                {{ $hash_documento }}
            </div>
        </div>

        @if(!empty($clausulas_aceitas))
        @php
            $textoClausulas = trim((string) $clausulas_aceitas);
            $textoClausulas = preg_replace('/\r\n?/', "\n", $textoClausulas) ?? $textoClausulas;
            $textoClausulas = preg_replace('/\n{3,}/', "\n\n", $textoClausulas) ?? $textoClausulas;

            $blocosRaw = collect(preg_split('/\n\s*\n/', $textoClausulas))
                ->map(fn ($bloco) => trim((string) $bloco))
                ->filter()
                ->values();

            if ($blocosRaw->isEmpty() && $textoClausulas !== '') {
                $blocosRaw = collect([$textoClausulas]);
            }

            $blocosClausulas = $blocosRaw->map(function ($bloco) {
                $linhas = collect(preg_split('/\n+/', (string) $bloco))
                    ->map(fn ($linha) => trim((string) $linha))
                    ->filter()
                    ->values();

                if ($linhas->isEmpty()) {
                    return null;
                }

                $titulo = '';
                $corpo = '';

                if ($linhas->count() > 1) {
                    $titulo = (string) $linhas->first();
                    $corpo = (string) $linhas->slice(1)->implode("\n");
                } else {
                    $linha = (string) $linhas->first();

                    if (preg_match('/^(CL[ÁA]USULA\s+[A-Z0-9IVXLCM\-\s]+)[:\-\.\s]+(.+)$/iu', $linha, $match)) {
                        $titulo = trim((string) ($match[1] ?? 'CLÁUSULA'));
                        $corpo = trim((string) ($match[2] ?? ''));
                    } else {
                        $titulo = 'CLÁUSULA';
                        $corpo = $linha;
                    }
                }

                if (str_contains($corpo, '|')) {
                    $partesCorpo = collect(explode('|', $corpo))
                        ->map(fn ($parte) => trim((string) $parte))
                        ->filter()
                        ->values();
                    $corpo = $partesCorpo->implode("\n");
                }

                $titulo = $titulo !== '' ? $titulo : 'CLÁUSULA';
                $corpo = $corpo !== '' ? $corpo : '-';

                return [
                    'titulo' => $titulo,
                    'corpo' => $corpo,
                ];
            })->filter()->values();
        @endphp
        @if($blocosClausulas->isNotEmpty())
        <div class="clausulas-aceitas">
            <div class="titulo">Cláusulas Aceitas - {{ $titulo_contrato }} (v{{ $versao_contrato }})</div>
            @foreach($blocosClausulas as $blocoClausula)
                <div class="clausula-box">
                    <div class="clausula-head">{{ $blocoClausula['titulo'] }}</div>
                    <div class="clausula-body">{!! nl2br(e($blocoClausula['corpo'])) !!}</div>
                </div>
            @endforeach
        </div>
        @endif
        @endif

        <!-- Área de Assinatura -->
        <div class="assinatura-area">
            @if(($assinatura['tipo'] ?? '') === 'imagem' && !empty($assinatura['valor']))
            <img src="{{ $assinatura['valor'] }}" alt="Assinatura" class="assinatura-imagem">
            @elseif(($assinatura['tipo'] ?? '') === 'texto' && !empty($assinatura['valor']))
            <div class="assinatura-texto">{{ $assinatura['valor'] }}</div>
            @endif
            <div class="assinatura-linha">
                <div class="assinatura-nome">{{ $contrato->assinado_por_nome }}</div>
                <div class="assinatura-doc">{{ $contrato->assinado_por_documento ?: $cliente['cnpj_cpf'] }}</div>
            </div>
        </div>

        <!-- Data e Local -->
        <div class="data-local">
            <p>Documento gerado eletronicamente em {{ $data_emissao }}</p>
        </div>

        <!-- Rodapé -->
        <div class="footer">
            <p>Este documento possui validade jurídica conforme Lei nº 14.063/2020 e MP nº 2.200-2/2001.</p>
            <p>O hash SHA-256 garante a integridade e autenticidade deste documento.</p>
            <p>Gestor Now - Sistema de Gestão Empresarial</p>
        </div>
    </div>
</body>
</html>
