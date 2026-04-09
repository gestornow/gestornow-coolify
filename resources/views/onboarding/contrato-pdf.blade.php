<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contrato->titulo_contrato }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
            line-height: 1.5;
            margin: 20px;
        }

        .header {
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }

        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #111827;
        }

        .header .meta {
            margin-top: 6px;
            color: #4b5563;
            font-size: 11px;
        }

        .section-title {
            margin: 16px 0 8px;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
        }

        .contract-body {
            border: 1px solid #e5e7eb;
            padding: 14px;
            border-radius: 4px;
            white-space: pre-wrap;
            background: #f9fafb;
        }

        .signatures {
            margin-top: 26px;
            width: 100%;
        }

        .signature-card {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 12px;
        }

        .signature-card h3 {
            margin: 0 0 10px;
            font-size: 12px;
            text-transform: uppercase;
            color: #374151;
        }

        .signature-text {
            font-size: 18px;
            font-family: "Times New Roman", serif;
            font-style: italic;
            margin: 6px 0 10px;
            color: #111827;
        }

        .signature-meta {
            font-size: 11px;
            color: #4b5563;
        }

        .footer {
            margin-top: 16px;
            font-size: 10px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $contrato->titulo_contrato }}</h1>
        <div class="meta">
            <div>Versao: {{ $contrato->versao_contrato }}</div>
            <div>Hash do documento: {{ $contrato->hash_documento }}</div>
        </div>
    </div>

    <div class="section-title">Dados da empresa contratante</div>
    <div>
        <strong>{{ $empresa->razao_social ?: $empresa->nome_empresa }}</strong><br>
        Documento: {{ $empresa->cnpj ?: $empresa->cpf ?: '-' }}<br>
        E-mail: {{ $empresa->email ?: '-' }}
    </div>

    <div class="section-title">Corpo do contrato</div>
    <div class="contract-body">{{ $contrato->corpo_contrato }}</div>

    <div class="section-title">Assinaturas</div>
    <div class="signatures">
        <div class="signature-card">
            <h3>Assinatura do cliente</h3>
            <div class="signature-text">{{ $assinaturaClienteTexto ?: $contrato->assinado_por_nome }}</div>
            <div class="signature-meta">
                Nome: {{ $contrato->assinado_por_nome }}<br>
                Documento: {{ $contrato->assinado_por_documento }}<br>
                Data/Hora: {{ $contrato->assinado_em ? $contrato->assinado_em->format('d/m/Y H:i:s') : '-' }}<br>
                IP da assinatura: {{ $contrato->assinatura_ip ?: '-' }}
            </div>
        </div>

        <div class="signature-card">
            <h3>Assinatura da filial 1</h3>
            <div class="signature-text">{{ $assinaturaFilialTexto }}</div>
            <div class="signature-meta">
                Nome: {{ $nomeFilialAssinante }}<br>
                Documento: {{ $documentoFilialAssinante ?: '-' }}<br>
                Data/Hora: {{ $dataFilialAssinante ? $dataFilialAssinante->format('d/m/Y H:i:s') : '-' }}
            </div>
        </div>
    </div>

    <div class="footer">
        Documento gerado automaticamente em {{ now()->format('d/m/Y H:i:s') }}.
    </div>
</body>
</html>
