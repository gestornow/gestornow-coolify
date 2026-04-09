<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Troca de Produto</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 10px; margin-bottom: 16px; }
        .titulo { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
        .subtitulo { color: #4b5563; font-size: 11px; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid td { padding: 8px; border: 1px solid #d1d5db; vertical-align: top; }
        .label { font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; }
        .valor { font-size: 12px; margin-top: 3px; }
        .bloco { margin-top: 12px; }
        .rodape { margin-top: 24px; font-size: 10px; color: #6b7280; }
        .assinatura { margin-top: 40px; }
        .assinatura-linha { border-top: 1px solid #9ca3af; width: 280px; padding-top: 6px; }
        .logo { height: 48px; }
    </style>
</head>
<body>
    <div class="header">
        <table width="100%" cellspacing="0" cellpadding="0">
            <tr>
                <td>
                    <div class="titulo">Comprovante de Troca de Produto</div>
                    <div class="subtitulo">Gerado em {{ optional($geradoEm)->format('d/m/Y H:i') }}</div>
                </td>
                <td align="right">
                    @if(!empty($logoEmpresaDataUri))
                        <img src="{{ $logoEmpresaDataUri }}" class="logo" alt="Logo">
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <tr>
            <td>
                <div class="label">Empresa</div>
                <div class="valor">{{ $empresa->nome_fantasia ?? $empresa->razao_social ?? '-' }}</div>
            </td>
            <td>
                <div class="label">Contrato</div>
                <div class="valor">#{{ $troca->locacao->codigo_display ?? $troca->locacao->numero_contrato ?? '-' }}</div>
            </td>
            <td>
                <div class="label">Cliente</div>
                <div class="valor">{{ $troca->locacao->cliente->nome ?? '-' }}</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="label">Data da troca</div>
                <div class="valor">{{ optional($troca->created_at)->format('d/m/Y H:i') }}</div>
            </td>
            <td>
                <div class="label">Usuário responsável</div>
                <div class="valor">{{ $troca->usuario->nome ?? '-' }}</div>
            </td>
            <td>
                <div class="label">Quantidade</div>
                <div class="valor">{{ (int) ($troca->quantidade ?? 1) }}</div>
            </td>
        </tr>
    </table>

    <div class="bloco">
        <table class="grid">
            <tr>
                <td width="50%">
                    <div class="label">Produto removido</div>
                    <div class="valor"><strong>{{ $troca->produtoAnterior->nome ?? '-' }}</strong></div>
                    <div class="subtitulo">Código: {{ $troca->produtoAnterior->codigo ?? '-' }}</div>
                    <div class="subtitulo">Patrimônio: {{ $troca->patrimonio_anterior_troca ?? '-' }}</div>
                </td>
                <td width="50%">
                    <div class="label">Produto inserido</div>
                    <div class="valor"><strong>{{ $troca->produtoNovo->nome ?? '-' }}</strong></div>
                    <div class="subtitulo">Código: {{ $troca->produtoNovo->codigo ?? '-' }}</div>
                    <div class="subtitulo">Patrimônio: {{ $troca->patrimonio_novo_troca ?? '-' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="bloco">
        <table class="grid">
            <tr>
                <td>
                    <div class="label">Motivo</div>
                    <div class="valor">{{ $troca->motivo ?: '-' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Observações</div>
                    <div class="valor">{{ $troca->observacoes ?: '-' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="label">Movimentou estoque no ato</div>
                    <div class="valor">{{ (int) ($troca->estoque_movimentado ?? 0) === 1 ? 'Sim' : 'Não' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="assinatura">
        <div class="assinatura-linha">Assinatura do responsável</div>
    </div>

    <div class="rodape">
        Comprovante interno do sistema de locações.
    </div>
</body>
</html>
