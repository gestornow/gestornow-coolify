<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planos_promocoes', function (Blueprint $table) {
            // Remover campos de regra por quantidade de clientes
            $table->dropColumn(['tipo_regra', 'quantidade_clientes_min', 'quantidade_clientes_max', 'prioridade']);
        });
    }

    public function down(): void
    {
        Schema::table('planos_promocoes', function (Blueprint $table) {
            $table->string('tipo_regra')->nullable()->after('nome');
            $table->integer('quantidade_clientes_min')->nullable()->after('data_fim');
            $table->integer('quantidade_clientes_max')->nullable()->after('quantidade_clientes_min');
            $table->integer('prioridade')->nullable()->after('desconto_adesao');
        });
    }
};
