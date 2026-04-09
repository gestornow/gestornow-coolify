<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('locacao_modelos_contrato') || Schema::hasColumn('locacao_modelos_contrato', 'tipo_modelo')) {
            return;
        }

        Schema::table('locacao_modelos_contrato', function (Blueprint $table) {
            $table->string('tipo_modelo', 20)->default('contrato');
        });

        if (Schema::hasColumn('locacao_modelos_contrato', 'usa_medicao')) {
            DB::table('locacao_modelos_contrato')
                ->where('usa_medicao', true)
                ->update(['tipo_modelo' => 'medicao']);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('locacao_modelos_contrato') || !Schema::hasColumn('locacao_modelos_contrato', 'tipo_modelo')) {
            return;
        }

        Schema::table('locacao_modelos_contrato', function (Blueprint $table) {
            $table->dropColumn('tipo_modelo');
        });
    }
};
