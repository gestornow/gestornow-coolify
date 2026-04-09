<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('bancos_boleto')) {
            return;
        }

        $agora = now();

        $dadosPagHiper = [
            'nome' => 'PagHiper',
            'codigo_banco' => 'paghiper',
            'ativo' => true,
            'requer_certificado' => false,
            'requer_chave' => false,
            'requer_client_id' => false,
            'requer_client_secret' => false,
            'requer_api_key' => true,
            'requer_token' => true,
            'requer_convenio' => false,
            'requer_carteira' => false,
            'instrucoes' => 'Integracao PagHiper: informe ApiKey e Token. Emissao via /transaction/create com valor minimo de R$ 3,00 por boleto.',
            'updated_at' => $agora,
        ];

        $registroExistente = DB::table('bancos_boleto')
            ->where('codigo_banco', 'paghiper')
            ->orWhereRaw('LOWER(nome) = ?', ['paghiper'])
            ->first();

        if ($registroExistente) {
            DB::table('bancos_boleto')
                ->where('id_banco_boleto', $registroExistente->id_banco_boleto)
                ->update($dadosPagHiper);

            return;
        }

        DB::table('bancos_boleto')->insert(array_merge($dadosPagHiper, [
            'created_at' => $agora,
        ]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('bancos_boleto')) {
            return;
        }

        DB::table('bancos_boleto')
            ->where('codigo_banco', 'paghiper')
            ->delete();
    }
};
