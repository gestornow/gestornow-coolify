<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // DOMAIN: AUTH
        // =====================================================================

        Schema::create('empresa', function (Blueprint $table) {
            $table->bigIncrements('id_empresa');
            $table->string('razao_social')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('cpf')->nullable();
            $table->unsignedBigInteger('id_tipo_pessoa')->nullable();
            $table->string('nome_empresa')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero')->nullable();
            $table->string('bairro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('complemento')->nullable();
            $table->string('uf')->nullable();
            $table->string('cep')->nullable();
            $table->string('ie')->nullable();
            $table->string('im')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('id_plano')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('data_bloqueio')->nullable();
            $table->dateTime('data_cancelamento')->nullable();
            $table->dateTime('data_fim_teste')->nullable();
            $table->unsignedBigInteger('id_plano_teste')->nullable();
            $table->string('telefone')->nullable();
            $table->string('cnae')->nullable();
            $table->unsignedBigInteger('id_regime_tributario')->nullable();
            $table->json('configuracoes')->nullable();
            $table->string('dados_cadastrais')->nullable();
            $table->string('codigo')->nullable();
            $table->string('filial')->nullable();
            $table->string('c_produtos')->nullable();
            $table->string('c_clientes')->nullable();
            $table->string('c_fornecedores')->nullable();
            $table->unsignedBigInteger('id_empresa_matriz')->nullable();
            $table->integer('orcamentos_contratos')->nullable();
            $table->integer('locacao_numero_manual')->nullable();
            $table->index('id_tipo_pessoa');
            $table->index('id_plano');
            $table->index('id_plano_teste');
            $table->index('id_regime_tributario');
            $table->index('id_empresa_matriz');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('usuario_permissoes', function (Blueprint $table) {
            $table->bigIncrements('id_usuario_permissao');
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->unsignedBigInteger('id_modulo')->nullable();
            $table->boolean('pode_ler')->default(false);
            $table->boolean('pode_criar')->default(false);
            $table->boolean('pode_editar')->default(false);
            $table->boolean('pode_deletar')->default(false);
            $table->index('id_usuario');
            $table->index('id_modulo');
            $table->timestamps();
        });

        // =====================================================================
        // DOMAIN: CLIENTE
        // =====================================================================

        Schema::create('clientes', function (Blueprint $table) {
            $table->bigIncrements('id_clientes');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_filial')->nullable();
            $table->string('nome')->nullable();
            $table->string('cep')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('rg_ie')->nullable();
            $table->string('cpf_cnpj')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('bairro')->nullable();
            $table->string('email')->nullable();
            $table->string('endereco_entrega')->nullable();
            $table->string('numero_entrega')->nullable();
            $table->string('complemento_entrega')->nullable();
            $table->string('cep_entrega')->nullable();
            $table->string('telefone')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('id_tipo_pessoa')->nullable();
            $table->string('foto')->nullable();
            $table->string('nomeImagemCliente')->nullable();
            $table->index('id_empresa');
            $table->index('id_filial');
            $table->index('id_tipo_pessoa');
            $table->timestamps();
            $table->softDeletes();
        });

        // =====================================================================
        // DOMAIN: PRODUTO
        // =====================================================================

        Schema::create('produtos', function (Blueprint $table) {
            $table->bigIncrements('id_produto');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_marca')->nullable();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->unsignedBigInteger('id_tipo')->nullable();
            $table->unsignedBigInteger('unidade_medida_id')->nullable();
            $table->unsignedBigInteger('id_modelo')->nullable();
            $table->string('hex_color')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->text('detalhes')->nullable();
            $table->decimal('preco', 15, 2)->nullable();
            $table->decimal('preco_reposicao', 15, 2)->nullable();
            $table->decimal('preco_custo', 15, 2)->nullable();
            $table->decimal('preco_venda', 15, 2)->nullable();
            $table->decimal('preco_locacao', 15, 2)->nullable();
            $table->decimal('altura', 15, 2)->nullable();
            $table->decimal('largura', 15, 2)->nullable();
            $table->decimal('profundidade', 15, 2)->nullable();
            $table->decimal('peso', 15, 2)->nullable();
            $table->integer('estoque_total')->nullable();
            $table->integer('quantidade')->nullable();
            $table->string('codigo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->string('status')->nullable();
            $table->string('foto_url')->nullable();
            $table->string('foto_filename')->nullable();
            $table->index('id_empresa');
            $table->index('id_marca');
            $table->index('id_grupo');
            $table->index('id_tipo');
            $table->index('unidade_medida_id');
            $table->index('id_modelo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('acessorios', function (Blueprint $table) {
            $table->bigIncrements('id_acessorio');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->integer('quantidade')->nullable();
            $table->decimal('preco_custo', 15, 2)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->string('status')->nullable();
            $table->index('id_empresa');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('patrimonios', function (Blueprint $table) {
            $table->bigIncrements('id_patrimonio');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->string('numero_serie')->nullable();
            $table->date('data_aquisicao')->nullable();
            $table->decimal('valor_aquisicao', 15, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('status_locacao')->nullable();
            $table->date('ultima_manutencao')->nullable();
            $table->date('proxima_manutencao')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_empresa');
            $table->index('id_produto');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('manutencoes', function (Blueprint $table) {
            $table->bigIncrements('id_manutencao');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->unsignedBigInteger('id_patrimonio')->nullable();
            $table->integer('quantidade')->nullable();
            $table->dateTime('data_manutencao')->nullable();
            $table->dateTime('data_previsao')->nullable();
            $table->string('hora_manutencao')->nullable();
            $table->string('hora_previsao')->nullable();
            $table->string('tipo')->nullable();
            $table->text('descricao')->nullable();
            $table->string('status')->nullable();
            $table->integer('estoque_status')->nullable();
            $table->string('responsavel')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_empresa');
            $table->index('id_produto');
            $table->index('id_patrimonio');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('movimentacoes_estoque', function (Blueprint $table) {
            $table->bigIncrements('id_movimentacao');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('tipo')->nullable();
            $table->integer('quantidade')->nullable();
            $table->integer('estoque_anterior')->nullable();
            $table->integer('estoque_posterior')->nullable();
            $table->string('motivo')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_empresa');
            $table->index('id_produto');
            $table->index('id_usuario');
            $table->timestamps();
        });

        Schema::create('patrimonio_historico', function (Blueprint $table) {
            $table->bigIncrements('id_historico');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_patrimonio')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->string('tipo_movimentacao')->nullable();
            $table->string('status_anterior')->nullable();
            $table->string('status_novo')->nullable();
            $table->dateTime('data_movimentacao')->nullable();
            $table->string('local_origem')->nullable();
            $table->string('local_destino')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->index('id_empresa');
            $table->index('id_patrimonio');
            $table->index('id_produto');
            $table->index('id_locacao');
            $table->index('id_cliente');
            $table->index('id_usuario');
            $table->timestamps();
        });

        Schema::create('produto_acessorios', function (Blueprint $table) {
            $table->bigIncrements('id_produto_acessorio');
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->unsignedBigInteger('id_acessorio')->nullable();
            $table->integer('quantidade')->nullable();
            $table->boolean('obrigatorio')->default(false);
            $table->index('id_produto');
            $table->index('id_acessorio');
            $table->timestamps();
        });

        Schema::create('produto_historico', function (Blueprint $table) {
            $table->bigIncrements('id_historico');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->string('tipo_movimentacao')->nullable();
            $table->integer('quantidade')->nullable();
            $table->integer('estoque_anterior')->nullable();
            $table->integer('estoque_novo')->nullable();
            $table->dateTime('data_movimentacao')->nullable();
            $table->string('motivo')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->index('id_empresa');
            $table->index('id_produto');
            $table->index('id_locacao');
            $table->index('id_cliente');
            $table->index('id_usuario');
            $table->timestamps();
        });

        Schema::create('produtos_terceiros', function (Blueprint $table) {
            $table->bigIncrements('id_produto_terceiro');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_fornecedor')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->string('codigo')->nullable();
            $table->decimal('custo_diaria', 15, 2)->nullable();
            $table->decimal('preco_locacao', 15, 2)->nullable();
            $table->string('foto_url')->nullable();
            $table->string('status')->nullable();
            $table->index('id_empresa');
            $table->index('id_fornecedor');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('produtos_venda', function (Blueprint $table) {
            $table->bigIncrements('id_produto_venda');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_marca')->nullable();
            $table->unsignedBigInteger('id_grupo')->nullable();
            $table->unsignedBigInteger('id_tipo')->nullable();
            $table->unsignedBigInteger('unidade_medida_id')->nullable();
            $table->unsignedBigInteger('id_modelo')->nullable();
            $table->string('hex_color')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->text('detalhes')->nullable();
            $table->decimal('preco', 15, 2)->nullable();
            $table->decimal('preco_reposicao', 15, 2)->nullable();
            $table->decimal('preco_custo', 15, 2)->nullable();
            $table->decimal('preco_venda', 15, 2)->nullable();
            $table->decimal('preco_locacao', 15, 2)->nullable();
            $table->decimal('altura', 15, 2)->nullable();
            $table->decimal('largura', 15, 2)->nullable();
            $table->decimal('profundidade', 15, 2)->nullable();
            $table->decimal('peso', 15, 2)->nullable();
            $table->integer('estoque_total')->nullable();
            $table->integer('quantidade')->nullable();
            $table->string('codigo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->string('status')->nullable();
            $table->string('foto_url')->nullable();
            $table->string('foto_filename')->nullable();
            $table->index('id_empresa');
            $table->index('id_marca');
            $table->index('id_grupo');
            $table->index('id_tipo');
            $table->index('unidade_medida_id');
            $table->index('id_modelo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tabela_precos', function (Blueprint $table) {
            $table->bigIncrements('id_tabela');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->string('nome')->nullable();
            $table->decimal('d1', 15, 2)->nullable();
            $table->decimal('d2', 15, 2)->nullable();
            $table->decimal('d3', 15, 2)->nullable();
            $table->decimal('d4', 15, 2)->nullable();
            $table->decimal('d5', 15, 2)->nullable();
            $table->decimal('d6', 15, 2)->nullable();
            $table->decimal('d7', 15, 2)->nullable();
            $table->decimal('d8', 15, 2)->nullable();
            $table->decimal('d9', 15, 2)->nullable();
            $table->decimal('d10', 15, 2)->nullable();
            $table->decimal('d11', 15, 2)->nullable();
            $table->decimal('d12', 15, 2)->nullable();
            $table->decimal('d13', 15, 2)->nullable();
            $table->decimal('d14', 15, 2)->nullable();
            $table->decimal('d15', 15, 2)->nullable();
            $table->decimal('d16', 15, 2)->nullable();
            $table->decimal('d17', 15, 2)->nullable();
            $table->decimal('d18', 15, 2)->nullable();
            $table->decimal('d19', 15, 2)->nullable();
            $table->decimal('d20', 15, 2)->nullable();
            $table->decimal('d21', 15, 2)->nullable();
            $table->decimal('d22', 15, 2)->nullable();
            $table->decimal('d23', 15, 2)->nullable();
            $table->decimal('d24', 15, 2)->nullable();
            $table->decimal('d25', 15, 2)->nullable();
            $table->decimal('d26', 15, 2)->nullable();
            $table->decimal('d27', 15, 2)->nullable();
            $table->decimal('d28', 15, 2)->nullable();
            $table->decimal('d29', 15, 2)->nullable();
            $table->decimal('d30', 15, 2)->nullable();
            $table->decimal('d60', 15, 2)->nullable();
            $table->decimal('d120', 15, 2)->nullable();
            $table->decimal('d360', 15, 2)->nullable();
            $table->index('id_empresa');
            $table->index('id_produto');
            $table->timestamps();
            $table->softDeletes();
        });

        // =====================================================================
        // DOMAIN: LOCACAO
        // =====================================================================

        Schema::create('locacao', function (Blueprint $table) {
            $table->bigIncrements('id_locacao');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('numero_contrato')->nullable();
            // Período da locação (saída/retorno)
            $table->date('data_inicio')->nullable();
            $table->string('hora_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->string('hora_fim')->nullable();
            $table->boolean('locacao_por_hora')->default(false);
            $table->integer('quantidade_dias')->nullable();
            // Transporte
            $table->dateTime('data_transporte_ida')->nullable();
            $table->string('hora_transporte_ida')->nullable();
            $table->dateTime('data_transporte_volta')->nullable();
            $table->string('hora_transporte_volta')->nullable();
            // Endereço e contato do evento
            $table->string('local_entrega')->nullable();
            $table->string('local_retirada')->nullable();
            $table->string('contato_responsavel')->nullable();
            $table->string('telefone_responsavel')->nullable();
            $table->string('nome_obra')->nullable();
            $table->string('contato_local')->nullable();
            $table->string('telefone_contato')->nullable();
            $table->string('local_evento')->nullable();
            $table->string('endereco_entrega')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado')->nullable();
            $table->string('cep')->nullable();
            // Nota fiscal
            $table->string('numero_nf')->nullable();
            $table->string('serie_nf')->nullable();
            $table->date('vencimento')->nullable();
            // Valores
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->decimal('valor_frete', 15, 2)->nullable();
            $table->decimal('valor_despesas_extras', 15, 2)->nullable();
            $table->decimal('valor_desconto', 15, 2)->nullable();
            $table->decimal('valor_acrescimo', 15, 2)->nullable();
            $table->decimal('valor_imposto', 15, 2)->nullable();
            $table->decimal('valor_final', 15, 2)->nullable();
            $table->decimal('valor_limite_medicao', 15, 2)->nullable();
            // Pagamento
            $table->string('forma_pagamento')->nullable();
            $table->string('condicao_pagamento')->nullable();
            // Status e informações gerais
            $table->string('status')->nullable();
            $table->string('status_logistica')->nullable();
            $table->string('responsavel')->nullable();
            $table->text('observacoes')->nullable();
            $table->text('observacoes_recibo')->nullable();
            $table->text('observacoes_entrega')->nullable();
            $table->text('observacoes_checklist')->nullable();
            $table->integer('aditivo')->nullable();
            $table->boolean('renovacao_automatica')->default(false);
            $table->unsignedBigInteger('id_locacao_origem')->nullable();
            $table->unsignedBigInteger('id_locacao_anterior')->nullable();
            $table->integer('numero_orcamento')->nullable();
            $table->integer('numero_orcamento_origem')->nullable();
            $table->index('id_empresa');
            $table->index('id_cliente');
            $table->index('id_usuario');
            $table->index('id_locacao_origem');
            $table->index('id_locacao_anterior');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locacao_assinaturas_digitais', function (Blueprint $table) {
            $table->bigIncrements('id_assinatura');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_modelo')->nullable();
            $table->string('email_destinatario')->nullable();
            $table->string('token')->nullable();
            $table->string('status')->nullable();
            $table->string('assinatura_tipo')->nullable();
            $table->string('assinatura_cliente_url')->nullable();
            $table->dateTime('assinado_em')->nullable();
            $table->dateTime('solicitado_em')->nullable();
            $table->string('ip_assinatura')->nullable();
            $table->string('user_agent')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_cliente');
            $table->index('id_modelo');
            $table->timestamps();
        });

        Schema::create('locacao_checklist', function (Blueprint $table) {
            $table->bigIncrements('id_locacao_checklist');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->string('tipo')->nullable();
            $table->string('status')->nullable();
            $table->longText('assinatura_base64')->nullable();
            $table->string('assinado_por')->nullable();
            $table->dateTime('assinado_em')->nullable();
            $table->boolean('possui_avaria')->default(false);
            $table->text('observacoes_gerais')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->timestamps();
        });

        Schema::create('locacao_checklist_foto', function (Blueprint $table) {
            $table->bigIncrements('id_locacao_checklist_foto');
            $table->unsignedBigInteger('id_locacao_checklist')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_produto_locacao')->nullable();
            $table->string('tipo')->nullable();
            $table->string('url_foto')->nullable();
            $table->string('texto_watermark')->nullable();
            $table->boolean('voltou_com_defeito')->default(false);
            $table->boolean('alerta_avaria')->default(false);
            $table->text('observacao')->nullable();
            $table->dateTime('capturado_em')->nullable();
            $table->index('id_locacao_checklist');
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_produto_locacao');
            $table->timestamps();
        });

        Schema::create('locacao_despesas', function (Blueprint $table) {
            $table->bigIncrements('id_locacao_despesa');
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->text('descricao')->nullable();
            $table->string('tipo')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->dateTime('data_despesa')->nullable();
            $table->string('status')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_locacao');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locacao_modelos_contrato', function (Blueprint $table) {
            $table->bigIncrements('id_modelo');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->longText('conteudo_html')->nullable();
            $table->longText('cabecalho_html')->nullable();
            $table->longText('rodape_html')->nullable();
            $table->longText('css_personalizado')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('titulo_documento')->nullable();
            $table->string('subtitulo_documento')->nullable();
            $table->string('cor_primaria')->nullable();
            $table->string('cor_secundaria')->nullable();
            $table->string('cor_texto')->nullable();
            $table->string('cor_fundo')->nullable();
            $table->string('cor_borda')->nullable();
            $table->boolean('exibir_cabecalho')->default(true);
            $table->boolean('exibir_logo')->default(true);
            $table->boolean('exibir_assinatura_locadora')->default(true);
            $table->boolean('exibir_assinatura_cliente')->default(true);
            $table->string('assinatura_locadora_url')->nullable();
            $table->json('colunas_tabela_produtos')->nullable();
            $table->boolean('ativo')->default(true);
            $table->boolean('padrao')->default(false);
            $table->boolean('usa_medicao')->default(false);
            $table->index('id_empresa');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('produto_locacao', function (Blueprint $table) {
            $table->bigIncrements('id_produto_locacao');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_produto')->nullable();
            $table->unsignedBigInteger('id_patrimonio')->nullable();
            $table->unsignedBigInteger('id_sala')->nullable();
            $table->unsignedBigInteger('id_tabela_preco')->nullable();
            // Valores
            $table->decimal('preco_unitario', 15, 2)->nullable();
            $table->decimal('preco_total', 15, 2)->nullable();
            $table->integer('quantidade')->nullable();
            // Período específico do produto
            $table->date('data_inicio')->nullable();
            $table->string('hora_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->string('hora_fim')->nullable();
            $table->date('data_contrato')->nullable();
            $table->date('data_contrato_fim')->nullable();
            $table->string('hora_contrato')->nullable();
            $table->string('hora_contrato_fim')->nullable();
            // Tipo de cobrança e configurações
            $table->string('tipo_cobranca')->nullable();
            $table->string('tipo_movimentacao')->nullable();
            $table->string('status_retorno')->nullable();
            $table->integer('estoque_status')->nullable();
            $table->boolean('valor_fechado')->default(false);
            $table->boolean('voltou_com_defeito')->default(false);
            $table->integer('quantidade_com_defeito')->nullable();
            $table->text('observacao_defeito')->nullable();
            // Observações
            $table->text('observacoes')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_produto');
            $table->index('id_patrimonio');
            $table->index('id_sala');
            $table->index('id_tabela_preco');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locacao_retorno_patrimonios', function (Blueprint $table) {
            $table->bigIncrements('id_retorno');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_produto_locacao')->nullable();
            $table->unsignedBigInteger('id_patrimonio')->nullable();
            $table->dateTime('data_retorno')->nullable();
            $table->string('status_retorno')->nullable();
            $table->text('observacoes_retorno')->nullable();
            $table->string('foto_retorno')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_produto_locacao');
            $table->index('id_patrimonio');
            $table->index('id_usuario');
            $table->timestamps();
        });

        Schema::create('locacao_salas', function (Blueprint $table) {
            $table->bigIncrements('id_sala');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->integer('ordem')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('locacao_servicos', function (Blueprint $table) {
            $table->bigIncrements('id_locacao_servico');
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->text('descricao')->nullable();
            $table->string('tipo_item')->nullable();
            $table->integer('quantidade')->nullable();
            $table->decimal('preco_unitario', 15, 2)->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->unsignedBigInteger('id_sala')->nullable();
            $table->unsignedBigInteger('id_fornecedor')->nullable();
            $table->string('fornecedor_nome')->nullable();
            $table->decimal('custo_fornecedor', 15, 2)->nullable();
            $table->boolean('gerar_conta_pagar')->default(false);
            $table->date('conta_vencimento')->nullable();
            $table->decimal('conta_valor', 15, 2)->nullable();
            $table->integer('conta_parcelas')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_locacao');
            $table->index('id_sala');
            $table->index('id_fornecedor');
            $table->timestamps();
        });

        Schema::create('locacao_troca_produto', function (Blueprint $table) {
            $table->bigIncrements('id_locacao_troca_produto');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_produto_locacao')->nullable();
            $table->unsignedBigInteger('id_produto_anterior')->nullable();
            $table->unsignedBigInteger('id_produto_novo')->nullable();
            $table->integer('quantidade')->nullable();
            $table->string('motivo')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('estoque_movimentado')->default(false);
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_produto_locacao');
            $table->index('id_produto_anterior');
            $table->index('id_produto_novo');
            $table->index('id_usuario');
            $table->timestamps();
        });

        Schema::create('produto_terceiros_locacao', function (Blueprint $table) {
            $table->bigIncrements('id_produto_terceiros_locacao');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_produto_terceiro')->nullable();
            $table->string('nome_produto_manual')->nullable();
            $table->text('descricao_manual')->nullable();
            $table->unsignedBigInteger('id_fornecedor')->nullable();
            $table->unsignedBigInteger('id_sala')->nullable();
            $table->integer('quantidade')->nullable();
            $table->decimal('preco_unitario', 15, 2)->nullable();
            $table->boolean('valor_fechado')->default(false);
            $table->decimal('custo_fornecedor', 15, 2)->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->string('tipo_movimentacao')->nullable();
            $table->text('observacoes')->nullable();
            // Campos para gerar conta a pagar
            $table->boolean('gerar_conta_pagar')->default(false);
            $table->date('conta_vencimento')->nullable();
            $table->decimal('conta_valor', 15, 2)->nullable();
            $table->integer('conta_parcelas')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_produto_terceiro');
            $table->index('id_fornecedor');
            $table->index('id_sala');
            $table->timestamps();
            $table->softDeletes();
        });

        // =====================================================================
        // DOMAIN: VENDA
        // =====================================================================

        Schema::create('vendas', function (Blueprint $table) {
            $table->bigIncrements('id_venda');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->unsignedBigInteger('id_forma_pagamento')->nullable();
            $table->integer('numero_venda')->nullable();
            $table->dateTime('data_venda')->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->decimal('desconto', 15, 2)->nullable();
            $table->decimal('acrescimo', 15, 2)->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->decimal('valor_recebido', 15, 2)->nullable();
            $table->decimal('troco', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->string('status')->nullable();
            $table->index('id_empresa');
            $table->index('id_cliente');
            $table->index('id_usuario');
            $table->index('id_forma_pagamento');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('venda_itens', function (Blueprint $table) {
            $table->bigIncrements('id_venda_item');
            $table->unsignedBigInteger('id_venda')->nullable();
            $table->unsignedBigInteger('id_produto_venda')->nullable();
            $table->string('nome_produto')->nullable();
            $table->string('codigo_produto')->nullable();
            $table->integer('quantidade')->nullable();
            $table->decimal('preco_unitario', 15, 2)->nullable();
            $table->decimal('desconto', 15, 2)->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->index('id_venda');
            $table->index('id_produto_venda');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        // VENDA
        Schema::dropIfExists('venda_itens');
        Schema::dropIfExists('vendas');

        // LOCACAO
        Schema::dropIfExists('produto_terceiros_locacao');
        Schema::dropIfExists('locacao_troca_produto');
        Schema::dropIfExists('locacao_servicos');
        Schema::dropIfExists('locacao_salas');
        Schema::dropIfExists('locacao_retorno_patrimonios');
        Schema::dropIfExists('produto_locacao');
        Schema::dropIfExists('locacao_modelos_contrato');
        Schema::dropIfExists('locacao_despesas');
        Schema::dropIfExists('locacao_checklist_foto');
        Schema::dropIfExists('locacao_checklist');
        Schema::dropIfExists('locacao_assinaturas_digitais');
        Schema::dropIfExists('locacao');

        // PRODUTO
        Schema::dropIfExists('tabela_precos');
        Schema::dropIfExists('produtos_venda');
        Schema::dropIfExists('produtos_terceiros');
        Schema::dropIfExists('produto_historico');
        Schema::dropIfExists('produto_acessorios');
        Schema::dropIfExists('patrimonio_historico');
        Schema::dropIfExists('movimentacoes_estoque');
        Schema::dropIfExists('manutencoes');
        Schema::dropIfExists('patrimonios');
        Schema::dropIfExists('acessorios');
        Schema::dropIfExists('produtos');

        // CLIENTE
        Schema::dropIfExists('clientes');

        // AUTH
        Schema::dropIfExists('usuario_permissoes');
        Schema::dropIfExists('empresa');
    }
};
