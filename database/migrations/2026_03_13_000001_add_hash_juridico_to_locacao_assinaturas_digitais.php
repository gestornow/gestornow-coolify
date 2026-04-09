<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locacao_assinaturas_digitais', function (Blueprint $table) {
            // Campos para validade jurídica
            $table->string('hash_documento')->nullable()->after('user_agent');
            $table->longText('corpo_contrato_assinado')->nullable()->after('hash_documento');
            $table->string('assinado_por_nome')->nullable()->after('corpo_contrato_assinado');
            $table->string('assinado_por_documento')->nullable()->after('assinado_por_nome');
        });
    }

    public function down(): void
    {
        Schema::table('locacao_assinaturas_digitais', function (Blueprint $table) {
            $table->dropColumn(['hash_documento', 'corpo_contrato_assinado', 'assinado_por_nome', 'assinado_por_documento']);
        });
    }
};
