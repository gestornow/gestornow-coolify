<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grupos_permissoes', function (Blueprint $table) {
            $table->bigIncrements('id_grupo');
            $table->unsignedBigInteger('id_empresa');
            $table->string('nome', 100);
            $table->string('descricao', 255)->nullable();
            $table->timestamps();

            $table->index('id_empresa');
            $table->unique(['id_empresa', 'nome']);
        });

        Schema::create('grupos_permissoes_itens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_grupo');
            $table->string('chave', 100);

            $table->index('id_grupo');
            $table->unique(['id_grupo', 'chave']);
        });

        Schema::create('usuario_grupo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_usuario');
            $table->unsignedBigInteger('id_empresa');
            $table->unsignedBigInteger('id_grupo');
            $table->timestamps();

            $table->unique(['id_usuario', 'id_empresa']);
            $table->index('id_grupo');
        });

        Schema::create('permissoes_chaves', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('chave', 100)->unique();
            $table->string('modulo', 100);
            $table->string('label', 150);

            $table->index('modulo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_grupo');
        Schema::dropIfExists('grupos_permissoes_itens');
        Schema::dropIfExists('grupos_permissoes');
        Schema::dropIfExists('permissoes_chaves');
    }
};
