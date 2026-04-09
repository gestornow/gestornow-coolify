<?php

namespace App\Domain\Locacao\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class LocacaoModeloContrato extends Model
{
    use SoftDeletes;

    protected $table = 'locacao_modelos_contrato';
    protected $primaryKey = 'id_modelo';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'nome',
        'descricao',
        'conteudo_html',
        'cabecalho_html',
        'rodape_html',
        'css_personalizado',
        'logo_url',
        'titulo_documento',
        'subtitulo_documento',
        'cor_primaria',
        'cor_secundaria',
        'cor_texto',
        'cor_fundo',
        'cor_borda',
        'exibir_cabecalho',
        'exibir_logo',
        'exibir_assinatura_locadora',
        'exibir_assinatura_cliente',
        'assinatura_locadora_url',
        'colunas_tabela_produtos',
        'ativo',
        'padrao',
        'tipo_modelo',
        'usa_medicao',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'ativo' => 'boolean',
        'padrao' => 'boolean',
        'exibir_cabecalho' => 'boolean',
        'exibir_logo' => 'boolean',
        'exibir_assinatura_locadora' => 'boolean',
        'exibir_assinatura_cliente' => 'boolean',
        'colunas_tabela_produtos' => 'array',
        'tipo_modelo' => 'string',
        'usa_medicao' => 'boolean',
    ];

    private static ?array $colunasTabela = null;

    /**
     * Scope para empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para modelos ativos
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeTipoDocumento($query, string $tipo)
    {
        $tipoNormalizado = self::normalizarTipoModelo($tipo);

        if (self::hasColuna('tipo_modelo')) {
            if ($tipoNormalizado === 'contrato') {
                $query->where(function ($sub) {
                    $sub->whereNull('tipo_modelo')
                        ->orWhere('tipo_modelo', '')
                        ->orWhere('tipo_modelo', 'contrato');

                    if (self::hasColuna('usa_medicao')) {
                        $sub->where(function ($filtroMedicao) {
                            $filtroMedicao->whereNull('usa_medicao')
                                ->orWhere('usa_medicao', false);
                        });
                    }
                });

                return $query;
            }

            if ($tipoNormalizado === 'medicao' && self::hasColuna('usa_medicao')) {
                $query->where(function ($sub) {
                    $sub->where('tipo_modelo', 'medicao')
                        ->orWhere(function ($legacy) {
                            $legacy->whereNull('tipo_modelo')
                                ->where('usa_medicao', true);
                        });
                });

                return $query;
            }

            return $query->where('tipo_modelo', $tipoNormalizado);
        }

        if (self::hasColuna('usa_medicao')) {
            if ($tipoNormalizado === 'medicao') {
                return $query->where('usa_medicao', true);
            }

            return $query->where(function ($sub) {
                $sub->whereNull('usa_medicao')->orWhere('usa_medicao', false);
            });
        }

        return $query;
    }

    /**
     * Obter modelo padrão da empresa
     */
    public static function getModeloPadrao($idEmpresa, string $tipo = 'contrato')
    {
        $tipoNormalizado = self::normalizarTipoModelo($tipo);

        $query = self::where('id_empresa', $idEmpresa)
            ->where('padrao', true)
            ->where('ativo', true)
            ->tipoDocumento($tipoNormalizado)
            ->orderBy('nome');

        return $query->first();
    }

    public static function getModeloPadraoPorTipo($idEmpresa, string $tipo = 'contrato')
    {
        return self::getModeloPadrao($idEmpresa, $tipo)
            ?? self::where('id_empresa', $idEmpresa)
                ->where('ativo', true)
                ->tipoDocumento($tipo)
                ->orderBy('nome')
                ->first();
    }

    public function tipoModelo(): string
    {
        if (self::hasColuna('tipo_modelo')) {
            $tipo = self::normalizarTipoModelo((string) ($this->tipo_modelo ?? ''));
            if ($tipo !== '') {
                return $tipo;
            }
        }

        return !empty($this->usa_medicao) ? 'medicao' : 'contrato';
    }

    public function ehTipo(string $tipo): bool
    {
        return $this->tipoModelo() === self::normalizarTipoModelo($tipo);
    }

    public function getClausulasHtmlRenderizadas(): string
    {
        return $this->gerarClausulasHtmlDoBanco();
    }

    public static function hasColuna(string $coluna): bool
    {
        if (self::$colunasTabela === null) {
            self::$colunasTabela = Schema::hasTable('locacao_modelos_contrato')
                ? Schema::getColumnListing('locacao_modelos_contrato')
                : [];
        }

        return in_array($coluna, self::$colunasTabela, true);
    }

    public static function normalizarTipoModelo(?string $tipo): string
    {
        $tipoNormalizado = strtolower(trim((string) $tipo));
        return in_array($tipoNormalizado, ['contrato', 'orcamento', 'medicao'], true)
            ? $tipoNormalizado
            : 'contrato';
    }

    /**
     * Processar template substituindo variáveis
     */
    public function processarTemplate(Locacao $locacao)
    {
        $conteudo = $this->getConteudoPadraoEstilizado();
        $cabecalho = '';
        $rodape = '';

        $tema = $this->getTemaContrato();
        $tituloDocumento = $this->titulo_documento ?: ($this->nome ?: 'Contrato');
        $subtituloDocumento = $this->subtitulo_documento !== null
            ? $this->subtitulo_documento
            : ($this->descricao ?: 'Locação de Bens Móveis');

        // Variáveis da empresa
        $empresa = $locacao->empresa ?? null;
        $empresaConfig = is_array($empresa->configuracoes ?? null) ? $empresa->configuracoes : [];
        $logoTemplate = '';
        $logoBloco = '';
        
        // Logo só aparece se exibir_logo for explicitamente true (1) E houver logo configurada
        if ($this->exibir_logo === true || $this->exibir_logo === 1) {
            $logoResolvida = $this->resolverLogoEmpresaContrato($empresaConfig['logo_url'] ?? ($empresa->logo_url ?? null));
            if (!empty($logoResolvida)) {
                $logoTemplate = $logoResolvida;
                $logoBloco = '<img src="' . e($logoTemplate) . '" alt="Logo" class="logo-img">';
            }
        }
        
        $variaveis = [
            '{{empresa_nome}}' => $empresa->razao_social ?? $empresa->nome ?? '',
            '{{empresa_cnpj}}' => $empresa->cnpj ?? '',
            '{{empresa_endereco}}' => $this->formatarEnderecoEmpresa($empresa),
            '{{empresa_telefone}}' => $empresa->telefone ?? '',
            '{{empresa_email}}' => $empresa->email ?? '',
            '{{logo_url}}' => $logoTemplate,
            '{{cor_primaria}}' => $tema['cor_primaria'],
            '{{cor_secundaria}}' => $tema['cor_secundaria'],
            '{{cor_texto}}' => $tema['cor_texto'],
            '{{cor_fundo}}' => $tema['cor_fundo'],
            '{{cor_borda}}' => $tema['cor_borda'],
            '{{titulo_documento}}' => $tituloDocumento,
            '{{subtitulo_documento}}' => $subtituloDocumento,
            '{{logo_bloco}}' => $logoBloco,
            '{{clausulas_db}}' => $this->gerarClausulasHtmlDoBanco(),
        ];

        $variaveis = array_merge($variaveis, [
            '{{logo}}' => $logoBloco,
            '{{titulo}}' => $tituloDocumento,
            '{{subtitulo}}' => $subtituloDocumento,
        ]);

        // Bloco cabeçalho (título e logo) - sempre exibido
        $numeroContrato = trim((string) ($locacao->numero_contrato ?? ''));
        $blocoNumeroContrato = $numeroContrato !== '' 
            ? '<span class="numero-contrato">Nº ' . e($numeroContrato) . '</span>'
            : '';

        $blocoHeaderHtml = '
            <div class="contrato-wrapper">
                <div class="faixa-topo"></div>
                <div class="cabecalho-principal">
                    <table class="cabecalho-table">
                        <tr>
                            <td style="width:68%;">
                                <div class="documento-titulo">' . e($tituloDocumento) . ' <span class="titulo-dots">● ● ●</span></div>
                                <div class="documento-subtitulo">' . e($subtituloDocumento) . '</div>
                            </td>
                            <td style="width:14%;" class="numero-area">
                                ' . $blocoNumeroContrato . '
                            </td>
                            <td style="width:18%;" class="logo-area">
                                ' . $logoBloco . '
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        ';
        $variaveis['{{bloco_cabecalho}}'] = $blocoHeaderHtml;

        $assinaturasHtml = '';
        $exibirAssinaturaLocadora = $this->exibir_assinatura_locadora !== false;
        $exibirAssinaturaCliente = $this->exibir_assinatura_cliente !== false;
        $cliente = $locacao->cliente;

        $nomeLocadoraAssinatura = trim((string) ($empresa->razao_social ?? $empresa->nome_fantasia ?? $empresa->nome_empresa ?? $empresa->nome ?? 'Locadora'));
        $docLocadoraAssinatura = trim((string) ($empresa->cnpj ?? ''));
        $nomeClienteAssinatura = trim((string) ($cliente->razao_social ?? $cliente->nome ?? 'Cliente'));
        $docClienteAssinatura = trim((string) ($cliente->cpf_cnpj ?? ''));

        $assinaturaLocadoraSrc = $this->resolverAssinaturaLocadora($this->assinatura_locadora_url ?? null);
        $temAssinaturaLocadora = !empty($assinaturaLocadoraSrc);
        $assinaturaDigital = $locacao->assinaturaDigital;
        $assinaturaClienteSrc = null;

        if ($assinaturaDigital && $assinaturaDigital->status === 'assinado' && !empty($assinaturaDigital->assinatura_cliente_url)) {
            $assinaturaClienteSrc = $this->resolverAssinaturaCliente($assinaturaDigital->assinatura_cliente_url);
        }

        if ($exibirAssinaturaLocadora || $exibirAssinaturaCliente) {
            $assinaturasHtml = '<div class="data-local largura-partes">' . '{{cidade}}, {{data_extenso}}' . '</div>'
                . '<div class="assinaturas largura-partes">'
                . '<table class="assinaturas-table">'
                . '<tr>'
                . '<td class="assinatura-cell">'
                . ($exibirAssinaturaLocadora
                    ? (($assinaturaLocadoraSrc ? '<div class="assinatura-imagem"><img src="' . e($assinaturaLocadoraSrc) . '" alt="Assinatura Locadora" class="assinatura-img"></div>' : '')
                        . '<div class="assinatura-linha">' . e($nomeLocadoraAssinatura) . ($docLocadoraAssinatura !== '' ? '<br><small>CNPJ: ' . e($docLocadoraAssinatura) . '</small>' : '') . '</div>')
                    : '&nbsp;')
                . '</td>'
                . '<td class="assinatura-cell">'
                . ($exibirAssinaturaCliente
                    ? (($assinaturaClienteSrc
                            ? '<div class="assinatura-imagem"><img src="' . e($assinaturaClienteSrc) . '" alt="Assinatura Cliente" class="assinatura-img"></div>'
                            : ($temAssinaturaLocadora ? '<div class="assinatura-imagem assinatura-placeholder"></div>' : ''))
                        . '<div class="assinatura-linha">' . e($nomeClienteAssinatura) . ($docClienteAssinatura !== '' ? '<br><small>' . (strlen(preg_replace('/\D+/', '', $docClienteAssinatura)) > 11 ? 'CNPJ' : 'CPF') . ': ' . e($docClienteAssinatura) . '</small>' : '') . '</div>')
                    : '&nbsp;')
                . '</td>'
                . '</tr>'
                . '</table>'
                . '</div>';
        }

        $variaveis['{{bloco_assinaturas}}'] = $assinaturasHtml;
        $variaveis['{{assinatura_cliente_url}}'] = $assinaturaClienteSrc ?? '';

        // Variáveis do cliente
        $variaveis = array_merge($variaveis, [
            '{{cliente_nome}}' => $cliente->nome ?? '',
            '{{cliente_documento}}' => $cliente->cpf_cnpj ?? '',
            '{{cliente_endereco}}' => $this->formatarEnderecoCliente($cliente),
            '{{cliente_telefone}}' => $cliente->celular ?? $cliente->telefone ?? '',
            '{{cliente_email}}' => $cliente->email ?? '',
        ]);
        
        // Bloco partes (contratante/contratado) - controlado pelo toggle exibir_cabecalho
        // Montado DEPOIS de ter as variáveis de cliente e empresa definidas
        if ($this->exibir_cabecalho === false) {
            $variaveis['{{bloco_partes}}'] = '';
        } else {
            $blocoPartesHtml = '
                <table class="partes-table">
                    <tr>
                        <td style="width:50%;">
                            <div class="bloco-parte">
                                <div class="bloco-titulo">Contratante</div>
                                <div class="item-linha">Nome: ' . e($cliente->nome ?? '') . '</div>
                                <div class="item-linha">CPF/CNPJ: ' . e($cliente->cpf_cnpj ?? '') . '</div>
                                <div class="item-linha">Endereço: ' . e($this->formatarEnderecoCliente($cliente)) . '</div>
                                <div class="item-linha">E mail: ' . e($cliente->email ?? '') . '</div>
                            </div>
                        </td>
                        <td style="width:50%;">
                            <div class="bloco-parte">
                                <div class="bloco-titulo">Contratado</div>
                                <div class="item-linha">Nome: ' . e($empresa->razao_social ?? $empresa->nome ?? '') . '</div>
                                <div class="item-linha">CPF/CNPJ: ' . e($empresa->cnpj ?? '') . '</div>
                                <div class="item-linha">Endereço: ' . e($this->formatarEnderecoEmpresa($empresa)) . '</div>
                                <div class="item-linha">E mail: ' . e($empresa->email ?? '') . '</div>
                            </div>
                        </td>
                    </tr>
                </table>
            ';
            $variaveis['{{bloco_partes}}'] = $blocoPartesHtml;
        }

        // Variáveis da locação
        $subtotalProdutos = $this->calcularSubtotalProdutosLocacao($locacao);
        $subtotalServicos = $this->calcularSubtotalServicosLocacao($locacao);
        $valorFreteEntrega = $this->calcularValorFreteEntregaLocacao($locacao);
        $valorFreteRetirada = $this->calcularValorFreteRetiradaLocacao($locacao);
        $valorFrete = $this->calcularValorFreteLocacao($locacao);
        $valorDesconto = (float) ($locacao->valor_desconto ?? 0);
        $valorTotalContrato = $this->calcularValorTotalContrato($locacao, $subtotalProdutos, $subtotalServicos, $valorFrete, $valorDesconto);

        $variaveis = array_merge($variaveis, [
            '{{numero_contrato}}' => $locacao->numero_contrato ?? '',
            '{{data_inicio}}' => optional($locacao->data_inicio)->format('d/m/Y') ?? '',
            '{{data_fim}}' => optional($locacao->data_fim)->format('d/m/Y') ?? '',
            '{{hora_saida}}' => $locacao->hora_inicio ?? '00:00',
            '{{hora_inicio}}' => $locacao->hora_inicio ?? '00:00',
            '{{hora_retorno}}' => $locacao->hora_fim ?? '00:00',
            '{{hora_fim}}' => $locacao->hora_fim ?? '00:00',
            '{{total_dias}}' => $locacao->quantidade_dias ?? '',
            '{{dia}}' => (string) ($locacao->quantidade_dias ?? ''),
            '{{qdeperiodo}}' => ($locacao->locacao_por_hora ?? false) ? 'hora(s)' : 'dia(s)',
            '{{local_entrega}}' => $locacao->local_entrega ?? $this->formatarEnderecoCliente($cliente),
            '{{observacoes}}' => $locacao->observacoes ?? 'Nenhuma observação.',
            '{{subtotal_produtos}}' => number_format($subtotalProdutos, 2, ',', '.'),
            '{{subtotal_servicos}}' => number_format($subtotalServicos, 2, ',', '.'),
            '{{desconto}}' => number_format($valorDesconto, 2, ',', '.'),
            '{{frete_entrega}}' => number_format($valorFreteEntrega, 2, ',', '.'),
            '{{frete_retirada}}' => number_format($valorFreteRetirada, 2, ',', '.'),
            '{{frete_total}}' => number_format($valorFrete, 2, ',', '.'),
            '{{taxa_entrega}}' => number_format($valorFrete, 2, ',', '.'),
            '{{valor_total}}' => number_format($valorTotalContrato, 2, ',', '.'),
            '{{totalgeral}}' => number_format($valorTotalContrato, 2, ',', '.'),
            '{{totalextenso}}' => $this->valorMonetarioPorExtenso((float) $valorTotalContrato),
            '{{pagamento}}' => (string) ($locacao->forma_pagamento ?? 'A combinar'),
            '{{dataprazopag}}' => optional($locacao->vencimento)->format('d/m/Y') ?? 'A definir',
            '{{cidade}}' => $empresa->cidade ?? '',
            '{{data_extenso}}' => $this->dataExtenso(),
            '{{data_atual}}' => now()->format('d/m/Y'),
        ]);

        // Gerar lista de produtos
        $produtosHtml = $this->gerarListaProdutos($locacao);
        $variaveis['{{produtos_lista}}'] = $produtosHtml;

        // Substituir variáveis (com aliases: {{chave}}, {{CHAVE}}, {chave}, {CHAVE})
        $variaveisComAliases = $this->expandirVariaveisComAliases($variaveis);
        $conteudo = str_replace(array_keys($variaveisComAliases), array_values($variaveisComAliases), $conteudo);
        $cabecalho = str_replace(array_keys($variaveisComAliases), array_values($variaveisComAliases), $cabecalho);
        $rodape = str_replace(array_keys($variaveisComAliases), array_values($variaveisComAliases), $rodape);

        $conteudo = $this->limparChavesResiduaisTemplate($conteudo);
        $cabecalho = $this->limparChavesResiduaisTemplate($cabecalho);
        $rodape = $this->limparChavesResiduaisTemplate($rodape);

        return [
            'cabecalho' => $this->exibir_cabecalho === false ? '' : $cabecalho,
            'conteudo' => $conteudo,
            'rodape' => $rodape,
            'css' => $this->getCssBaseContrato($tema),
            'tema' => $tema,
            'exibir_cabecalho' => $this->exibir_cabecalho !== false,
        ];
    }

    /**
     * Formatar endereço da empresa
     */
    private function formatarEnderecoEmpresa($empresa)
    {
        if (!$empresa) return '';
        $partes = array_filter([
            $empresa->endereco,
            $empresa->numero,
            $empresa->bairro,
            $empresa->cidade,
            $empresa->uf,
            $empresa->cep
        ]);
        return implode(', ', $partes);
    }

    /**
     * Formatar endereço do cliente
     */
    private function formatarEnderecoCliente($cliente)
    {
        if (!$cliente) return '';
        $partes = array_filter([
            $cliente->endereco,
            $cliente->numero,
            $cliente->bairro,
            $cliente->cidade,
            $cliente->uf,
            $cliente->cep
        ]);
        return implode(', ', $partes);
    }

    /**
     * Gerar lista HTML de produtos
     */
    private function gerarListaProdutos(Locacao $locacao)
    {
        $colunas = $this->colunasTabelaProdutosValidas();

        $titulosColuna = [
            'produto' => 'Produto',
            'patrimonio' => 'Patrimônio',
            'sala' => 'Sala',
            'fornecedor' => 'Fornecedor',
            'quantidade' => 'Qtd',
            'dias' => $this->locacaoEhPorHora($locacao) ? 'Horas' : 'Dias',
            'valor_unitario' => 'Valor Unit.',
            'tipo_cobranca' => 'Cobrança',
            'subtotal' => 'Subtotal',
        ];

        $html = '<div class="tabela-produtos-wrap"><table class="tabela-produtos">';
        $html .= '<thead><tr>';
        foreach ($colunas as $coluna) {
            $html .= '<th>' . ($titulosColuna[$coluna] ?? ucfirst($coluna)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        $indice = 0;
        foreach ($locacao->produtos as $item) {
            $indice++;
            $html .= '<tr' . ($indice % 2 === 0 ? ' class="linha-zebra"' : '') . '>';
            foreach ($colunas as $coluna) {
                $html .= '<td>' . $this->renderizarColunaProduto($item, $coluna, $locacao) . '</td>';
            }
            $html .= '</tr>';
        }

        foreach ($locacao->produtosTerceiros as $item) {
            $indice++;
            $html .= '<tr' . ($indice % 2 === 0 ? ' class="linha-zebra"' : '') . '>';
            foreach ($colunas as $coluna) {
                $html .= '<td>' . $this->renderizarColunaProdutoTerceiro($item, $coluna, $locacao) . '</td>';
            }
            $html .= '</tr>';
        }

        foreach ($locacao->servicos as $item) {
            $indice++;
            $html .= '<tr' . ($indice % 2 === 0 ? ' class="linha-zebra"' : '') . '>';
            foreach ($colunas as $coluna) {
                $html .= '<td>' . $this->renderizarColunaServico($item, $coluna, $locacao) . '</td>';
            }
            $html .= '</tr>';
        }

        if ($indice === 0) {
            $html .= '<tr><td colspan="' . count($colunas) . '" style="text-align:center;">Sem itens.</td></tr>';
        }

        $html .= '</tbody></table></div>';
        $html .= '<div class="total-produtos">Total de itens: ' . $indice . '</div>';

        return $html;
    }

    private function renderizarColunaProduto($item, string $coluna, Locacao $locacao): string
    {
        $periodo = $this->calcularPeriodoItem(
            $locacao,
            $item->data_saida ?? $item->data_inicio ?? null,
            $item->data_retorno ?? $item->data_fim ?? null,
            $item->hora_saida ?? $item->hora_inicio ?? null,
            $item->hora_retorno ?? $item->hora_fim ?? null
        );
        $quantidade = max(1, (int) ($item->quantidade ?? 1));
        $valorUnitario = (float) ($item->preco_unitario ?? 0);
        $ehValorFechado = $this->itemEhValorFechado($item);
        $subtotalCalculado = $ehValorFechado
            ? (float) ($item->preco_total ?? 0)
            : ($valorUnitario * $quantidade * $periodo['quantidade']);

        return match ($coluna) {
            'produto' => e($item->produto->nome ?? 'Produto'),
            'patrimonio' => e($item->patrimonio->codigo_patrimonio ?? $item->patrimonio->numero_serie ?? '-'),
            'sala' => e($item->sala->nome ?? '-'),
            'fornecedor' => e($item->fornecedor->nome ?? '-'),
            'quantidade' => (string) $quantidade,
            'dias' => (string) $periodo['quantidade'],
            'valor_unitario' => 'R$ ' . number_format($valorUnitario, 2, ',', '.'),
            'tipo_cobranca' => $ehValorFechado ? 'Fechado' : ($periodo['unidade'] === 'hora(s)' ? 'Hora' : 'Diária'),
            'subtotal' => 'R$ ' . number_format($subtotalCalculado, 2, ',', '.'),
            default => '-',
        };
    }

    private function renderizarColunaProdutoTerceiro($item, string $coluna, Locacao $locacao): string
    {
        $periodo = $this->calcularPeriodoItem(
            $locacao,
            $item->data_inicio ?? $locacao->data_inicio ?? null,
            $item->data_fim ?? $locacao->data_fim ?? null,
            $item->hora_inicio ?? $locacao->hora_inicio ?? null,
            $item->hora_fim ?? $locacao->hora_fim ?? null
        );
        $quantidade = max(1, (int) ($item->quantidade ?? 1));
        $valorUnitario = (float) ($item->preco_unitario ?? 0);
        $ehValorFechado = $this->itemEhValorFechado($item);
        $subtotalCalculado = $ehValorFechado
            ? (float) ($item->valor_total ?? 0)
            : ($valorUnitario * $quantidade * $periodo['quantidade']);

        return match ($coluna) {
            'produto' => e($item->nome_produto ?? $item->produtoTerceiro->nome ?? 'Produto terceiro'),
            'patrimonio' => '-',
            'sala' => e($item->sala->nome ?? '-'),
            'fornecedor' => e($item->fornecedor->nome ?? '-'),
            'quantidade' => (string) $quantidade,
            'dias' => (string) $periodo['quantidade'],
            'valor_unitario' => 'R$ ' . number_format($valorUnitario, 2, ',', '.'),
            'tipo_cobranca' => $ehValorFechado ? 'Fechado' : ($periodo['unidade'] === 'hora(s)' ? 'Hora' : 'Diária'),
            'subtotal' => 'R$ ' . number_format($subtotalCalculado, 2, ',', '.'),
            default => '-',
        };
    }

    private function renderizarColunaServico($item, string $coluna, Locacao $locacao): string
    {
        $quantidade = max(1, (int) ($item->quantidade ?? 1));
        $valorUnitario = (float) ($item->preco_unitario ?? 0);
        $valorTotal = (float) ($item->valor_total ?? ($valorUnitario * $quantidade));
        $ehTerceiro = strtolower((string) ($item->tipo_item ?? 'proprio')) === 'terceiro';
        $rotuloTipo = $ehTerceiro ? ' (Serviço Terceiro)' : ' (Serviço Próprio)';

        return match ($coluna) {
            'produto' => e((string) ($item->descricao ?? 'Serviço') . $rotuloTipo),
            'patrimonio' => '-',
            'sala' => '-',
            'fornecedor' => $ehTerceiro ? 'Terceiro' : '-',
            'quantidade' => (string) $quantidade,
            'dias' => '1',
            'valor_unitario' => 'R$ ' . number_format($valorUnitario, 2, ',', '.'),
            'tipo_cobranca' => 'Serviço',
            'subtotal' => 'R$ ' . number_format($valorTotal, 2, ',', '.'),
            default => '-',
        };
    }

    private function locacaoEhPorHora(Locacao $locacao): bool
    {
        $qtdPeriodo = (int) ($locacao->quantidade_dias ?? 0);
        $dataInicioPeriodo = optional($locacao->data_inicio)->format('Y-m-d');
        $dataFimPeriodo = optional($locacao->data_fim)->format('Y-m-d');
        $horaInicioPeriodo = (string) ($locacao->hora_inicio ?? '');
        $horaFimPeriodo = (string) ($locacao->hora_fim ?? '');

        if (!$dataInicioPeriodo || !$dataFimPeriodo || $horaInicioPeriodo === '' || $horaFimPeriodo === '') {
            return false;
        }

        try {
            $inicioHora = \Carbon\Carbon::parse($dataInicioPeriodo . ' ' . $horaInicioPeriodo);
            $fimHora = \Carbon\Carbon::parse($dataFimPeriodo . ' ' . $horaFimPeriodo);
            $diasInclusivos = max(1, $inicioHora->copy()->startOfDay()->diffInDays($fimHora->copy()->startOfDay()) + 1);

            return $dataInicioPeriodo === $dataFimPeriodo || $qtdPeriodo > $diasInclusivos;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function calcularPeriodoItem(Locacao $locacao, $dataInicio, $dataFim, $horaInicio, $horaFim): array
    {
        $ehPorHora = $this->locacaoEhPorHora($locacao);

        if ($ehPorHora && $dataInicio && $dataFim && $horaInicio && $horaFim) {
            try {
                $inicio = \Carbon\Carbon::parse($dataInicio . ' ' . $horaInicio);
                $fim = \Carbon\Carbon::parse($dataFim . ' ' . $horaFim);
                if ($fim->gte($inicio)) {
                    return ['quantidade' => max(1, (int) ceil($inicio->diffInMinutes($fim) / 60)), 'unidade' => 'hora(s)'];
                }
            } catch (\Throwable $e) {
                // fallback abaixo
            }
        }

        return [
            'quantidade' => $this->calcularDiasItem($dataInicio, $dataFim),
            'unidade' => 'dia(s)',
        ];
    }

    private function itemEhValorFechado($item): bool
    {
        $tipoCobranca = strtolower((string) ($item->tipo_cobranca ?? 'diaria'));

        return in_array($tipoCobranca, ['fechado', 'valor_fechado'], true)
            || (int) ($item->valor_fechado ?? 0) === 1;
    }

    private function calcularDiasItem($dataInicio, $dataFim): int
    {
        if (!$dataInicio || !$dataFim) {
            return 1;
        }

        try {
            $inicio = \Carbon\Carbon::parse($dataInicio);
            $fim = \Carbon\Carbon::parse($dataFim);
            return max(1, $inicio->diffInDays($fim) + 1);
        } catch (\Throwable $e) {
            return 1;
        }
    }

    private function colunasTabelaProdutosValidas(): array
    {
        $permitidas = [
            'produto',
            'quantidade',
            'dias',
            'valor_unitario',
            'subtotal',
        ];

        $colunas = $this->colunas_tabela_produtos;
        if (!is_array($colunas) || empty($colunas)) {
            return ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal'];
        }

        $validas = array_values(array_intersect($colunas, $permitidas));
        return empty($validas) ? ['produto', 'quantidade', 'dias', 'valor_unitario', 'subtotal'] : $validas;
    }

    private function calcularSubtotalProdutosLocacao(Locacao $locacao): float
    {
        // Calcular subtotal de produtos próprios usando a mesma lógica de renderizarColunaProduto
        $subtotalProprios = (float) $locacao->produtos->sum(function ($item) use ($locacao) {
            $periodo = $this->calcularPeriodoItem(
                $locacao,
                $item->data_saida ?? $item->data_inicio ?? null,
                $item->data_retorno ?? $item->data_fim ?? null,
                $item->hora_saida ?? $item->hora_inicio ?? null,
                $item->hora_retorno ?? $item->hora_fim ?? null
            );
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $valorUnitario = (float) ($item->preco_unitario ?? 0);
            $ehValorFechado = $this->itemEhValorFechado($item);

            return $ehValorFechado
                ? (float) ($item->preco_total ?? 0)
                : ($valorUnitario * $quantidade * $periodo['quantidade']);
        });

        // Calcular subtotal de produtos terceiros usando a mesma lógica de renderizarColunaProdutoTerceiro
        $subtotalTerceiros = (float) $locacao->produtosTerceiros->sum(function ($item) use ($locacao) {
            $periodo = $this->calcularPeriodoItem(
                $locacao,
                $item->data_inicio ?? $locacao->data_inicio ?? null,
                $item->data_fim ?? $locacao->data_fim ?? null,
                $item->hora_inicio ?? $locacao->hora_inicio ?? null,
                $item->hora_fim ?? $locacao->hora_fim ?? null
            );
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $valorUnitario = (float) ($item->preco_unitario ?? 0);
            $ehValorFechado = $this->itemEhValorFechado($item);

            return $ehValorFechado
                ? (float) ($item->valor_total ?? 0)
                : ($valorUnitario * $quantidade * $periodo['quantidade']);
        });

        return $subtotalProprios + $subtotalTerceiros;
    }

    private function calcularSubtotalServicosLocacao(Locacao $locacao): float
    {
        return (float) ($locacao->servicos->sum(function ($item) {
            $quantidade = max(1, (int) ($item->quantidade ?? 1));
            $valorUnitario = (float) ($item->preco_unitario ?? 0);
            return (float) ($item->valor_total ?? ($valorUnitario * $quantidade));
        }) ?? 0);
    }

    private function calcularValorFreteLocacao(Locacao $locacao): float
    {
        $freteEntrega = $this->calcularValorFreteEntregaLocacao($locacao);
        $freteRetirada = $this->calcularValorFreteRetiradaLocacao($locacao);
        $freteSeparado = $freteEntrega + $freteRetirada;

        if ($freteSeparado > 0) {
            return $freteSeparado;
        }

        $valorFrete = (float) ($locacao->valor_frete ?? 0);
        if ($valorFrete > 0) {
            return $valorFrete;
        }

        return (float) ($locacao->valor_acrescimo ?? 0);
    }

    private function calcularValorFreteEntregaLocacao(Locacao $locacao): float
    {
        $freteEntrega = $locacao->valor_frete_entrega ?? null;

        if ($freteEntrega !== null && $freteEntrega !== '') {
            return (float) $freteEntrega;
        }

        return (float) ($locacao->valor_acrescimo ?? 0);
    }

    private function calcularValorFreteRetiradaLocacao(Locacao $locacao): float
    {
        return (float) ($locacao->valor_frete_retirada ?? 0);
    }

    private function calcularValorTotalContrato(
        Locacao $locacao,
        float $subtotalProdutos,
        float $subtotalServicos,
        float $valorFrete,
        float $valorDesconto
    ): float {
        $valorFinal = (float) ($locacao->valor_final ?? 0);
        if ($valorFinal > 0) {
            return $valorFinal;
        }

        return max(0, ($subtotalProdutos + $subtotalServicos + $valorFrete) - $valorDesconto);
    }

    private function getTemaContrato(): array
    {
        return [
            'cor_primaria' => $this->cor_primaria ?: '#1f97ea',
            'cor_secundaria' => $this->cor_secundaria ?: '#2f4858',
            'cor_texto' => $this->cor_texto ?: '#1f2937',
            'cor_fundo' => $this->cor_fundo ?: '#f3f4f6',
            'cor_borda' => $this->cor_borda ?: '#2f4858',
        ];
    }

    private function getCssBaseContrato(array $tema): string
    {
        return "
            body { font-family: DejaVu Sans, Arial, sans-serif; color: {$tema['cor_texto']}; font-size: 10px; background: #ffffff; margin: 0; }
            .contrato-wrapper { overflow: hidden; margin: -20px -20px 0 -20px; }
            .faixa-topo { background: {$tema['cor_borda']}; height: 20px; }
            .cabecalho-principal { padding: 10px 24px 2px 24px; }
            .cabecalho-table { width: 100%; border-collapse: collapse; }
            .cabecalho-table td { vertical-align: middle; }
            .documento-titulo { font-size: 20px; letter-spacing: .4px; color: {$tema['cor_secundaria']}; font-weight: 700; text-transform: uppercase; line-height: 1; }
            .titulo-dots { font-size: 9px; color: #6b7280; margin-left: 7px; vertical-align: middle; }
            .documento-subtitulo { font-size: 11px; color: {$tema['cor_secundaria']}; margin-top: 3px; }
            .numero-area { text-align: center; vertical-align: middle; white-space: nowrap; }
            .numero-contrato { display: inline-block; font-size: 12px; color: {$tema['cor_secundaria']}; font-weight: 700; }
            .logo-area { text-align: right; font-size: 16px; color: {$tema['cor_secundaria']}; text-transform: uppercase; vertical-align: middle; }
            .logo-img { display: inline-block; max-height: 52px; max-width: 130px; width: auto; height: auto; }
            .partes-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-top: 10px; }
            .bloco-parte { border: 1px solid {$tema['cor_borda']}; border-radius: 3px; padding: 6px; min-height: 82px; background: #fff; }
            .bloco { border: 1px solid {$tema['cor_borda']}; border-radius: 3px; padding: 7px; margin-top: 10px; background: #fff; }
            .bloco-produtos { border: 1px solid #cbd5e1; border-radius: 3px; padding: 7px; background: #fff; }
            .bloco-titulo { font-size: 11px; font-weight: 700; color: {$tema['cor_secundaria']}; margin-bottom: 5px; }
            .item-linha { line-height: 1.25; margin-bottom: 1px; font-size: 9px; }
            .largura-partes { width: 96%; margin-left: auto; margin-right: auto; box-sizing: border-box; }
            .largura-partes.bloco { margin-top: 10px; }
            .clausulas-wrap { margin-top: 10px; }
            .bloco-clausulas { border: 1px solid #cbd5e1; border-radius: 3px; padding: 7px; background: #fff; overflow: hidden; }
            .tabela-produtos-wrap { border: none; padding: 0; margin-top: 0; }
            .tabela-produtos { width: 100%; border-collapse: collapse; margin-top: 4px; table-layout: fixed; }
            .tabela-produtos th, .tabela-produtos td { border: 1px solid {$tema['cor_borda']}; padding: 4px; font-size: 9px; }
            .tabela-produtos th { background: #f1f5f9; color: {$tema['cor_secundaria']}; font-weight: 700; }
            .tabela-produtos .linha-zebra { background: #f8fafc; }
            .tabela-produtos td { word-wrap: break-word; overflow-wrap: anywhere; }
            .total-produtos { margin-top: 6px; font-size: 9px; color: #334155; text-align: right; }
            .resumo-financeiro { margin-top: 8px; border: 1px solid #cbd5e1; border-radius: 3px; overflow: hidden; }
            .resumo-financeiro .linha-resumo { display: table; width: 100%; border-bottom: 1px solid #e2e8f0; font-size: 9px; }
            .resumo-financeiro .linha-resumo:last-child { border-bottom: none; }
            .resumo-financeiro .linha-resumo span { display: table-cell; padding: 5px 6px; }
            .resumo-financeiro .linha-resumo span:last-child { text-align: right; font-weight: 600; }
            .resumo-financeiro .linha-resumo.total { background: #eff6ff; color: {$tema['cor_secundaria']}; font-weight: 700; }
            .clausulas-texto { margin-top: 4px; font-size: 9px; line-height: 1.4; text-align: justify; word-break: break-word; overflow-wrap: anywhere; max-width: 100%; }
            .clausulas-texto * { word-break: break-word; overflow-wrap: anywhere; max-width: 100%; }
            .clausulas-texto ul, .clausulas-texto ol { padding-left: 0; margin-left: 0; list-style-position: inside; }
            .clausulas-texto img { max-width: 100%; height: auto; }
            .clausulas-texto table { width: 100%; table-layout: fixed; }
            .data-local { margin-top: 16px; font-size: 9px; color: #334155; text-align: right; }
            .assinaturas { margin-top: 20px; }
            .assinaturas-table { width: 100%; border-collapse: collapse; }
            .assinatura-cell { width: 50%; text-align: center; padding-top: 26px; vertical-align: bottom; }
            .assinatura-imagem { margin-bottom: 6px; }
            .assinatura-img { max-height: 70px; max-width: 220px; }
            .assinatura-placeholder { height: 70px; }
            .assinatura-linha { border-top: 1px solid #111; padding-top: 4px; font-size: 9px; color: #334155; margin: 0 16px; }
            .clausula-box { margin-top: 8px; border: 1px solid #bfd3f2; border-radius: 4px; overflow: hidden; }
            .clausula-head { background: #eaf1fb; color: #0f3f7d; font-size: 9.5px; font-weight: 700; padding: 6px 8px; text-transform: uppercase; }
            .clausula-body { background: #fff; padding: 8px; font-size: 9px; line-height: 1.4; color: {$tema['cor_texto']}; }
        ";
    }

    private function getConteudoPadraoEstilizado(): string
    {
        return '
            {{bloco_cabecalho}}

            {{bloco_partes}}

            <div class="bloco largura-partes bloco-produtos">
                <div class="bloco-titulo">Tabela de Itens (Produtos e Serviços)</div>
                {{produtos_lista}}

                <div class="resumo-financeiro">
                    <div class="linha-resumo"><span>Subtotal Produtos</span><span>R$ {{subtotal_produtos}}</span></div>
                    <div class="linha-resumo"><span>Subtotal Serviços</span><span>R$ {{subtotal_servicos}}</span></div>
                    <div class="linha-resumo"><span>Frete Entrega</span><span>R$ {{frete_entrega}}</span></div>
                    <div class="linha-resumo"><span>Frete Retirada</span><span>R$ {{frete_retirada}}</span></div>
                    <div class="linha-resumo"><span>Frete Total</span><span>R$ {{frete_total}}</span></div>
                    <div class="linha-resumo"><span>Desconto</span><span>R$ {{desconto}}</span></div>
                    <div class="linha-resumo total"><span>Valor Total do Contrato</span><span>R$ {{valor_total}}</span></div>
                </div>
            </div>

            <div class="largura-partes clausulas-wrap bloco-clausulas">
                <div class="clausulas-texto">{{clausulas_db}}</div>
            </div>

            {{bloco_assinaturas}}
        ';
    }

    private function gerarClausulasHtmlDoBanco(): string
    {
        $conteudoDb = trim((string) ($this->conteudo_html ?? ''));

        if ($conteudoDb === '') {
            return $this->getClausulasPadraoHtml();
        }

        $conteudoDb = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $conteudoDb) ?? $conteudoDb;
        $conteudoDb = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $conteudoDb) ?? $conteudoDb;

        $placeholdersParaRemover = [
            '{{produtos_lista}}',
            '{{bloco_cabecalho}}',
            '{{bloco_partes}}',
            '{{logo_bloco}}',
        ];
        $conteudoDb = str_replace($placeholdersParaRemover, '', $conteudoDb);
        $conteudoDb = preg_replace('/tabela\s+de\s+produtos/i', '', $conteudoDb) ?? $conteudoDb;

        if (preg_match('/<(html|head|body|table|thead|tbody|tr|td|th)\b/i', $conteudoDb)) {
            $conteudoDb = trim(strip_tags($conteudoDb));
        }

        if ($conteudoDb === '') {
            return $this->getClausulasPadraoHtml();
        }

        if (preg_match('/<[^>]+>/', $conteudoDb)) {
            if (preg_match('/class\s*=\s*["\'][^"\']*clausula-box/i', $conteudoDb)) {
                return $conteudoDb;
            }

            $normalizadoHtml = $this->normalizarHtmlComParagrafosEmClausulas($conteudoDb);
            if ($normalizadoHtml !== '') {
                return $normalizadoHtml;
            }

            return $conteudoDb;
        }

        $blocos = preg_split('/\R{2,}/', $conteudoDb);
        $blocos = array_values(array_filter(array_map('trim', $blocos)));

        if (empty($blocos)) {
            return $this->getClausulasPadraoHtml();
        }

        $html = $this->montarClausulasHtmlPorBlocos($blocos);

        return $html !== '' ? $html : $this->getClausulasPadraoHtml();
    }

    private function normalizarHtmlComParagrafosEmClausulas(string $html): string
    {
        $texto = preg_replace('/<br\s*\/?\s*>/i', "\n", $html) ?? $html;
        $texto = preg_replace('/<\/(p|div|li|h[1-6]|tr|td|th)\s*>/i', "\n\n", $texto) ?? $texto;
        $texto = strip_tags((string) $texto);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/\r\n?/', "\n", $texto) ?? $texto;
        $texto = preg_replace('/[ \t]+\n/', "\n", $texto) ?? $texto;
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto) ?? $texto;
        $texto = trim((string) $texto);

        if ($texto === '') {
            return '';
        }

        $blocos = preg_split('/\n{2,}/', $texto) ?: [];
        $blocos = array_values(array_filter(array_map('trim', $blocos)));

        if (empty($blocos)) {
            return '';
        }

        return $this->montarClausulasHtmlPorBlocos($blocos);
    }

    private function montarClausulasHtmlPorBlocos(array $blocos): string
    {
        $html = '';

        foreach ($blocos as $bloco) {
            $linhas = array_values(array_filter(array_map('trim', preg_split('/\R+/', (string) $bloco) ?: [])));
            if (empty($linhas)) {
                continue;
            }

            $titulo = trim((string) ($linhas[0] ?? ''));
            $corpoLinhas = array_slice($linhas, 1);

            if ($titulo === '') {
                $titulo = 'TÍTULO';
                $corpoLinhas = $linhas;
            }

            $corpo = implode('<br>', array_map('e', $corpoLinhas));
            if ($corpo === '') {
                $corpo = '&nbsp;';
            }

            $html .= '<div class="clausula-box">';
            $html .= '<div class="clausula-head">' . e($titulo) . '</div>';
            $html .= '<div class="clausula-body">' . $corpo . '</div>';
            $html .= '</div>';
        }

        return $html;
    }

    private function getClausulasPadraoHtml(): string
    {
        return '
            <div class="clausula-box">
                <div class="clausula-head">CLÁUSULA I - DO PRAZO CONTRATUAL</div>
                <div class="clausula-body">O objeto deste contrato refere-se à locação dos itens relacionados, pelo período de {{total_dias}} dia(s), com início em {{data_inicio}} às {{hora_saida}} e término em {{data_fim}} às {{hora_retorno}}.</div>
            </div>
            <div class="clausula-box">
                <div class="clausula-head">CLÁUSULA II - DA CONSERVAÇÃO DOS BENS</div>
                <div class="clausula-body">A LOCATÁRIA se responsabiliza pela guarda e conservação dos bens durante toda a locação, comprometendo-se a devolvê-los em condições normais de uso.</div>
            </div>
            <div class="clausula-box">
                <div class="clausula-head">CLÁUSULA III - DO VALOR E INADIMPLEMENTO</div>
                <div class="clausula-body">Valor total pactuado: R$ {{valor_total}}. Em caso de inadimplemento, aplicam-se as penalidades previstas neste instrumento e na legislação vigente.</div>
            </div>
        ';
    }

    private function numeroRomano(int $numero): string
    {
        $mapa = [
            1000 => 'M', 900 => 'CM', 500 => 'D', 400 => 'CD',
            100 => 'C', 90 => 'XC', 50 => 'L', 40 => 'XL',
            10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I',
        ];

        $resultado = '';
        foreach ($mapa as $valor => $simbolo) {
            while ($numero >= $valor) {
                $resultado .= $simbolo;
                $numero -= $valor;
            }
        }

        return $resultado;
    }

    private function expandirVariaveisComAliases(array $variaveis): array
    {
        $resultado = $variaveis;

        foreach ($variaveis as $chaveTemplate => $valor) {
            if (!preg_match('/^\{\{([a-z0-9_]+)\}\}$/i', $chaveTemplate, $matches)) {
                continue;
            }

            $nome = $matches[1];
            $nomeUpper = strtoupper($nome);
            $nomeUcfirst = ucfirst(strtolower($nome));
            $nomeSemSeparadores = preg_replace('/[_\-\s]+/', '', $nome) ?: $nome;
            $nomeSemSeparadoresUpper = strtoupper($nomeSemSeparadores);
            $nomeSemSeparadoresUcfirst = ucfirst(strtolower($nomeSemSeparadores));

            $resultado['{{' . $nomeUpper . '}}'] = $valor;
            $resultado['{{' . $nomeUcfirst . '}}'] = $valor;
            $resultado['{' . $nome . '}'] = $valor;
            $resultado['{' . $nomeUpper . '}'] = $valor;
            $resultado['{' . $nomeUcfirst . '}'] = $valor;
            $resultado['[' . $nome . ']'] = $valor;
            $resultado['[' . $nomeUpper . ']'] = $valor;
            $resultado['[' . $nomeUcfirst . ']'] = $valor;
            $resultado['[' . $nomeSemSeparadores . ']'] = $valor;
            $resultado['[' . $nomeSemSeparadoresUpper . ']'] = $valor;
            $resultado['[' . $nomeSemSeparadoresUcfirst . ']'] = $valor;
        }

        return $resultado;
    }

    private function resolverLogoEmpresaContrato(?string $logoEmpresa): string
    {
        $logo = trim((string) ($logoEmpresa ?: ''));

        if ($logo === '') {
            return '';
        }

        $logoNormalizada = $this->normalizarLogoUrlContrato($logo);
        if (!$logoNormalizada) {
            return '';
        }

        $logoPath = parse_url($logoNormalizada, PHP_URL_PATH);
        $logoFileLocal = $logoPath ? public_path(ltrim($logoPath, '/')) : null;

        if ($logoFileLocal && file_exists($logoFileLocal)) {
            return $logoFileLocal;
        }

        return $logoNormalizada;
    }

    private function normalizarLogoUrlContrato(?string $logoUrl): ?string
    {
        if (empty($logoUrl)) {
            return null;
        }

        $logoMigrada = $this->migrarLogoLegadaParaPublicoContrato($logoUrl);
        if ($logoMigrada) {
            return $logoMigrada;
        }

        if (str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://')) {
            return $logoUrl;
        }

        $apiBase = rtrim((string) config('services.gestornow_api.base_url', ''), '/');
        $logoNormalizada = '/' . ltrim($logoUrl, '/');

        if (str_contains($logoNormalizada, '/uploads/logos/imagens/')) {
            return $apiBase !== '' ? ($apiBase . $logoNormalizada) : asset(ltrim($logoNormalizada, '/'));
        }

        return asset(ltrim($logoNormalizada, '/'));
    }

    private function migrarLogoLegadaParaPublicoContrato(string $logoUrl): ?string
    {
        $isUrlExterna = str_starts_with($logoUrl, 'http://') || str_starts_with($logoUrl, 'https://');
        $logoPath = $isUrlExterna ? parse_url($logoUrl, PHP_URL_PATH) : $logoUrl;
        $nomeArquivo = basename((string) $logoPath);

        if (empty($nomeArquivo) || $nomeArquivo === '.' || $nomeArquivo === '..') {
            return null;
        }

        $diretorioPublico = public_path('assets/logos-empresa');
        $logoPublica = $diretorioPublico . DIRECTORY_SEPARATOR . $nomeArquivo;

        if (\Illuminate\Support\Facades\File::exists($logoPublica)) {
            return asset('assets/logos-empresa/' . $nomeArquivo);
        }

        $origens = array_filter([
            $logoPath ? public_path(ltrim($logoPath, '/')) : null,
            storage_path('app/public/logos-empresa/' . $nomeArquivo),
        ]);

        foreach ($origens as $origem) {
            if (!\Illuminate\Support\Facades\File::exists($origem) || !\Illuminate\Support\Facades\File::isFile($origem)) {
                continue;
            }

            if (!\Illuminate\Support\Facades\File::exists($diretorioPublico)) {
                \Illuminate\Support\Facades\File::makeDirectory($diretorioPublico, 0755, true);
            }

            \Illuminate\Support\Facades\File::copy($origem, $logoPublica);
            return asset('assets/logos-empresa/' . $nomeArquivo);
        }

        return null;
    }

    private function limparChavesResiduaisTemplate(string $html): string
    {
        // Remove placeholders vazios ou não substituídos completamente: {{...}} e {...}
        $html = preg_replace('/\{\{\s*[^{}\n]*?\s*\}\}/u', '', $html) ?? $html;
        $html = preg_replace('/\{\s*[^{}\n]*?\s*\}/u', '', $html) ?? $html;
        return $html;
    }

    private function resolverAssinaturaLocadora(?string $assinaturaUrl): ?string
    {
        if (empty($assinaturaUrl)) {
            return null;
        }

        $assinaturaPath = parse_url($assinaturaUrl, PHP_URL_PATH);
        $assinaturaFileLocal = $assinaturaPath ? public_path(ltrim($assinaturaPath, '/')) : null;

        if ($assinaturaFileLocal && file_exists($assinaturaFileLocal)) {
            return $assinaturaFileLocal;
        }

        return $assinaturaUrl;
    }

    private function resolverAssinaturaCliente(?string $assinaturaUrl): ?string
    {
        if (empty($assinaturaUrl)) {
            return null;
        }

        $assinaturaUrl = str_replace(['https//', 'http//'], ['https://', 'http://'], trim((string) $assinaturaUrl));

        $assinaturaPath = parse_url($assinaturaUrl, PHP_URL_PATH);
        $assinaturaFileLocal = $assinaturaPath ? public_path(ltrim($assinaturaPath, '/')) : null;

        if ($assinaturaFileLocal && file_exists($assinaturaFileLocal)) {
            return $assinaturaFileLocal;
        }

        if (str_starts_with($assinaturaUrl, 'http://') || str_starts_with($assinaturaUrl, 'https://')) {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(20)->get($assinaturaUrl);
                if ($response->successful()) {
                    $mime = $response->header('Content-Type') ?: 'image/png';
                    return 'data:' . $mime . ';base64,' . base64_encode($response->body());
                }
            } catch (\Throwable $e) {
                // fallback para URL original
            }
        }

        return $assinaturaUrl;
    }

    /**
     * Data por extenso
     */
    private function dataExtenso()
    {
        $meses = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
        ];
        
        $dia = date('d');
        $mes = $meses[(int)date('m')];
        $ano = date('Y');
        
        return "{$dia} de {$mes} de {$ano}";
    }

    private function valorMonetarioPorExtenso(float $valor): string
    {
        try {
            $inteiro = (int) floor($valor);
            $centavos = (int) round(($valor - $inteiro) * 100);
            $formatador = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);

            $texto = $formatador->format($inteiro) . ' ' . ($inteiro === 1 ? 'real' : 'reais');

            if ($centavos > 0) {
                $texto .= ' e ' . $formatador->format($centavos) . ' ' . ($centavos === 1 ? 'centavo' : 'centavos');
            }

            return (string) $texto;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Processar template para pré-visualização com dados fictícios
     */
    public function processarTemplatePreview()
    {
        $dadosFicticios = [
            // Dados da locação
            '{{locacao_numero}}' => 'LOC-00001',
            '{{locacao_data_inicio}}' => date('d/m/Y'),
            '{{locacao_data_fim}}' => date('d/m/Y', strtotime('+30 days')),
            '{{locacao_hora_inicio}}' => '08:00',
            '{{locacao_hora_fim}}' => '18:00',
            '{{locacao_status}}' => 'Em Andamento',
            '{{locacao_observacoes}}' => 'Observações de exemplo para visualização do contrato.',
            '{{locacao_valor_total}}' => 'R$ 1.500,00',
            '{{locacao_valor_desconto}}' => 'R$ 100,00',
            '{{locacao_valor_final}}' => 'R$ 1.400,00',
            
            // Dados do cliente
            '{{cliente_nome}}' => 'João da Silva',
            '{{cliente_cpf_cnpj}}' => '123.456.789-00',
            '{{cliente_email}}' => 'joao@email.com',
            '{{cliente_telefone}}' => '(11) 99999-9999',
            '{{cliente_endereco}}' => 'Rua Exemplo, 123, Centro, São Paulo, SP, 01234-567',
            '{{cliente_rg}}' => '12.345.678-9',
            
            // Dados da empresa
            '{{empresa_nome}}' => auth()->user()->cliente->nome_empresa ?? 'Empresa Exemplo LTDA',
            '{{empresa_cnpj}}' => auth()->user()->cliente->cnpj ?? '12.345.678/0001-90',
            '{{empresa_telefone}}' => auth()->user()->cliente->telefone ?? '(11) 3333-3333',
            '{{empresa_email}}' => auth()->user()->cliente->email ?? 'contato@empresa.com',
            '{{empresa_endereco}}' => auth()->user()->cliente->endereco ?? 'Av. Principal, 456, Bairro, Cidade, UF',
            
            // Tabela de produtos
            '{{produtos_tabela}}' => '
                <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                    <thead>
                        <tr style="background: #f4f4f4;">
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: left;">Produto</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: center;">Qtd</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Preço Unit.</th>
                            <th style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;">Produto Exemplo A - PAT001</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">2</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 250,00</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 500,00</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;">Produto Exemplo B - PAT002</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">1</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 500,00</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 500,00</td>
                        </tr>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 8px;">Serviço de Instalação</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: center;">1</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 400,00</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 400,00</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f9f9f9; font-weight: bold;">
                            <td colspan="3" style="border: 1px solid #ddd; padding: 8px; text-align: right;">Total:</td>
                            <td style="border: 1px solid #ddd; padding: 8px; text-align: right;">R$ 1.400,00</td>
                        </tr>
                    </tfoot>
                </table>
            ',
            
            // Data atual
            '{{data_atual}}' => date('d/m/Y'),
            '{{data_atual_extenso}}' => $this->dataExtenso(),
            
            // Assinaturas
            '{{assinatura_cliente}}' => '
                <div style="margin-top: 50px; text-align: center;">
                    <div style="border-top: 1px solid #000; width: 250px; margin: 0 auto; padding-top: 5px;">
                        João da Silva<br>
                        <small>CPF: 123.456.789-00</small>
                    </div>
                </div>
            ',
            '{{assinatura_empresa}}' => '
                <div style="margin-top: 50px; text-align: center;">
                    <div style="border-top: 1px solid #000; width: 250px; margin: 0 auto; padding-top: 5px;">
                        ' . (auth()->user()->cliente->nome_empresa ?? 'Empresa Exemplo LTDA') . '<br>
                        <small>CNPJ: ' . (auth()->user()->cliente->cnpj ?? '12.345.678/0001-90') . '</small>
                    </div>
                </div>
            ',
        ];

        $conteudo = $this->conteudo_html;
        
        foreach ($dadosFicticios as $variavel => $valor) {
            $conteudo = str_replace($variavel, $valor, $conteudo);
        }
        
        return $conteudo;
    }
}
