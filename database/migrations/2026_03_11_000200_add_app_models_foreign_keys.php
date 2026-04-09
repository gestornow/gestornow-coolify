<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->foreignKeys() as $fk) {
            $this->addForeignKeyIfPossible($fk['table'], $fk['column'], $fk['ref_table'], $fk['ref_column']);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->foreignKeys()) as $fk) {
            $this->dropForeignIfExists($fk['table'], $fk['column']);
        }
    }

    private function foreignKeys(): array
    {
        return [
            ['table' => 'assinaturas_planos', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'assinaturas_planos', 'column' => 'id_plano', 'ref_table' => 'planos', 'ref_column' => 'id_plano'],
            ['table' => 'assinaturas_planos', 'column' => 'id_plano_contratado', 'ref_table' => 'planos_contratados', 'ref_column' => 'id'],
            ['table' => 'assinaturas_planos_pagamentos', 'column' => 'id_assinatura_plano', 'ref_table' => 'assinaturas_planos', 'ref_column' => 'id'],
            ['table' => 'assinaturas_planos_pagamentos', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'assinaturas_planos_pagamentos', 'column' => 'id_plano', 'ref_table' => 'planos', 'ref_column' => 'id_plano'],
            ['table' => 'assinaturas_planos_pagamentos', 'column' => 'id_plano_contratado', 'ref_table' => 'planos_contratados', 'ref_column' => 'id'],
            ['table' => 'banco_boleto_config', 'column' => 'id_banco_boleto', 'ref_table' => 'bancos_boleto', 'ref_column' => 'id_banco_boleto'],
            ['table' => 'banco_boleto_config', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'banco_boleto_config', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'bancos', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'boletos', 'column' => 'id_banco_boleto', 'ref_table' => 'bancos_boleto', 'ref_column' => 'id_banco_boleto'],
            ['table' => 'boletos', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'boletos', 'column' => 'id_conta_receber', 'ref_table' => 'contas_a_receber', 'ref_column' => 'id_contas'],
            ['table' => 'boletos', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'boletos_historico', 'column' => 'id_boleto', 'ref_table' => 'boletos', 'ref_column' => 'id_boleto'],
            ['table' => 'boletos_historico', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'categoria_contas', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'client_contracts', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'client_contracts', 'column' => 'id_plano', 'ref_table' => 'planos', 'ref_column' => 'id_plano'],
            ['table' => 'client_contracts', 'column' => 'id_plano_contratado', 'ref_table' => 'planos_contratados', 'ref_column' => 'id'],
            ['table' => 'contas_a_pagar', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'contas_a_pagar', 'column' => 'id_categoria_contas', 'ref_table' => 'categoria_contas', 'ref_column' => 'id_categoria_contas'],
            ['table' => 'contas_a_pagar', 'column' => 'id_clientes', 'ref_table' => 'clientes', 'ref_column' => 'id_clientes'],
            ['table' => 'contas_a_pagar', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'contas_a_pagar', 'column' => 'id_forma_pagamento', 'ref_table' => 'forma_pagamento', 'ref_column' => 'id_forma_pagamento'],
            ['table' => 'contas_a_pagar', 'column' => 'id_fornecedores', 'ref_table' => 'fornecedores', 'ref_column' => 'id_fornecedores'],
            ['table' => 'contas_a_pagar', 'column' => 'id_locacao', 'ref_table' => 'locacoes', 'ref_column' => 'id_locacao'],
            ['table' => 'contas_a_pagar', 'column' => 'id_usuario', 'ref_table' => 'usuarios', 'ref_column' => 'id_usuario'],
            ['table' => 'contas_a_receber', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'contas_a_receber', 'column' => 'id_categoria_contas', 'ref_table' => 'categoria_contas', 'ref_column' => 'id_categoria_contas'],
            ['table' => 'contas_a_receber', 'column' => 'id_clientes', 'ref_table' => 'clientes', 'ref_column' => 'id_clientes'],
            ['table' => 'contas_a_receber', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'contas_a_receber', 'column' => 'id_forma_pagamento', 'ref_table' => 'forma_pagamento', 'ref_column' => 'id_forma_pagamento'],
            ['table' => 'contas_a_receber', 'column' => 'id_fornecedores', 'ref_table' => 'fornecedores', 'ref_column' => 'id_fornecedores'],
            ['table' => 'contas_a_receber', 'column' => 'id_locacao', 'ref_table' => 'locacoes', 'ref_column' => 'id_locacao'],
            ['table' => 'contas_a_receber', 'column' => 'id_usuario', 'ref_table' => 'usuarios', 'ref_column' => 'id_usuario'],
            ['table' => 'empresa_contratos_software', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'faturamento_locacoes', 'column' => 'id_cliente', 'ref_table' => 'clientes', 'ref_column' => 'id_clientes'],
            ['table' => 'faturamento_locacoes', 'column' => 'id_conta_receber', 'ref_table' => 'contas_a_receber', 'ref_column' => 'id_contas'],
            ['table' => 'faturamento_locacoes', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'faturamento_locacoes', 'column' => 'id_locacao', 'ref_table' => 'locacoes', 'ref_column' => 'id_locacao'],
            ['table' => 'faturamento_locacoes', 'column' => 'id_usuario', 'ref_table' => 'usuarios', 'ref_column' => 'id_usuario'],
            ['table' => 'fluxo_caixa', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'fluxo_caixa', 'column' => 'id_categoria_fluxo', 'ref_table' => 'categoria_contas', 'ref_column' => 'id_categoria_contas'],
            ['table' => 'fluxo_caixa', 'column' => 'id_conta_pagar', 'ref_table' => 'contas_a_pagar', 'ref_column' => 'id_contas'],
            ['table' => 'fluxo_caixa', 'column' => 'id_conta_receber', 'ref_table' => 'contas_a_receber', 'ref_column' => 'id_contas'],
            ['table' => 'fluxo_caixa', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'fluxo_caixa', 'column' => 'id_forma_pagamento', 'ref_table' => 'forma_pagamento', 'ref_column' => 'id_forma_pagamento'],
            ['table' => 'forma_pagamento', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'fornecedores', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'modulos', 'column' => 'id_modulo_pai', 'ref_table' => 'modulos', 'ref_column' => 'id_modulo'],
            ['table' => 'pagamentos_contas_pagar', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'pagamentos_contas_pagar', 'column' => 'id_conta_pagar', 'ref_table' => 'contas_a_pagar', 'ref_column' => 'id_contas'],
            ['table' => 'pagamentos_contas_pagar', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'pagamentos_contas_pagar', 'column' => 'id_fluxo_caixa', 'ref_table' => 'fluxo_caixa', 'ref_column' => 'id_fluxo'],
            ['table' => 'pagamentos_contas_pagar', 'column' => 'id_forma_pagamento', 'ref_table' => 'forma_pagamento', 'ref_column' => 'id_forma_pagamento'],
            ['table' => 'pagamentos_contas_pagar', 'column' => 'id_usuario', 'ref_table' => 'usuarios', 'ref_column' => 'id_usuario'],
            ['table' => 'pagamentos_contas_receber', 'column' => 'id_bancos', 'ref_table' => 'bancos', 'ref_column' => 'id_bancos'],
            ['table' => 'pagamentos_contas_receber', 'column' => 'id_conta_receber', 'ref_table' => 'contas_a_receber', 'ref_column' => 'id_contas'],
            ['table' => 'pagamentos_contas_receber', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'pagamentos_contas_receber', 'column' => 'id_fluxo_caixa', 'ref_table' => 'fluxo_caixa', 'ref_column' => 'id_fluxo'],
            ['table' => 'pagamentos_contas_receber', 'column' => 'id_forma_pagamento', 'ref_table' => 'forma_pagamento', 'ref_column' => 'id_forma_pagamento'],
            ['table' => 'pagamentos_contas_receber', 'column' => 'id_usuario', 'ref_table' => 'usuarios', 'ref_column' => 'id_usuario'],
            ['table' => 'planos_contratados', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'planos_contratados_modulos', 'column' => 'id_modulo', 'ref_table' => 'modulos', 'ref_column' => 'id_modulo'],
            ['table' => 'planos_contratados_modulos', 'column' => 'id_plano_contratado', 'ref_table' => 'planos_contratados', 'ref_column' => 'id'],
            ['table' => 'planos_modulos', 'column' => 'id_modulo', 'ref_table' => 'modulos', 'ref_column' => 'id_modulo'],
            ['table' => 'planos_modulos', 'column' => 'id_plano', 'ref_table' => 'planos', 'ref_column' => 'id_plano'],
            ['table' => 'planos_promocoes', 'column' => 'id_plano', 'ref_table' => 'planos', 'ref_column' => 'id_plano'],
            ['table' => 'registro_atividades', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
            ['table' => 'registro_atividades', 'column' => 'id_usuario', 'ref_table' => 'usuarios', 'ref_column' => 'id_usuario'],
            ['table' => 'usuarios', 'column' => 'id_empresa', 'ref_table' => 'empresas', 'ref_column' => 'id_empresa'],
        ];
    }

    private function addForeignKeyIfPossible(string $table, string $column, string $referenceTable, string $referenceColumn): void
    {
        if (!Schema::hasTable($table) || !Schema::hasTable($referenceTable)) {
            return;
        }

        if (!Schema::hasColumn($table, $column) || !Schema::hasColumn($referenceTable, $referenceColumn)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referenceTable, $referenceColumn) {
            $blueprint->foreign($column)->references($referenceColumn)->on($referenceTable)->nullOnDelete();
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable $e) {
            // Ignora quando a FK nao existe.
        }
    }
};
