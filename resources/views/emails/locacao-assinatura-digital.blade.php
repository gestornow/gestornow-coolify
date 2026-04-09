<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Assinatura Digital do Contrato</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
        <tr>
            <td style="padding:22px 24px;border-bottom:1px solid #eef2f7;background:#fafbfc;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td style="vertical-align:middle;">
                            <div style="font-size:18px;font-weight:700;color:#0f172a;">{{ $empresaNome ?? 'GestorNow' }}</div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">Assinatura digital de contrato</div>
                        </td>
                        @if(!empty($empresaLogoUrl))
                            <td style="text-align:right;vertical-align:middle;">
                                <img src="{{ $empresaLogoUrl }}" alt="Logo {{ $empresaNome ?? 'Empresa' }}" style="max-height:56px;max-width:180px;display:inline-block;">
                            </td>
                        @endif
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <h2 style="margin:0 0 12px 0;font-size:28px;line-height:1.2;color:#1e293b;">Assinatura digital do contrato</h2>
                <p style="margin:0 0 12px 0;font-size:16px;line-height:1.6;">Olá {{ $cliente->nome ?? 'cliente' }},</p>
                <p style="margin:0 0 18px 0;font-size:17px;line-height:1.7;color:#334155;">
                    Seu contrato #{{ $locacao->numero_contrato ?? $locacao->id_locacao }} com a empresa {{ $empresaNome ?? 'Empresa' }} está disponível para assinatura digital.
                </p>

                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 12px 0;">
                    <tr>
                        <td>
                            <a href="{{ $urlContrato }}" style="display:inline-block;background:#2563eb;color:#fff;padding:11px 16px;text-decoration:none;border-radius:8px;font-weight:600;">
                                Visualizar contrato em PDF
                            </a>
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 0 18px 0;">
                    <tr>
                        <td>
                            <a href="{{ $urlAssinatura }}" style="display:inline-block;background:#059669;color:#fff;padding:11px 16px;text-decoration:none;border-radius:8px;font-weight:600;">
                                Assinar contrato agora
                            </a>
                        </td>
                    </tr>
                </table>

                <p style="margin:0;font-size:14px;line-height:1.6;color:#64748b;">Após a assinatura, o contrato ficará marcado como assinado digitalmente no sistema.</p>
            </td>
        </tr>
    </table>
</body>
</html>
