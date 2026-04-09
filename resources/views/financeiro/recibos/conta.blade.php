<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Recibo' }} #{{ $conta->id_contas }}</title>
    <style>
        @page { margin: 20px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #1f2937; font-size: 11px; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; vertical-align: middle; }
        .logo { max-height: 56px; max-width: 180px; }
        .header { border: 1px solid #d0d7e2; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: 700; text-align: center; margin: 4px 0 8px; }
        .meta { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .meta td { border: none; padding: 2px 0; vertical-align: top; }
        .box { border: 1px solid #d0d7e2; border-radius: 8px; padding: 10px; margin-bottom: 10px; line-height: 1.55; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid th, .grid td { border: 1px solid #334155; padding: 6px; }
        .grid th { background: #334155; color: #fff; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .signatures { width: 100%; border-collapse: collapse; margin-top: 26px; }
        .signatures td { width: 50%; text-align: center; border: none; }
        .signature-image { height: 58px; margin-bottom: 4px; }
        .signature-img { max-height: 58px; max-width: 220px; }
        .signature-placeholder { height: 58px; margin-bottom: 4px; }
        .line { border-top: 1px solid #111; margin-top: 42px; padding-top: 6px; }
        .footer { margin-top: 10px; text-align: center; color: #64748b; font-size: 9px; }
    </style>
</head>
<body>
    @php
        $empresaNome = $empresa->razao_social ?? $empresa->nome_empresa ?? 'Empresa';
        $empresaDocumento = $empresa->cnpj_formatado ?? $empresa->cpf_formatado ?? ($empresa->cnpj ?? $empresa->cpf ?? '-');
        $pessoaNome = $tipo === 'pagar'
            ? ($conta->fornecedor->nome ?? $conta->fornecedor->razao_social ?? '-')
            : ($conta->cliente->nome ?? $conta->cliente->razao_social ?? '-');
        $pessoaDocumento = $tipo === 'pagar'
            ? ($conta->fornecedor->cpf_cnpj ?? '-')
            : ($conta->cliente->cpf_cnpj ?? '-');
        $valorPagoTotal = (float) ($conta->valor_pago ?? 0);
        $valorOriginal = (float) ($conta->valor_total ?? 0);
        $dataReferencia = $conta->data_pagamento ?: optional($pagamentos->first())->data_pagamento;
        $dataReferenciaFormatada = $dataReferencia ? \Carbon\Carbon::parse($dataReferencia)->format('d/m/Y') : now()->format('d/m/Y');
        $cidadeUf = trim(($empresa->cidade ?? '') . ' - ' . ($empresa->uf ?? ''));
        $dataExtenso = now()->locale('pt_BR')->isoFormat('DD [de] MMMM [de] YYYY');
        $textoAcao = $tipo === 'pagar' ? 'Pagamos para' : 'Recebemos de';
        $textoRef = $tipo === 'pagar' ? 'pagamento da conta' : 'recebimento da conta';

        $logoSrc = \App\Helpers\PdfAssetHelper::resolveCompanyConfigImage($empresa, 'logo_url', true);
        $assinaturaSrc = \App\Helpers\PdfAssetHelper::resolveCompanyConfigImage($empresa, ['assinatura_locadora_url', 'assinatura_url']);
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td style="width: 28%;">
                    @if($logoSrc)
                        <img class="logo" src="{{ $logoSrc }}" alt="Logo da Empresa">
                    @endif
                </td>
                <td style="width: 44%;">
                    <div class="title">{{ strtoupper($titulo ?? 'RECIBO') }}</div>
                </td>
                <td style="width: 28%;"></td>
            </tr>
        </table>
        <table class="meta">
            <tr>
                <td style="width:50%;"><strong>Recibo Nº:</strong> #{{ $conta->id_contas }}</td>
                <td style="width:50%;" class="text-right"><strong>Data de Emissão:</strong> {{ now()->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td><strong>Empresa:</strong> {{ $empresaNome }}</td>
                <td class="text-right"><strong>Documento:</strong> {{ $empresaDocumento ?: '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="box">
        {{ $textoAcao }} <strong>{{ $pessoaNome }}</strong>, inscrito(a) no CPF/CNPJ <strong>{{ $pessoaDocumento }}</strong>,
        a importância total de <strong>R$ {{ number_format($valorPagoTotal, 2, ',', '.') }}</strong>,
        referente ao {{ $textoRef }} <strong>{{ $conta->descricao }}</strong>.
        <br><br>
        Valor original da conta: <strong>R$ {{ number_format($valorOriginal, 2, ',', '.') }}</strong><br>
        Data de referência: <strong>{{ $dataReferenciaFormatada }}</strong>
    </div>

    @if($pagamentos->count() > 0)
        <table class="grid">
            <thead>
                <tr>
                    <th class="text-center" style="width: 130px;">Data</th>
                    <th style="width: 190px;">Forma de Pagamento</th>
                    <th style="width: 180px;">Banco</th>
                    <th class="text-right" style="width: 120px;">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pagamentos as $item)
                    <tr>
                        <td class="text-center">{{ optional($item->data_pagamento)->format('d/m/Y') }}</td>
                        <td>{{ $item->formaPagamento->nome ?? '-' }}</td>
                        <td>{{ $item->banco->nome_banco ?? '-' }}</td>
                        <td class="text-right">R$ {{ number_format((float) $item->valor_pago, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="3" class="text-right"><strong>Total</strong></td>
                    <td class="text-right"><strong>R$ {{ number_format($valorPagoTotal, 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    <div class="box">
        Para todos os fins legais, declara-se {{ $tipo === 'pagar' ? 'realizado o pagamento' : 'confirmado o recebimento' }} do valor acima.
        <br><br>
        {{ $cidadeUf ?: 'Brasil' }}, {{ $dataExtenso }}.
    </div>

    <table class="signatures">
        <tr>
            <td>
                @if($assinaturaSrc)
                    <div class="signature-image">
                        <img class="signature-img" src="{{ $assinaturaSrc }}" alt="Assinatura Locadora">
                    </div>
                @endif
                <div class="line">
                    {{ $empresaNome }}<br>
                    {{ $tipo === 'pagar' ? 'Pagador' : 'Recebedor' }}
                </div>
            </td>
            <td>
                @if($assinaturaSrc)
                    <div class="signature-placeholder"></div>
                @endif
                <div class="line">
                    {{ $pessoaNome }}<br>
                    {{ $tipo === 'pagar' ? 'Favorecido' : 'Pagador' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">Emitido em {{ now()->format('d/m/Y H:i') }} • GestorNow</div>
</body>
</html>
