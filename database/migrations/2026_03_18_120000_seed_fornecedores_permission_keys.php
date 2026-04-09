<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = [
            [
                'chave' => 'fornecedores.visualizar',
                'modulo' => 'Fornecedores',
                'label' => 'Visualizar fornecedores',
            ],
            [
                'chave' => 'fornecedores.criar',
                'modulo' => 'Fornecedores',
                'label' => 'Criar fornecedores',
            ],
            [
                'chave' => 'fornecedores.editar',
                'modulo' => 'Fornecedores',
                'label' => 'Editar fornecedores',
            ],
            [
                'chave' => 'fornecedores.excluir',
                'modulo' => 'Fornecedores',
                'label' => 'Excluir fornecedores',
            ],
        ];

        foreach ($rows as $row) {
            DB::table('permissoes_chaves')->updateOrInsert(
                ['chave' => $row['chave']],
                ['modulo' => $row['modulo'], 'label' => $row['label']]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissoes_chaves')
            ->whereIn('chave', [
                'fornecedores.visualizar',
                'fornecedores.criar',
                'fornecedores.editar',
                'fornecedores.excluir',
            ])
            ->delete();
    }
};
