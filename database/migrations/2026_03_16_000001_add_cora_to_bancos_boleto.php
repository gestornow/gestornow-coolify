<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bancos_boleto')) {
            return;
        }

        $agora = now();
        $registroExistente = DB::table('bancos_boleto')
            ->where('codigo_banco', 'cora')
            ->first();

        $dados = [
            'nome' => 'Cora',
            'codigo_banco' => 'cora',
            'ativo' => true,
            'requer_certificado' => false,
            'requer_chave' => false,
            'requer_client_id' => true,
            'requer_client_secret' => true,
            'requer_api_key' => false,
            'requer_token' => false,
            'requer_convenio' => false,
            'requer_carteira' => false,
            'instrucoes' => 'Integração Cora (Parceria): informe Client ID e Client Secret e conclua a autorização da conta (Authorization Code) para emitir boletos.',
        ];

        if ($registroExistente) {
            DB::table('bancos_boleto')
                ->where('id_banco_boleto', $registroExistente->id_banco_boleto)
                ->update(array_merge($dados, [
                    'updated_at' => $agora,
                ]));

            return;
        }

        DB::table('bancos_boleto')->insert(array_merge($dados, [
            'created_at' => $agora,
            'updated_at' => $agora,
        ]));
    }

    public function down(): void
    {
        if (!Schema::hasTable('bancos_boleto')) {
            return;
        }

        DB::table('bancos_boleto')
            ->where('codigo_banco', 'cora')
            ->delete();
    }
};
