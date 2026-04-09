<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Produto\Models\Patrimonio;
use Closure;

class PatrimoniosMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'Patrimonio';
    }

    public static function tags(): array
    {
        return ['patrimonios', 'estoque'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Patrimonio) {
                return 'Patrimonio';
            }

            $id = (int) ($model->id_patrimonio ?? 0);
            $serial = trim((string) ($model->numero_serie ?? ''));
            $nomeProduto = self::nomeProduto($model);

            if ($serial === '') {
                return "Patrimonio #{$id} (sem numero de serie) - {$nomeProduto}";
            }

            return "Patrimonio #{$id} (S/N: {$serial}) - {$nomeProduto}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof Patrimonio) {
                return null;
            }

            return isset($model->valor_aquisicao) ? (float) $model->valor_aquisicao : null;
        };
    }

    public static function camposSensiveis(): array
    {
        return [];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'patrimonio.criado',
                'icone' => 'box',
                'cor' => 'verde',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';
                    $nomeProduto = self::nomeProduto($patrimonio);

                    return "Registrou patrimonio #{$id} (S/N: {$serialTexto}) do produto {$nomeProduto}";
                },
                'tags' => ['novo_cadastro'],
            ],
            'updated' => [
                'acao' => 'patrimonio.editado',
                'icone' => 'box',
                'cor' => 'amarelo',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';
                    $nomeProduto = self::nomeProduto($patrimonio);

                    return "Editou patrimonio #{$id} (S/N: {$serialTexto}) do produto {$nomeProduto}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'patrimonio.excluido',
                'icone' => 'box',
                'cor' => 'vermelho',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';
                    $nomeProduto = self::nomeProduto($patrimonio);
                    $valor = self::formatarMoeda($patrimonio->valor_aquisicao ?? 0);

                    return "Excluiu patrimonio #{$id} (S/N: {$serialTexto}) do produto {$nomeProduto} - valor de aquisicao: {$valor}";
                },
                'tags' => ['exclusao'],
            ],
            'ativacao' => [
                'acao' => 'patrimonio.ativado',
                'icone' => 'check-circle',
                'cor' => 'verde',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';
                    $nomeProduto = self::nomeProduto($patrimonio);

                    return "Ativou o patrimonio #{$id} (S/N: {$serialTexto}) do produto {$nomeProduto}";
                },
                'tags' => ['status'],
            ],
            'inativacao' => [
                'acao' => 'patrimonio.inativado',
                'icone' => 'minus-circle',
                'cor' => 'cinza',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';
                    $nomeProduto = self::nomeProduto($patrimonio);

                    return "Inativou o patrimonio #{$id} (S/N: {$serialTexto}) do produto {$nomeProduto}";
                },
                'tags' => ['status'],
            ],
            'descarte' => [
                'acao' => 'patrimonio.descartado',
                'icone' => 'trash-2',
                'cor' => 'vermelho-escuro',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';
                    $nomeProduto = self::nomeProduto($patrimonio);
                    $valor = self::formatarMoeda($patrimonio->valor_aquisicao ?? 0);

                    return "Descartou o patrimonio #{$id} (S/N: {$serialTexto}) do produto {$nomeProduto} - valor de aquisicao: {$valor}";
                },
                'tags' => ['status', 'descarte'],
            ],
            'disponibilizado' => [
                'acao' => 'patrimonio.disponibilizado',
                'icone' => 'unlock',
                'cor' => 'azul',
                'descricao' => static function (Patrimonio $patrimonio): string {
                    $id = (int) ($patrimonio->id_patrimonio ?? 0);
                    $serial = trim((string) ($patrimonio->numero_serie ?? ''));
                    $serialTexto = $serial !== '' ? $serial : 'sem numero de serie';

                    return "Patrimonio #{$id} (S/N: {$serialTexto}) marcado como disponivel";
                },
                'tags' => ['locacao', 'disponivel'],
            ],
        ];
    }

    private static function nomeProduto(Patrimonio $patrimonio): string
    {
        $produto = $patrimonio->produto;
        $nome = trim((string) ($produto->nome ?? ''));

        return $nome !== '' ? $nome : 'Produto nao identificado';
    }

    private static function formatarMoeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
