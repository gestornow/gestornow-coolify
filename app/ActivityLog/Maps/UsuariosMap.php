<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Auth\Models\Usuario;
use Closure;

class UsuariosMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'usuario';
    }

    public static function tags(): array
    {
        return ['usuarios', 'acesso'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Usuario) {
                return 'Usuario';
            }

            $nome = trim((string) ($model->nome ?? 'Usuario'));
            $id = (int) ($model->id_usuario ?? 0);

            if ($id > 0) {
                return sprintf('%s (#%d)', $nome, $id);
            }

            return $nome;
        };
    }

    public static function valor(): ?Closure
    {
        return null;
    }

    public static function camposSensiveis(): array
    {
        return [
            'cpf',
            'rg',
            'telefone',
            'endereco',
            'cep',
            'bairro',
            'observacoes',
        ];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'usuario.criado',
                'icone' => 'user-plus',
                'cor' => 'verde',
                'descricao' => static function (Usuario $usuario): string {
                    $nome = trim((string) ($usuario->nome ?? 'Usuario'));
                    return "Cadastrou o usuario {$nome}";
                },
                'tags' => ['novo_cadastro'],
            ],
            'updated' => [
                'acao' => 'usuario.editado',
                'icone' => 'user-cog',
                'cor' => 'amarelo',
                'descricao' => static function (Usuario $usuario): string {
                    $nome = trim((string) ($usuario->nome ?? 'Usuario'));
                    return "Editou o cadastro do usuario {$nome}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'usuario.excluido',
                'icone' => 'user-x',
                'cor' => 'vermelho',
                'descricao' => static function (Usuario $usuario): string {
                    $nome = trim((string) ($usuario->nome ?? 'Usuario'));
                    return "Excluiu o usuario {$nome}";
                },
                'tags' => ['exclusao'],
            ],
        ];
    }
}