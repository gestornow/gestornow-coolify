<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Recibo Simplificado - {{ $numero_recibo }}</title>
    <style>
        @page {
            margin-top: 18mm;
            margin-right: 15mm;
            margin-bottom: 18mm;
            margin-left: 15mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1f97ea;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .logo {
            max-height: 56px;
            max-width: 200px;
            margin-bottom: 8px;
        }
        .titulo {
            font-size: 18px;
            font-weight: 700;
            color: #1f97ea;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .subtitulo {
            font-size: 12px;
            color: #4b5563;
        }
        .numero {
            text-align: right;
            margin-bottom: 10px;
            font-size: 11px;
            color: #334155;
        }
        .box {
            border: 1px solid #d9e2ec;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .box h3 {
            font-size: 12px;
            margin: 0 0 8px;
            color: #1f97ea;
            text-transform: uppercase;
        }
        .linha {
            margin-bottom: 4px;
        }
        .hash {
            word-break: break-all;
            font-family: 'Courier New', monospace;
            font-size: 9px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px;
        }
        .assinatura {
            margin-top: 14px;
            text-align: center;
            page-break-inside: avoid;
        }
        .assinatura .linha-assinatura {
            width: 60%;
            margin: 24px auto 0;
            border-top: 1px solid #334155;
            padding-top: 6px;
        }
        .rodape {
            margin-top: 12px;
            font-size: 9px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(!empty($logo_gestornow))
            <img class="logo" src="{{ $logo_gestornow }}" alt="Logo Gestor Now">
        @endif
        <div class="titulo">Recibo de Migração de Plano</div>
        <div class="subtitulo">Comprovante jurídico de aceite eletrônico</div>
    </div>

    <div class="numero">Recibo Nº {{ $numero_recibo }} | Emissão: {{ $data_emissao }}</div>

    <div class="box">
        <h3>Dados do Contratante</h3>
        <div class="linha"><strong>Razão Social / Nome:</strong> {{ $contrato->cliente_razao_social }}</div>
        <div class="linha"><strong>CNPJ / CPF:</strong> {{ $contrato->cnpj_cpf_formatado }}</div>
        <div class="linha"><strong>E-mail:</strong> {{ $contrato->cliente_email ?: '-' }}</div>
        <div class="linha"><strong>Endereço:</strong> {{ $contrato->cliente_endereco ?: '-' }}</div>
    </div>

    <div class="box">
        <h3>Dados da Migração</h3>
        <div class="linha"><strong>Plano:</strong> {{ $nome_plano }}</div>
        <div class="linha"><strong>Valor de Adesão da Troca:</strong> {{ $valor_adesao_formatado }}</div>
        <div class="linha"><strong>Mensalidade:</strong> {{ $valor_mensalidade_formatado }} / mês</div>
        <div class="linha"><strong>Data do Aceite:</strong> {{ $contrato->aceito_em_formatado }}</div>
        <div class="linha"><strong>Assinado por:</strong> {{ $contrato->assinado_por_nome }}</div>
        <div class="linha"><strong>Documento do Assinante:</strong> {{ $contrato->assinado_por_documento ?: '-' }}</div>
    </div>

    <div class="box">
        <h3>Validade Jurídica</h3>
        <div class="linha"><strong>IP de Origem:</strong> {{ $contrato->ip_aceite ?: '-' }}</div>
        <div class="linha"><strong>User-Agent:</strong> {{ $contrato->user_agent ?: '-' }}</div>
        <div class="linha"><strong>Versão do Termo:</strong> {{ $contrato->versao_contrato ?: '1.0' }}</div>
        <div class="linha"><strong>Hash de Integridade (SHA-256):</strong></div>
        <div class="hash">{{ $contrato->hash_documento }}</div>
    </div>

    <div class="assinatura">
        <div class="linha-assinatura">
            <div><strong>{{ $contrato->assinado_por_nome }}</strong></div>
            <div>{{ $contrato->assinado_por_documento ?: '-' }}</div>
        </div>
    </div>

    <div class="rodape">
        Este documento possui validade jurídica conforme Lei nº 14.063/2020 e MP nº 2.200-2/2001.
    </div>
</body>
</html>
