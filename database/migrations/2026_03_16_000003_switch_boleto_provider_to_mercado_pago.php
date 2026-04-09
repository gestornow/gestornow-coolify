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

        $dadosMercadoPago = [
            'nome' => 'Mercado Pago',
            'codigo_banco' => 'mercado_pago',
            'ativo' => true,
            'requer_certificado' => false,
            'requer_chave' => false,
            'requer_client_id' => false,
            'requer_client_secret' => false,
            'requer_api_key' => true,
            'requer_token' => false,
            'requer_convenio' => false,
            'requer_carteira' => false,
            'instrucoes' => 'Integracao Mercado Pago (Orders API): informe o Access Token no campo API Key e gere boleto via payment_method.id=boleto.',
            'updated_at' => $agora,
        ];

        $registroExistente = DB::table('bancos_boleto')
            ->where('codigo_banco', 'mercado_pago')
            ->orWhereRaw('LOWER(nome) = ?', ['mercado pago'])
            ->first();

        if ($registroExistente) {
            DB::table('bancos_boleto')
                ->where('id_banco_boleto', $registroExistente->id_banco_boleto)
                ->update($dadosMercadoPago);
        } else {
            DB::table('bancos_boleto')->insert(array_merge($dadosMercadoPago, [
                'created_at' => $agora,
            ]));
        }

        // Desativa Cora para nao aparecer na lista de integracoes ativas.
        DB::table('bancos_boleto')
            ->where('codigo_banco', 'cora')
            ->update([
                'ativo' => false,
                'updated_at' => $agora,
            ]);
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
            ->where('codigo_banco', 'mercado_pago')
            ->delete();

        DB::table('bancos_boleto')
            ->where('codigo_banco', 'cora')
            ->update([
                'ativo' => true,
                'updated_at' => now(),
            ]);
    }
};
