<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adiciona FK apenas se ambas tabelas e colunas existirem
     */
    private function addForeignKeyIfPossible(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn
    ): void {
        if (!Schema::hasTable($table)) {
            return;
        }
        if (!Schema::hasColumn($table, $column)) {
            return;
        }
        if (!Schema::hasTable($referencedTable)) {
            return;
        }
        if (!Schema::hasColumn($referencedTable, $referencedColumn)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($column, $referencedTable, $referencedColumn) {
            $t->foreign($column)
              ->references($referencedColumn)
              ->on($referencedTable)
              ->nullOnDelete();
        });
    }

    public function up(): void
    {
        // =====================================================================
        // DOMAIN: AUTH - Foreign Keys
        // =====================================================================

        // empresa
        $this->addForeignKeyIfPossible('empresa', 'id_plano', 'planos', 'id_plano');
        $this->addForeignKeyIfPossible('empresa', 'id_plano_teste', 'planos', 'id_plano');
        $this->addForeignKeyIfPossible('empresa', 'id_empresa_matriz', 'empresa', 'id_empresa');

        // usuario_permissoes
        $this->addForeignKeyIfPossible('usuario_permissoes', 'id_usuario', 'usuarios', 'id_usuario');
        $this->addForeignKeyIfPossible('usuario_permissoes', 'id_modulo', 'modulos', 'id_modulo');

        // =====================================================================
        // DOMAIN: CLIENTE - Foreign Keys
        // =====================================================================

        // clientes
        $this->addForeignKeyIfPossible('clientes', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('clientes', 'id_filial', 'empresa', 'id_empresa');

        // =====================================================================
        // DOMAIN: PRODUTO - Foreign Keys
        // =====================================================================

        // produtos
        $this->addForeignKeyIfPossible('produtos', 'id_empresa', 'empresa', 'id_empresa');

        // acessorios
        $this->addForeignKeyIfPossible('acessorios', 'id_empresa', 'empresa', 'id_empresa');

        // patrimonios
        $this->addForeignKeyIfPossible('patrimonios', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('patrimonios', 'id_produto', 'produtos', 'id_produto');

        // manutencoes
        $this->addForeignKeyIfPossible('manutencoes', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('manutencoes', 'id_produto', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('manutencoes', 'id_patrimonio', 'patrimonios', 'id_patrimonio');

        // movimentacoes_estoque
        $this->addForeignKeyIfPossible('movimentacoes_estoque', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('movimentacoes_estoque', 'id_produto', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('movimentacoes_estoque', 'id_usuario', 'usuarios', 'id_usuario');

        // patrimonio_historico
        $this->addForeignKeyIfPossible('patrimonio_historico', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('patrimonio_historico', 'id_patrimonio', 'patrimonios', 'id_patrimonio');
        $this->addForeignKeyIfPossible('patrimonio_historico', 'id_produto', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('patrimonio_historico', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('patrimonio_historico', 'id_cliente', 'clientes', 'id_clientes');
        $this->addForeignKeyIfPossible('patrimonio_historico', 'id_usuario', 'usuarios', 'id_usuario');

        // produto_acessorios
        $this->addForeignKeyIfPossible('produto_acessorios', 'id_produto', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('produto_acessorios', 'id_acessorio', 'acessorios', 'id_acessorio');

        // produto_historico
        $this->addForeignKeyIfPossible('produto_historico', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('produto_historico', 'id_produto', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('produto_historico', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('produto_historico', 'id_cliente', 'clientes', 'id_clientes');
        $this->addForeignKeyIfPossible('produto_historico', 'id_usuario', 'usuarios', 'id_usuario');

        // produtos_terceiros
        $this->addForeignKeyIfPossible('produtos_terceiros', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('produtos_terceiros', 'id_fornecedor', 'fornecedores', 'id_fornecedores');

        // produtos_venda
        $this->addForeignKeyIfPossible('produtos_venda', 'id_empresa', 'empresa', 'id_empresa');

        // tabela_precos
        $this->addForeignKeyIfPossible('tabela_precos', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('tabela_precos', 'id_produto', 'produtos', 'id_produto');

        // =====================================================================
        // DOMAIN: LOCACAO - Foreign Keys
        // =====================================================================

        // locacao
        $this->addForeignKeyIfPossible('locacao', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao', 'id_cliente', 'clientes', 'id_clientes');
        $this->addForeignKeyIfPossible('locacao', 'id_usuario', 'usuarios', 'id_usuario');
        $this->addForeignKeyIfPossible('locacao', 'id_locacao_origem', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('locacao', 'id_locacao_anterior', 'locacao', 'id_locacao');

        // locacao_assinaturas_digitais
        $this->addForeignKeyIfPossible('locacao_assinaturas_digitais', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao_assinaturas_digitais', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('locacao_assinaturas_digitais', 'id_cliente', 'clientes', 'id_clientes');
        $this->addForeignKeyIfPossible('locacao_assinaturas_digitais', 'id_modelo', 'locacao_modelos_contrato', 'id_modelo');

        // locacao_checklist
        $this->addForeignKeyIfPossible('locacao_checklist', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao_checklist', 'id_locacao', 'locacao', 'id_locacao');

        // locacao_checklist_foto
        $this->addForeignKeyIfPossible('locacao_checklist_foto', 'id_locacao_checklist', 'locacao_checklist', 'id_locacao_checklist');
        $this->addForeignKeyIfPossible('locacao_checklist_foto', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao_checklist_foto', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('locacao_checklist_foto', 'id_produto_locacao', 'produto_locacao', 'id_produto_locacao');

        // locacao_despesas
        $this->addForeignKeyIfPossible('locacao_despesas', 'id_locacao', 'locacao', 'id_locacao');

        // locacao_modelos_contrato
        $this->addForeignKeyIfPossible('locacao_modelos_contrato', 'id_empresa', 'empresa', 'id_empresa');

        // produto_locacao
        $this->addForeignKeyIfPossible('produto_locacao', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('produto_locacao', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('produto_locacao', 'id_produto', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('produto_locacao', 'id_patrimonio', 'patrimonios', 'id_patrimonio');
        $this->addForeignKeyIfPossible('produto_locacao', 'id_sala', 'locacao_salas', 'id_sala');
        $this->addForeignKeyIfPossible('produto_locacao', 'id_tabela_preco', 'tabela_precos', 'id_tabela');

        // locacao_retorno_patrimonios
        $this->addForeignKeyIfPossible('locacao_retorno_patrimonios', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao_retorno_patrimonios', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('locacao_retorno_patrimonios', 'id_produto_locacao', 'produto_locacao', 'id_produto_locacao');
        $this->addForeignKeyIfPossible('locacao_retorno_patrimonios', 'id_patrimonio', 'patrimonios', 'id_patrimonio');
        $this->addForeignKeyIfPossible('locacao_retorno_patrimonios', 'id_usuario', 'usuarios', 'id_usuario');

        // locacao_salas
        $this->addForeignKeyIfPossible('locacao_salas', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao_salas', 'id_locacao', 'locacao', 'id_locacao');

        // locacao_servicos
        $this->addForeignKeyIfPossible('locacao_servicos', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('locacao_servicos', 'id_sala', 'locacao_salas', 'id_sala');
        $this->addForeignKeyIfPossible('locacao_servicos', 'id_fornecedor', 'fornecedores', 'id_fornecedores');

        // locacao_troca_produto
        $this->addForeignKeyIfPossible('locacao_troca_produto', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('locacao_troca_produto', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('locacao_troca_produto', 'id_produto_locacao', 'produto_locacao', 'id_produto_locacao');
        $this->addForeignKeyIfPossible('locacao_troca_produto', 'id_produto_anterior', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('locacao_troca_produto', 'id_produto_novo', 'produtos', 'id_produto');
        $this->addForeignKeyIfPossible('locacao_troca_produto', 'id_usuario', 'usuarios', 'id_usuario');

        // produto_terceiros_locacao
        $this->addForeignKeyIfPossible('produto_terceiros_locacao', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('produto_terceiros_locacao', 'id_locacao', 'locacao', 'id_locacao');
        $this->addForeignKeyIfPossible('produto_terceiros_locacao', 'id_produto_terceiro', 'produtos_terceiros', 'id_produto_terceiro');
        $this->addForeignKeyIfPossible('produto_terceiros_locacao', 'id_fornecedor', 'fornecedores', 'id_fornecedores');
        $this->addForeignKeyIfPossible('produto_terceiros_locacao', 'id_sala', 'locacao_salas', 'id_sala');

        // =====================================================================
        // DOMAIN: VENDA - Foreign Keys
        // =====================================================================

        // vendas
        $this->addForeignKeyIfPossible('vendas', 'id_empresa', 'empresa', 'id_empresa');
        $this->addForeignKeyIfPossible('vendas', 'id_cliente', 'clientes', 'id_clientes');
        $this->addForeignKeyIfPossible('vendas', 'id_usuario', 'usuarios', 'id_usuario');
        $this->addForeignKeyIfPossible('vendas', 'id_forma_pagamento', 'forma_pagamento', 'id_forma_pagamento');

        // venda_itens
        $this->addForeignKeyIfPossible('venda_itens', 'id_venda', 'vendas', 'id_venda');
        $this->addForeignKeyIfPossible('venda_itens', 'id_produto_venda', 'produtos_venda', 'id_produto_venda');
    }

    public function down(): void
    {
        $foreignKeys = [
            // VENDA
            ['venda_itens', 'venda_itens_id_venda_foreign'],
            ['venda_itens', 'venda_itens_id_produto_venda_foreign'],
            ['vendas', 'vendas_id_empresa_foreign'],
            ['vendas', 'vendas_id_cliente_foreign'],
            ['vendas', 'vendas_id_usuario_foreign'],
            ['vendas', 'vendas_id_forma_pagamento_foreign'],

            // LOCACAO
            ['produto_terceiros_locacao', 'produto_terceiros_locacao_id_empresa_foreign'],
            ['produto_terceiros_locacao', 'produto_terceiros_locacao_id_locacao_foreign'],
            ['produto_terceiros_locacao', 'produto_terceiros_locacao_id_produto_terceiro_foreign'],
            ['produto_terceiros_locacao', 'produto_terceiros_locacao_id_fornecedor_foreign'],
            ['produto_terceiros_locacao', 'produto_terceiros_locacao_id_sala_foreign'],
            ['locacao_troca_produto', 'locacao_troca_produto_id_empresa_foreign'],
            ['locacao_troca_produto', 'locacao_troca_produto_id_locacao_foreign'],
            ['locacao_troca_produto', 'locacao_troca_produto_id_produto_locacao_foreign'],
            ['locacao_troca_produto', 'locacao_troca_produto_id_produto_anterior_foreign'],
            ['locacao_troca_produto', 'locacao_troca_produto_id_produto_novo_foreign'],
            ['locacao_troca_produto', 'locacao_troca_produto_id_usuario_foreign'],
            ['locacao_servicos', 'locacao_servicos_id_locacao_foreign'],
            ['locacao_servicos', 'locacao_servicos_id_sala_foreign'],
            ['locacao_servicos', 'locacao_servicos_id_fornecedor_foreign'],
            ['locacao_salas', 'locacao_salas_id_empresa_foreign'],
            ['locacao_salas', 'locacao_salas_id_locacao_foreign'],
            ['locacao_retorno_patrimonios', 'locacao_retorno_patrimonios_id_empresa_foreign'],
            ['locacao_retorno_patrimonios', 'locacao_retorno_patrimonios_id_locacao_foreign'],
            ['locacao_retorno_patrimonios', 'locacao_retorno_patrimonios_id_produto_locacao_foreign'],
            ['locacao_retorno_patrimonios', 'locacao_retorno_patrimonios_id_patrimonio_foreign'],
            ['locacao_retorno_patrimonios', 'locacao_retorno_patrimonios_id_usuario_foreign'],
            ['produto_locacao', 'produto_locacao_id_empresa_foreign'],
            ['produto_locacao', 'produto_locacao_id_locacao_foreign'],
            ['produto_locacao', 'produto_locacao_id_produto_foreign'],
            ['produto_locacao', 'produto_locacao_id_patrimonio_foreign'],
            ['produto_locacao', 'produto_locacao_id_sala_foreign'],
            ['produto_locacao', 'produto_locacao_id_tabela_preco_foreign'],
            ['locacao_modelos_contrato', 'locacao_modelos_contrato_id_empresa_foreign'],
            ['locacao_despesas', 'locacao_despesas_id_locacao_foreign'],
            ['locacao_checklist_foto', 'locacao_checklist_foto_id_locacao_checklist_foreign'],
            ['locacao_checklist_foto', 'locacao_checklist_foto_id_empresa_foreign'],
            ['locacao_checklist_foto', 'locacao_checklist_foto_id_locacao_foreign'],
            ['locacao_checklist_foto', 'locacao_checklist_foto_id_produto_locacao_foreign'],
            ['locacao_checklist', 'locacao_checklist_id_empresa_foreign'],
            ['locacao_checklist', 'locacao_checklist_id_locacao_foreign'],
            ['locacao_assinaturas_digitais', 'locacao_assinaturas_digitais_id_empresa_foreign'],
            ['locacao_assinaturas_digitais', 'locacao_assinaturas_digitais_id_locacao_foreign'],
            ['locacao_assinaturas_digitais', 'locacao_assinaturas_digitais_id_cliente_foreign'],
            ['locacao_assinaturas_digitais', 'locacao_assinaturas_digitais_id_modelo_foreign'],
            ['locacao', 'locacao_id_empresa_foreign'],
            ['locacao', 'locacao_id_cliente_foreign'],
            ['locacao', 'locacao_id_usuario_foreign'],
            ['locacao', 'locacao_id_locacao_origem_foreign'],
            ['locacao', 'locacao_id_locacao_anterior_foreign'],

            // PRODUTO
            ['tabela_precos', 'tabela_precos_id_empresa_foreign'],
            ['tabela_precos', 'tabela_precos_id_produto_foreign'],
            ['produtos_venda', 'produtos_venda_id_empresa_foreign'],
            ['produtos_terceiros', 'produtos_terceiros_id_empresa_foreign'],
            ['produtos_terceiros', 'produtos_terceiros_id_fornecedor_foreign'],
            ['produto_historico', 'produto_historico_id_empresa_foreign'],
            ['produto_historico', 'produto_historico_id_produto_foreign'],
            ['produto_historico', 'produto_historico_id_locacao_foreign'],
            ['produto_historico', 'produto_historico_id_cliente_foreign'],
            ['produto_historico', 'produto_historico_id_usuario_foreign'],
            ['produto_acessorios', 'produto_acessorios_id_produto_foreign'],
            ['produto_acessorios', 'produto_acessorios_id_acessorio_foreign'],
            ['patrimonio_historico', 'patrimonio_historico_id_empresa_foreign'],
            ['patrimonio_historico', 'patrimonio_historico_id_patrimonio_foreign'],
            ['patrimonio_historico', 'patrimonio_historico_id_produto_foreign'],
            ['patrimonio_historico', 'patrimonio_historico_id_locacao_foreign'],
            ['patrimonio_historico', 'patrimonio_historico_id_cliente_foreign'],
            ['patrimonio_historico', 'patrimonio_historico_id_usuario_foreign'],
            ['movimentacoes_estoque', 'movimentacoes_estoque_id_empresa_foreign'],
            ['movimentacoes_estoque', 'movimentacoes_estoque_id_produto_foreign'],
            ['movimentacoes_estoque', 'movimentacoes_estoque_id_usuario_foreign'],
            ['manutencoes', 'manutencoes_id_empresa_foreign'],
            ['manutencoes', 'manutencoes_id_produto_foreign'],
            ['manutencoes', 'manutencoes_id_patrimonio_foreign'],
            ['patrimonios', 'patrimonios_id_empresa_foreign'],
            ['patrimonios', 'patrimonios_id_produto_foreign'],
            ['acessorios', 'acessorios_id_empresa_foreign'],
            ['produtos', 'produtos_id_empresa_foreign'],

            // CLIENTE
            ['clientes', 'clientes_id_empresa_foreign'],
            ['clientes', 'clientes_id_filial_foreign'],

            // AUTH
            ['usuario_permissoes', 'usuario_permissoes_id_usuario_foreign'],
            ['usuario_permissoes', 'usuario_permissoes_id_modulo_foreign'],
            ['empresa', 'empresa_id_plano_foreign'],
            ['empresa', 'empresa_id_plano_teste_foreign'],
            ['empresa', 'empresa_id_empresa_matriz_foreign'],
        ];

        foreach ($foreignKeys as [$table, $fkName]) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $t) use ($fkName) {
                    try {
                        $t->dropForeign($fkName);
                    } catch (\Exception $e) {
                        // FK pode não existir
                    }
                });
            }
        }
    }
};
