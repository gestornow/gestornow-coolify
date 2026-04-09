<?php

namespace App\Services;

use App\Domain\Locacao\Models\Locacao;
use App\Domain\Locacao\Models\LocacaoAssinaturaDigital;
use App\Domain\Locacao\Models\LocacaoModeloContrato;
use Illuminate\Support\Facades\View;

class ContratoPdfService
{
    /**
     * Gerar HTML do contrato para conversão em PDF
     */
    public function gerarHtml(
        Locacao $locacao,
        ?int $idModelo = null,
        ?LocacaoAssinaturaDigital $assinaturaReferencia = null
    ): string
    {
        // Carregar locação com todos os relacionamentos necessários
        $locacao->load([
            'cliente',
            'produtos.produto',
            'produtos.patrimonio',
            'produtos.sala',
            'produtos.fornecedor',
            'produtosTerceiros.fornecedor',
            'produtosTerceiros.produtoTerceiro',
            'produtosTerceiros.sala',
            'servicos',
            'despesas',
            'salas',
            'empresa',
        ]);

        if ($assinaturaReferencia && (int) ($assinaturaReferencia->id_locacao ?? 0) === (int) ($locacao->id_locacao ?? 0)) {
            $locacao->setRelation('assinaturaDigital', $assinaturaReferencia);
        } else {
            $locacao->setRelation('assinaturaDigital', $this->resolverAssinaturaDigitalDaLocacao($locacao, $idModelo));
        }

        // Buscar modelo de contrato
        $modelo = null;
        if ($idModelo) {
            $modelo = LocacaoModeloContrato::find($idModelo);
        }
        
        if (!$modelo) {
            $modelo = LocacaoModeloContrato::getModeloPadrao($locacao->id_empresa);
        }

        if (!$modelo) {
            $modelo = new LocacaoModeloContrato([
                'id_empresa' => $locacao->id_empresa,
                'nome' => 'Padrão',
                'conteudo_html' => '',
                'titulo_documento' => 'Contrato',
                'subtitulo_documento' => 'Locação de Bens Móveis',
                'cor_primaria' => '#1f97ea',
                'cor_secundaria' => '#2f4858',
                'cor_texto' => '#1f2937',
                'cor_fundo' => '#f3f4f6',
                'cor_borda' => '#2f4858',
                'exibir_cabecalho' => true,
                'exibir_logo' => true,
                'exibir_assinatura_locadora' => true,
                'exibir_assinatura_cliente' => true,
                'colunas_tabela_produtos' => ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal'],
                'ativo' => true,
                'padrao' => true,
            ]);
        }

        $template = $modelo->processarTemplate($locacao);
        return $this->montarHtmlCompleto($template, $locacao);
    }

    private function resolverAssinaturaDigitalDaLocacao(Locacao $locacao, ?int $idModelo = null): ?LocacaoAssinaturaDigital
    {
        $queryBase = LocacaoAssinaturaDigital::query()
            ->where('id_locacao', (int) $locacao->id_locacao)
            ->when($idModelo !== null, function ($query) use ($idModelo) {
                $query->where('id_modelo', $idModelo);
            }, function ($query) {
                $query->whereNull('id_modelo');
            });

        $assinaturaAssinada = (clone $queryBase)
            ->where('status', 'assinado')
            ->whereNotNull('assinatura_cliente_url')
            ->latest('id_assinatura')
            ->first();

        if ($assinaturaAssinada) {
            return $assinaturaAssinada;
        }

        $assinaturaModelo = (clone $queryBase)
            ->latest('id_assinatura')
            ->first();

        if ($assinaturaModelo) {
            return $assinaturaModelo;
        }

        return LocacaoAssinaturaDigital::query()
            ->where('id_locacao', (int) $locacao->id_locacao)
            ->latest('id_assinatura')
            ->first();
    }

    /**
     * Montar HTML completo com cabeçalho, conteúdo e rodapé
     */
    private function montarHtmlCompleto(array $template, Locacao $locacao): string
    {
        $css = $template['css'] ?? '';
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Locação #{$locacao->numero_contrato}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
        }
        .page {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { font-size: 18px; margin-bottom: 10px; }
        h2 { font-size: 16px; margin-bottom: 8px; }
        h3 { font-size: 14px; margin-bottom: 6px; margin-top: 15px; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        .assinaturas {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
        }
        .assinatura {
            width: 45%;
            text-align: center;
        }
        .assinatura-linha {
            border-top: 1px solid #000;
            padding-top: 5px;
            margin-top: 40px;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
        {$css}
    </style>
</head>
<body>
    <div class="page">
        <div class="cabecalho">
            {$template['cabecalho']}
        </div>
        
        <div class="conteudo">
            {$template['conteudo']}
        </div>
        
        <div class="rodape">
            {$template['rodape']}
        </div>
    </div>
</body>
</html>
HTML;

        // Sanitizar HTML para remover tags de tabela órfãs que causam erro no Dompdf
        return $this->sanitizarHtmlParaPdf($html);
    }
    
    /**
     * Sanitizar HTML para evitar erros do Dompdf com tags de tabela mal formatadas
     */
    private function sanitizarHtmlParaPdf(string $html): string
    {
        // Primeiro, remover tags de tabela do conteúdo de clausulas que podem ter vindo do Quill
        // Procurar por texto em divs/paragrafos que tenham tags de tabela soltas
        libxml_use_internal_errors(true);
        
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->recover = true;
            $dom->strictErrorChecking = false;
            
            // Carregar HTML
            @$dom->loadHTML('<?xml encoding="UTF-8">' . mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), 
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
            
            $xpath = new \DOMXPath($dom);
            
            // Encontrar e remover tags de tabela que NÃO estão dentro de um elemento <table>
            $elementsToClean = ['td', 'th', 'tr', 'thead', 'tbody', 'tfoot'];
            
            foreach ($elementsToClean as $tagName) {
                // Buscar elementos que não têm table como ancestral
                $orphans = $xpath->query("//{$tagName}[not(ancestor::table)]");
                
                if ($orphans->length > 0) {
                    foreach ($orphans as $node) {
                        // Se o nó tem conteúdo texto, extrair e colocar no lugar
                        if ($node->nodeValue) {
                            $textNode = $dom->createTextNode($node->nodeValue);
                            $node->parentNode->replaceChild($textNode, $node);
                        } else {
                            // Se não tem conteúdo, só remover
                            $node->parentNode->removeChild($node);
                        }
                    }
                }
            }
            
            // Salvar HTML limpo
            $html = $dom->saveHTML();
            $html = str_replace('<?xml encoding="UTF-8">', '', $html);
            
        } catch (\Exception $e) {
            // Se falhar com DOM, tentar sanitização por regex como fallback
            $html = preg_replace('/<(\/?)(?:td|th|tr|thead|tbody|tfoot)(?![^<]*<table)[^>]*>/i', '', $html);
        }
        
        libxml_clear_errors();
        
        return $html;
    }

    /**
     * Gerar HTML padrão do sistema (sem modelo personalizado)
     */
    private function gerarHtmlPadrao(Locacao $locacao): string
    {
        $cliente = $locacao->cliente;
        $produtos = $locacao->produtos;
        $servicos = $locacao->servicos;
        
        // Agrupar produtos por sala
        $produtosPorSala = $produtos->groupBy('id_sala');
        
        $produtosHtml = '';
        foreach ($produtosPorSala as $idSala => $itens) {
            if ($idSala && $sala = $locacao->salas->where('id_sala', $idSala)->first()) {
                $produtosHtml .= '<tr style="background: #e8e8e8;"><td colspan="5"><strong>📁 ' . $sala->nome . '</strong></td></tr>';
            }
            
            foreach ($itens as $item) {
                $nomeItem = $item->produto->nome ?? 'Item';
                if ($item->patrimonio) {
                    $nomeItem .= ' - ' . ($item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? '');
                }
                
                $tipoLabel = '';
                if ($item->tipo_item === 'terceiro') {
                    $tipoLabel = ' <span style="color: #999;">(Terceiro)</span>';
                } elseif ($item->tipo_item === 'servico') {
                    $tipoLabel = ' <span style="color: #999;">(Serviço)</span>';
                }
                
                $produtosHtml .= '<tr>';
                $produtosHtml .= '<td>' . $nomeItem . $tipoLabel . '</td>';
                $produtosHtml .= '<td class="text-center">' . ($item->quantidade ?? 1) . '</td>';
                $produtosHtml .= '<td class="text-right">R$ ' . number_format($item->preco_unitario ?? 0, 2, ',', '.') . '</td>';
                $produtosHtml .= '<td class="text-center">' . ($item->valor_fechado ? 'Fechado' : 'Diária') . '</td>';
                $produtosHtml .= '<td class="text-right">R$ ' . number_format($item->preco_total ?? 0, 2, ',', '.') . '</td>';
                $produtosHtml .= '</tr>';
            }
        }

        $servicosHtml = '';
        if ($servicos && $servicos->count() > 0) {
            $servicosHtml = '<h3>SERVIÇOS ADICIONAIS</h3><table><thead><tr><th>Descrição</th><th>Qtd</th><th>Valor Unit.</th><th>Subtotal</th></tr></thead><tbody>';
            foreach ($servicos as $servico) {
                $servicosHtml .= '<tr>';
                $servicosHtml .= '<td>' . $servico->descricao . '</td>';
                $servicosHtml .= '<td class="text-center">' . ($servico->quantidade ?? 1) . '</td>';
                $servicosHtml .= '<td class="text-right">R$ ' . number_format($servico->valor_unitario ?? 0, 2, ',', '.') . '</td>';
                $servicosHtml .= '<td class="text-right">R$ ' . number_format($servico->valor ?? 0, 2, ',', '.') . '</td>';
                $servicosHtml .= '</tr>';
            }
            $servicosHtml .= '</tbody></table>';
        }

        $horaSaida = $locacao->hora_saida ?? '00:00';
        $horaRetorno = $locacao->hora_retorno ?? '00:00';
        
        $transporteHtml = '';
        if ($locacao->data_transporte_ida) {
            $transporteHtml = '<p><strong>Transporte Ida:</strong> ' . optional($locacao->data_transporte_ida)->format('d/m/Y') . ' às ' . ($locacao->hora_transporte_ida ?? '00:00') . '</p>';
            if ($locacao->data_transporte_volta) {
                $transporteHtml .= '<p><strong>Transporte Volta:</strong> ' . optional($locacao->data_transporte_volta)->format('d/m/Y') . ' às ' . ($locacao->hora_transporte_volta ?? '00:00') . '</p>';
            }
        }

        $subtotalProdutos = $produtos->sum('preco_total');
        $subtotalServicos = $servicos ? $servicos->sum('valor') : 0;
        $freteEntrega = (float) ($locacao->valor_frete_entrega ?? $locacao->valor_acrescimo ?? 0);
        $freteRetirada = (float) ($locacao->valor_frete_retirada ?? 0);
        $freteTotal = $freteEntrega + $freteRetirada;

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato de Locação #{$locacao->numero_contrato}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            padding: 20px;
        }
        .page { max-width: 800px; margin: 0 auto; }
        h1 { font-size: 20px; text-align: center; margin-bottom: 5px; }
        h2 { font-size: 14px; text-align: center; margin-bottom: 20px; color: #666; }
        h3 { font-size: 13px; margin: 15px 0 8px; padding-bottom: 3px; border-bottom: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; font-size: 11px; }
        th { background-color: #f5f5f5; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .info-box { background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .info-row { display: flex; margin-bottom: 5px; }
        .info-label { font-weight: bold; width: 120px; }
        .totais { margin-top: 20px; }
        .totais table { width: 300px; margin-left: auto; }
        .totais th { text-align: right; }
        .total-final { font-size: 14px; font-weight: bold; background: #e3f2fd !important; }
        .assinaturas { display: flex; justify-content: space-between; margin-top: 60px; }
        .assinatura { width: 45%; text-align: center; }
        .assinatura-linha { border-top: 1px solid #000; padding-top: 5px; margin-top: 50px; }
        .obs-box { background: #fff9c4; padding: 10px; margin: 10px 0; border-radius: 4px; border-left: 3px solid #ffc107; }
        .rodape { margin-top: 30px; text-align: center; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
        @media print { body { padding: 10px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="page">
        <h1>CONTRATO DE LOCAÇÃO DE EQUIPAMENTOS</h1>
        <h2>Nº {$locacao->numero_contrato}</h2>
        
        <h3>1. DADOS DO CLIENTE</h3>
        <div class="info-box">
            <p><strong>Nome/Razão Social:</strong> {$cliente->nome}</p>
            <p><strong>CPF/CNPJ:</strong> {$cliente->cpf_cnpj}</p>
            <p><strong>Endereço:</strong> {$cliente->endereco}, {$cliente->numero} - {$cliente->bairro}, {$cliente->cidade}/{$cliente->uf} - CEP: {$cliente->cep}</p>
            <p><strong>Telefone:</strong> {$cliente->celular} | <strong>E-mail:</strong> {$cliente->email}</p>
        </div>
        
        <h3>2. PERÍODO DA LOCAÇÃO</h3>
        <div class="info-box">
            <p><strong>Data de Início:</strong> {$locacao->data_inicio->format('d/m/Y')} às {$horaSaida}</p>
            <p><strong>Data de Término:</strong> {$locacao->data_fim->format('d/m/Y')} às {$horaRetorno}</p>
            <p><strong>Total de Dias:</strong> {$locacao->quantidade_dias} dias</p>
            {$transporteHtml}
        </div>
        
        <h3>3. LOCAL DE ENTREGA</h3>
        <div class="info-box">
            <p>{$locacao->local_entrega}</p>
            <p><strong>Contato:</strong> {$locacao->contato_responsavel} - {$locacao->telefone_responsavel}</p>
        </div>
        
        <h3>4. ITENS LOCADOS</h3>
        <table>
            <thead>
                <tr>
                    <th>Descrição</th>
                    <th class="text-center">Qtd</th>
                    <th class="text-right">Valor Unit.</th>
                    <th class="text-center">Tipo</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                {$produtosHtml}
            </tbody>
        </table>
        
        {$servicosHtml}
        
        <div class="totais">
            <table>
                <tr>
                    <th>Subtotal Produtos:</th>
                    <td class="text-right">R$ {number_format($subtotalProdutos, 2, ',', '.')}</td>
                </tr>
                <tr>
                    <th>Subtotal Serviços:</th>
                    <td class="text-right">R$ {number_format($subtotalServicos, 2, ',', '.')}</td>
                </tr>
                <tr>
                    <th>Desconto:</th>
                    <td class="text-right">- R$ {number_format($locacao->valor_desconto ?? 0, 2, ',', '.')}</td>
                </tr>
                <tr>
                    <th>Frete Entrega:</th>
                    <td class="text-right">R$ {number_format($freteEntrega, 2, ',', '.')}</td>
                </tr>
                <tr>
                    <th>Frete Retirada:</th>
                    <td class="text-right">R$ {number_format($freteRetirada, 2, ',', '.')}</td>
                </tr>
                <tr>
                    <th>Frete Total:</th>
                    <td class="text-right">R$ {number_format($freteTotal, 2, ',', '.')}</td>
                </tr>
                <tr class="total-final">
                    <th>VALOR TOTAL:</th>
                    <td class="text-right">R$ {number_format($locacao->valor_final ?? 0, 2, ',', '.')}</td>
                </tr>
            </table>
        </div>
        
        <h3>5. OBSERVAÇÕES</h3>
        <div class="obs-box">
            <p>{$locacao->observacoes}</p>
        </div>
        
        <h3>6. TERMOS E CONDIÇÕES</h3>
        <p style="font-size: 10px; text-align: justify;">
            O LOCATÁRIO declara ter recebido os equipamentos descritos acima em perfeito estado de funcionamento e conservação, 
            comprometendo-se a devolvê-los nas mesmas condições. Em caso de danos, perdas ou extravios, o LOCATÁRIO será responsável 
            pelo pagamento integral do valor de reposição dos equipamentos. A devolução deverá ser realizada na data e horário 
            estipulados, sob pena de cobrança de diárias adicionais.
        </p>
        
        <div class="assinaturas">
            <div class="assinatura">
                <div class="assinatura-linha">
                    <strong>LOCADOR</strong>
                </div>
            </div>
            <div class="assinatura">
                <div class="assinatura-linha">
                    <strong>LOCATÁRIO</strong><br>
                    {$cliente->nome}
                </div>
            </div>
        </div>
        
        <div class="rodape">
            <p>Documento gerado em {now()->format('d/m/Y H:i')}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Gerar PDF usando DomPDF ou similar
     */
    public function gerarPdf(Locacao $locacao, ?int $idModelo = null)
    {
        $html = $this->gerarHtml($locacao, $idModelo);
        
        // LOG TEMPORÁRIO: Salvar HTML para debug
        try {
            $debugPath = storage_path('logs/ultimo-html-contrato-debug.html');
            file_put_contents($debugPath, $html);
            \Log::info('HTML do contrato salvo em: ' . $debugPath);
        } catch (\Exception $e) {
            // Ignorar erros de log
        }
        
        // Verificar se DomPDF está disponível
        if (class_exists('\Barryvdh\DomPDF\Facade\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            $pdf->setPaper('A4', 'portrait');
            return $pdf;
        }
        
        // Se não tiver DomPDF, retornar HTML para impressão
        return $html;
    }
}
