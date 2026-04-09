<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assinaturas_planos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_plano')->nullable();
            $table->unsignedBigInteger('id_plano_contratado')->nullable();
            $table->string('origem')->nullable();
            $table->string('status')->nullable();
            $table->string('metodo_adesao')->nullable();
            $table->string('metodo_mensal')->nullable();
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_subscription_id')->nullable();
            $table->date('proxima_cobranca_em')->nullable();
            $table->dateTime('ultimo_pagamento_em')->nullable();
            $table->date('inadimplente_desde')->nullable();
            $table->boolean('bloqueada_por_inadimplencia')->default(false);
            $table->text('observacoes')->nullable();
            $table->dateTime('cancelamento_solicitado_em')->nullable();
            $table->date('cancelamento_efetivo_em')->nullable();
            $table->text('motivo_cancelamento')->nullable();
            $table->index('id_empresa');
            $table->index('id_plano');
            $table->index('id_plano_contratado');
            $table->index('asaas_customer_id');
            $table->index('asaas_subscription_id');
            $table->timestamps();
        });

        Schema::create('assinaturas_planos_pagamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_assinatura_plano')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_plano')->nullable();
            $table->unsignedBigInteger('id_plano_contratado')->nullable();
            $table->string('tipo_cobranca')->nullable();
            $table->date('competencia')->nullable();
            $table->string('metodo_pagamento')->nullable();
            $table->string('asaas_payment_id')->nullable();
            $table->string('asaas_invoice_url')->nullable();
            $table->string('asaas_bank_slip_url')->nullable();
            $table->string('asaas_pix_qr_code')->nullable();
            $table->string('asaas_pix_copy_paste')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('data_vencimento')->nullable();
            $table->dateTime('data_pagamento')->nullable();
            $table->string('status')->nullable();
            $table->json('json_resposta')->nullable();
            $table->json('json_webhook')->nullable();
            $table->integer('tentativas')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_assinatura_plano');
            $table->index('id_empresa');
            $table->index('id_plano');
            $table->index('id_plano_contratado');
            $table->index('asaas_payment_id');
            $table->timestamps();
        });

        Schema::create('banco_boleto_config', function (Blueprint $table) {
            $table->bigIncrements('id_config');
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->unsignedBigInteger('id_banco_boleto')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('api_key')->nullable();
            $table->string('token')->nullable();
            $table->string('convenio')->nullable();
            $table->string('carteira')->nullable();
            $table->string('arquivo_certificado')->nullable();
            $table->string('arquivo_chave')->nullable();
            $table->decimal('juros_mora', 15, 2)->nullable();
            $table->decimal('multa_atraso', 15, 2)->nullable();
            $table->integer('dias_protesto')->nullable();
            $table->string('instrucao_1')->nullable();
            $table->string('instrucao_2')->nullable();
            $table->boolean('webhook_ativo')->default(false);
            $table->boolean('ativo')->default(false);
            $table->index('id_bancos');
            $table->index('id_banco_boleto');
            $table->index('id_empresa');
            $table->index('client_id');
            $table->timestamps();
        });

        Schema::create('bancos', function (Blueprint $table) {
            $table->bigIncrements('id_bancos');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome_banco')->nullable();
            $table->string('agencia')->nullable();
            $table->string('conta')->nullable();
            $table->decimal('saldo_inicial', 15, 2)->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('gera_boleto')->default(false);
            $table->index('id_empresa');
            $table->timestamps();
        });

        Schema::create('bancos_boleto', function (Blueprint $table) {
            $table->bigIncrements('id_banco_boleto');
            $table->string('nome')->nullable();
            $table->string('codigo_banco')->nullable();
            $table->boolean('ativo')->default(false);
            $table->boolean('requer_certificado')->default(false);
            $table->boolean('requer_chave')->default(false);
            $table->boolean('requer_client_id')->default(false);
            $table->boolean('requer_client_secret')->default(false);
            $table->boolean('requer_api_key')->default(false);
            $table->boolean('requer_token')->default(false);
            $table->boolean('requer_convenio')->default(false);
            $table->boolean('requer_carteira')->default(false);
            $table->text('instrucoes')->nullable();
            $table->timestamps();
        });

        Schema::create('boletos', function (Blueprint $table) {
            $table->bigIncrements('id_boleto');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_conta_receber')->nullable();
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->unsignedBigInteger('id_banco_boleto')->nullable();
            $table->string('codigo_solicitacao')->nullable();
            $table->string('nosso_numero')->nullable();
            $table->string('linha_digitavel')->nullable();
            $table->string('codigo_barras')->nullable();
            $table->decimal('valor_nominal', 15, 2)->nullable();
            $table->decimal('valor_pago', 15, 2)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->dateTime('data_pagamento')->nullable();
            $table->string('status')->nullable();
            $table->string('situacao_banco')->nullable();
            $table->string('url_pdf')->nullable();
            $table->json('json_resposta')->nullable();
            $table->json('json_webhook')->nullable();
            $table->index('id_empresa');
            $table->index('id_conta_receber');
            $table->index('id_bancos');
            $table->index('id_banco_boleto');
            $table->timestamps();
        });

        Schema::create('boletos_historico', function (Blueprint $table) {
            $table->bigIncrements('id_historico');
            $table->unsignedBigInteger('id_boleto')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('tipo')->nullable();
            $table->text('conteudo')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->index('id_boleto');
            $table->index('id_empresa');
        });

        Schema::create('categoria_contas', function (Blueprint $table) {
            $table->bigIncrements('id_categoria_contas');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome')->nullable();
            $table->string('tipo')->nullable();
            $table->text('descricao')->nullable();
            $table->index('id_empresa');
            $table->timestamps();
        });

        Schema::create('categorias_menu', function (Blueprint $table) {
            $table->bigIncrements('id_categoria');
            $table->string('nome')->nullable();
            $table->string('cor')->nullable();
            $table->string('icone')->nullable();
            $table->integer('ordem')->nullable();
            $table->boolean('ativo')->default(false);
            $table->timestamps();
        });

        Schema::create('client_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_plano')->nullable();
            $table->unsignedBigInteger('id_plano_contratado')->nullable();
            $table->string('cliente_razao_social')->nullable();
            $table->string('cliente_cnpj_cpf')->nullable();
            $table->string('cliente_email')->nullable();
            $table->string('cliente_endereco')->nullable();
            $table->decimal('valor_adesao', 15, 2)->nullable();
            $table->decimal('valor_mensalidade', 15, 2)->nullable();
            $table->json('limites_contratados')->nullable();
            $table->string('versao_contrato')->nullable();
            $table->string('titulo_contrato')->nullable();
            $table->longText('corpo_contrato')->nullable();
            $table->string('hash_documento')->nullable();
            $table->longText('assinatura_base64')->nullable();
            $table->string('assinado_por_nome')->nullable();
            $table->string('assinado_por_documento')->nullable();
            $table->string('assinado_por_email')->nullable();
            $table->string('ip_aceite')->nullable();
            $table->string('user_agent')->nullable();
            $table->dateTime('aceito_em')->nullable();
            $table->boolean('recibo_gerado')->default(false);
            $table->string('recibo_path')->nullable();
            $table->dateTime('recibo_gerado_em')->nullable();
            $table->string('status')->nullable();
            $table->text('motivo_revogacao')->nullable();
            $table->dateTime('revogado_em')->nullable();
            $table->index('id_empresa');
            $table->index('id_plano');
            $table->index('id_plano_contratado');
            $table->timestamps();
        });

        Schema::create('contas_a_pagar', function (Blueprint $table) {
            $table->bigIncrements('id_contas');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_clientes')->nullable();
            $table->unsignedBigInteger('id_fornecedores')->nullable();
            $table->unsignedBigInteger('id_venda')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_origem')->nullable();
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->unsignedBigInteger('id_categoria_contas')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->text('descricao')->nullable();
            $table->string('documento')->nullable();
            $table->string('boleto')->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->decimal('valor_pago', 15, 2)->nullable();
            $table->decimal('juros', 15, 2)->nullable();
            $table->decimal('multa', 15, 2)->nullable();
            $table->decimal('desconto', 15, 2)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->string('status')->nullable();
            $table->string('origem')->nullable();
            $table->unsignedBigInteger('id_forma_pagamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('numero_parcela')->nullable();
            $table->string('total_parcelas')->nullable();
            $table->unsignedBigInteger('id_parcelamento')->nullable();
            $table->string('tipo_recorrencia')->nullable();
            $table->string('quantidade_recorrencias')->nullable();
            $table->unsignedBigInteger('id_recorrencia')->nullable();
            $table->string('is_recorrente')->nullable();
            $table->index('id_empresa');
            $table->index('id_clientes');
            $table->index('id_fornecedores');
            $table->index('id_venda');
            $table->index('id_locacao');
            $table->index('id_origem');
            $table->index('id_bancos');
            $table->index('id_categoria_contas');
            $table->index('id_usuario');
            $table->index('id_forma_pagamento');
            $table->index('id_parcelamento');
            $table->index('id_recorrencia');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contas_a_receber', function (Blueprint $table) {
            $table->bigIncrements('id_contas');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_clientes')->nullable();
            $table->unsignedBigInteger('id_fornecedores')->nullable();
            $table->unsignedBigInteger('id_venda')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->unsignedBigInteger('id_categoria_contas')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->text('descricao')->nullable();
            $table->string('documento')->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->decimal('valor_pago', 15, 2)->nullable();
            $table->decimal('juros', 15, 2)->nullable();
            $table->decimal('multa', 15, 2)->nullable();
            $table->decimal('desconto', 15, 2)->nullable();
            $table->date('data_emissao')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('id_forma_pagamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('numero_parcela')->nullable();
            $table->string('total_parcelas')->nullable();
            $table->unsignedBigInteger('id_parcelamento')->nullable();
            $table->string('tipo_recorrencia')->nullable();
            $table->string('quantidade_recorrencias')->nullable();
            $table->unsignedBigInteger('id_recorrencia')->nullable();
            $table->string('is_recorrente')->nullable();
            $table->index('id_empresa');
            $table->index('id_clientes');
            $table->index('id_fornecedores');
            $table->index('id_venda');
            $table->index('id_locacao');
            $table->index('id_bancos');
            $table->index('id_categoria_contas');
            $table->index('id_usuario');
            $table->index('id_forma_pagamento');
            $table->index('id_parcelamento');
            $table->index('id_recorrencia');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empresa_contratos_software', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('versao_contrato')->nullable();
            $table->string('titulo_contrato')->nullable();
            $table->longText('corpo_contrato')->nullable();
            $table->string('hash_documento')->nullable();
            $table->longText('assinatura_base64')->nullable();
            $table->string('assinado_por_nome')->nullable();
            $table->string('assinado_por_documento')->nullable();
            $table->string('assinatura_ip')->nullable();
            $table->dateTime('assinado_em')->nullable();
            $table->string('status')->nullable();
            $table->index('id_empresa');
            $table->timestamps();
        });

        Schema::create('faturamento_locacoes', function (Blueprint $table) {
            $table->bigIncrements('id_faturamento_locacao');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_locacao')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('numero_fatura')->nullable();
            $table->unsignedBigInteger('id_cliente')->nullable();
            $table->unsignedBigInteger('id_conta_receber')->nullable();
            $table->unsignedBigInteger('id_grupo_faturamento')->nullable();
            $table->text('descricao')->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->date('data_faturamento')->nullable();
            $table->date('data_vencimento')->nullable();
            $table->string('status')->nullable();
            $table->string('origem')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_empresa');
            $table->index('id_locacao');
            $table->index('id_usuario');
            $table->index('id_cliente');
            $table->index('id_conta_receber');
            $table->index('id_grupo_faturamento');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fluxo_caixa', function (Blueprint $table) {
            $table->bigIncrements('id_fluxo');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('tipo')->nullable();
            $table->text('descricao')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->date('data_movimentacao')->nullable();
            $table->unsignedBigInteger('id_conta_pagar')->nullable();
            $table->unsignedBigInteger('id_conta_receber')->nullable();
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->unsignedBigInteger('id_categoria_fluxo')->nullable();
            $table->unsignedBigInteger('id_forma_pagamento')->nullable();
            $table->index('id_empresa');
            $table->index('id_conta_pagar');
            $table->index('id_conta_receber');
            $table->index('id_bancos');
            $table->index('id_categoria_fluxo');
            $table->index('id_forma_pagamento');
            $table->timestamps();
        });

        Schema::create('forma_pagamento', function (Blueprint $table) {
            $table->bigIncrements('id_forma_pagamento');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->index('id_empresa');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fornecedores', function (Blueprint $table) {
            $table->bigIncrements('id_fornecedores');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome')->nullable();
            $table->string('cep')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('rg_ie')->nullable();
            $table->string('cpf_cnpj')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('nome_empresa')->nullable();
            $table->date('data_abertura')->nullable();
            $table->string('bairro')->nullable();
            $table->string('uf')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('contato_nome')->nullable();
            $table->string('contato_cargo')->nullable();
            $table->string('telefone')->nullable();
            $table->string('email')->nullable();
            $table->string('prazo_medio_entrega_dias')->nullable();
            $table->unsignedBigInteger('id_categoria_fornecedor')->nullable();
            $table->string('banco_agencia')->nullable();
            $table->string('banco_conta')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('id_tipo_pessoa')->nullable();
            $table->string('status')->nullable();
            $table->index('id_empresa');
            $table->index('id_categoria_fornecedor');
            $table->index('id_tipo_pessoa');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('modulos', function (Blueprint $table) {
            $table->bigIncrements('id_modulo');
            $table->string('nome')->nullable();
            $table->unsignedBigInteger('id_modulo_pai')->nullable();
            $table->text('descricao')->nullable();
            $table->string('icone')->nullable();
            $table->string('rota')->nullable();
            $table->integer('ordem')->nullable();
            $table->string('categoria')->nullable();
            $table->boolean('ativo')->default(false);
            $table->boolean('tem_submodulos')->default(false);
            $table->index('id_modulo_pai');
        });

        Schema::create('pagamentos_contas_pagar', function (Blueprint $table) {
            $table->bigIncrements('id_pagamento');
            $table->unsignedBigInteger('id_conta_pagar')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->decimal('valor_pago', 15, 2)->nullable();
            $table->unsignedBigInteger('id_forma_pagamento')->nullable();
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->unsignedBigInteger('id_fluxo_caixa')->nullable();
            $table->index('id_conta_pagar');
            $table->index('id_empresa');
            $table->index('id_forma_pagamento');
            $table->index('id_bancos');
            $table->index('id_usuario');
            $table->index('id_fluxo_caixa');
            $table->timestamps();
        });

        Schema::create('pagamentos_contas_receber', function (Blueprint $table) {
            $table->bigIncrements('id_pagamento');
            $table->unsignedBigInteger('id_conta_receber')->nullable();
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->decimal('valor_pago', 15, 2)->nullable();
            $table->unsignedBigInteger('id_forma_pagamento')->nullable();
            $table->unsignedBigInteger('id_bancos')->nullable();
            $table->text('observacoes')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->unsignedBigInteger('id_fluxo_caixa')->nullable();
            $table->index('id_conta_receber');
            $table->index('id_empresa');
            $table->index('id_forma_pagamento');
            $table->index('id_bancos');
            $table->index('id_usuario');
            $table->index('id_fluxo_caixa');
            $table->timestamps();
        });

        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('email')->nullable();
            $table->string('code')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('used')->default(false);
            $table->timestamps();
        });

        Schema::create('planos', function (Blueprint $table) {
            $table->bigIncrements('id_plano');
            $table->string('nome')->nullable();
            $table->text('descricao')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->decimal('adesao', 15, 2)->nullable();
            $table->string('relatorios')->nullable();
            $table->string('bancos')->nullable();
            $table->string('assinatura_digital')->nullable();
            $table->string('contratos')->nullable();
            $table->string('faturas')->nullable();
            $table->boolean('ativo')->default(false);
            $table->timestamps();
        });

        Schema::create('planos_contratados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('nome')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->decimal('adesao', 15, 2)->nullable();
            $table->dateTime('data_contratacao')->nullable();
            $table->string('status')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_empresa');
            $table->timestamps();
        });

        Schema::create('planos_contratados_modulos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_plano_contratado')->nullable();
            $table->unsignedBigInteger('id_modulo')->nullable();
            $table->integer('limite')->nullable();
            $table->boolean('ativo')->default(false);
            $table->index('id_plano_contratado');
            $table->index('id_modulo');
            $table->timestamps();
        });

        Schema::create('planos_modulos', function (Blueprint $table) {
            $table->bigIncrements('id_plano_modulo');
            $table->unsignedBigInteger('id_plano')->nullable();
            $table->unsignedBigInteger('id_modulo')->nullable();
            $table->integer('limite')->nullable();
            $table->boolean('ativo')->default(false);
            $table->index('id_plano');
            $table->index('id_modulo');
        });

        Schema::create('planos_promocoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_plano')->nullable();
            $table->string('nome')->nullable();
            $table->string('tipo_regra')->nullable();
            $table->date('data_inicio')->nullable();
            $table->date('data_fim')->nullable();
            $table->integer('quantidade_clientes_min')->nullable();
            $table->integer('quantidade_clientes_max')->nullable();
            $table->decimal('desconto_mensal', 15, 2)->nullable();
            $table->decimal('desconto_adesao', 15, 2)->nullable();
            $table->boolean('ativo')->default(false);
            $table->integer('prioridade')->nullable();
            $table->text('observacoes')->nullable();
            $table->index('id_plano');
            $table->timestamps();
        });

        Schema::create('registro_atividades', function (Blueprint $table) {
            $table->bigIncrements('id_registro');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->unsignedBigInteger('id_usuario')->nullable();
            $table->string('nome_responsavel')->nullable();
            $table->string('email_responsavel')->nullable();
            $table->string('acao')->nullable();
            $table->text('descricao')->nullable();
            $table->string('entidade_tipo')->nullable();
            $table->unsignedBigInteger('entidade_id')->nullable();
            $table->string('entidade_label')->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->json('contexto')->nullable();
            $table->json('antes')->nullable();
            $table->json('depois')->nullable();
            $table->string('ip')->nullable();
            $table->string('origem')->nullable();
            $table->string('icone')->nullable();
            $table->string('cor')->nullable();
            $table->json('tags')->nullable();
            $table->dateTime('ocorrido_em')->nullable();
            $table->index('id_empresa');
            $table->index('id_usuario');
            $table->index('entidade_id');
        });

        Schema::create('usuarios', function (Blueprint $table) {
            $table->bigIncrements('id_usuario');
            $table->unsignedBigInteger('id_empresa')->nullable();
            $table->string('login')->nullable();
            $table->string('nome')->nullable();
            $table->string('senha')->nullable();
            $table->unsignedBigInteger('id_permissoes')->nullable();
            $table->boolean('is_suporte')->default(false);
            $table->string('telefone')->nullable();
            $table->string('status')->nullable();
            $table->string('cpf')->nullable();
            $table->string('rg')->nullable();
            $table->decimal('comissao', 15, 2)->nullable();
            $table->string('endereco')->nullable();
            $table->string('cep')->nullable();
            $table->string('bairro')->nullable();
            $table->string('finalidade')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('codigo_reset')->nullable();
            $table->string('google_calendar_token')->nullable();
            $table->string('tema')->nullable();
            $table->string('remember_token')->nullable();
            $table->string('session_token')->nullable();
            $table->dateTime('data_ultimo_acesso')->nullable();
            $table->index('id_empresa');
            $table->index('id_permissoes');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('registro_atividades');
        Schema::dropIfExists('planos_promocoes');
        Schema::dropIfExists('planos_modulos');
        Schema::dropIfExists('planos_contratados_modulos');
        Schema::dropIfExists('planos_contratados');
        Schema::dropIfExists('planos');
        Schema::dropIfExists('password_reset_codes');
        Schema::dropIfExists('pagamentos_contas_receber');
        Schema::dropIfExists('pagamentos_contas_pagar');
        Schema::dropIfExists('modulos');
        Schema::dropIfExists('fornecedores');
        Schema::dropIfExists('forma_pagamento');
        Schema::dropIfExists('fluxo_caixa');
        Schema::dropIfExists('faturamento_locacoes');
        Schema::dropIfExists('empresa_contratos_software');
        Schema::dropIfExists('contas_a_receber');
        Schema::dropIfExists('contas_a_pagar');
        Schema::dropIfExists('client_contracts');
        Schema::dropIfExists('categorias_menu');
        Schema::dropIfExists('categoria_contas');
        Schema::dropIfExists('boletos_historico');
        Schema::dropIfExists('boletos');
        Schema::dropIfExists('bancos_boleto');
        Schema::dropIfExists('bancos');
        Schema::dropIfExists('banco_boleto_config');
        Schema::dropIfExists('assinaturas_planos_pagamentos');
        Schema::dropIfExists('assinaturas_planos');
    }
};
