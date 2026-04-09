<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Cliente\Models\Cliente;
use Closure;

class ClientesMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'cliente';
    }

    public static function tags(): array
    {
        return ['clientes', 'cadastro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Cliente) {
                return 'Cliente';
            }

            $nome = trim((string) ($model->nome ?? 'Cliente'));
            $documento = preg_replace('/\D+/', '', (string) ($model->cpf_cnpj ?? ''));
            $sufixo = $documento !== '' ? substr($documento, -4) : '----';

            return sprintf('%s (***%s)', $nome, $sufixo);
        };
    }

    public static function valor(): ?Closure
    {
        return null;
    }

    public static function camposSensiveis(): array
    {
        return [];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'cliente.criado',
                'icone' => 'user-plus',
                'cor' => 'verde',
                'descricao' => static function (Cliente $cliente): string {
                    $nome = trim((string) ($cliente->nome ?? 'Cliente'));
                    return "Cadastrou o cliente {$nome}";
                },
                'tags' => ['novo_cadastro'],
            ],
            'updated' => [
                'acao' => 'cliente.editado',
                'icone' => 'user-check',
                'cor' => 'amarelo',
                'descricao' => static function (Cliente $cliente): string {
                    $nome = trim((string) ($cliente->nome ?? 'Cliente'));
                    return "Editou o cadastro do cliente {$nome}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'cliente.excluido',
                'icone' => 'user-x',
                'cor' => 'vermelho',
                'descricao' => static function (Cliente $cliente): string {
                    $nome = trim((string) ($cliente->nome ?? 'Cliente'));
                    return "Excluiu o cliente {$nome}";
                },
                'tags' => ['exclusao'],
            ],
        ];
    }
}
