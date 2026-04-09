<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locacao')) {
            return;
        }

        $adicionarFreteEntrega = !Schema::hasColumn('locacao', 'valor_frete_entrega');
        $adicionarFreteRetirada = !Schema::hasColumn('locacao', 'valor_frete_retirada');
        $adicionarObservacoesOrcamento = !Schema::hasColumn('locacao', 'observacoes_orcamento');

        if (!$adicionarFreteEntrega && !$adicionarFreteRetirada && !$adicionarObservacoesOrcamento) {
            return;
        }

        Schema::table('locacao', function (Blueprint $table) use (
            $adicionarFreteEntrega,
            $adicionarFreteRetirada,
            $adicionarObservacoesOrcamento
        ) {
            if ($adicionarFreteEntrega) {
                $table->decimal('valor_frete_entrega', 15, 2)->default(0);
            }

            if ($adicionarFreteRetirada) {
                $table->decimal('valor_frete_retirada', 15, 2)->default(0);
            }

            if ($adicionarObservacoesOrcamento) {
                $table->text('observacoes_orcamento')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('locacao')) {
            return;
        }

        Schema::table('locacao', function (Blueprint $table) {
            if (Schema::hasColumn('locacao', 'observacoes_orcamento')) {
                $table->dropColumn('observacoes_orcamento');
            }

            if (Schema::hasColumn('locacao', 'valor_frete_retirada')) {
                $table->dropColumn('valor_frete_retirada');
            }

            if (Schema::hasColumn('locacao', 'valor_frete_entrega')) {
                $table->dropColumn('valor_frete_entrega');
            }
        });
    }
};
