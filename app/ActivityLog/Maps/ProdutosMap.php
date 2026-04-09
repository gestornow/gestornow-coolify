<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Produto\Models\Produto;
use Closure;

class ProdutosMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'produto';
    }

    public static function tags(): array
    {
        return ['produtos', 'estoque'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Produto) {
                return 'Produto';
            }

            $nome = trim((string) ($model->nome ?? 'Produto'));
            $codigo = trim((string) ($model->codigo ?? ''));

            if ($codigo === '') {
                return $nome;
            }

            return sprintf('%s (Cod: %s)', $nome, $codigo);
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof Produto) {
                return null;
            }

            $precoVenda = isset($model->preco_venda) ? (float) $model->preco_venda : 0.0;
            $precoBase = isset($model->preco) ? (float) $model->preco : null;

            if ($precoVenda > 0) {
                return $precoVenda;
            }

            return $precoBase;
        };
    }

    public static function camposSensiveis(): array
    {
        return [
            'foto_url',
            'foto_filename',
            'hex_color',
        ];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'produto.criado',
                'icone' => 'package-plus',
                'cor' => 'verde',
                'descricao' => static function (Produto $produto): string {
                    $nome = trim((string) ($produto->nome ?? 'Produto'));
                    $codigo = trim((string) ($produto->codigo ?? ''));

                    if ($codigo === '') {
                        return "Cadastrou o produto {$nome}";
                    }

                    return "Cadastrou o produto {$nome} (Cod: {$codigo})";
                },
                'tags' => ['novo_cadastro'],
            ],
            'updated' => [
                'acao' => 'produto.editado',
                'icone' => 'package',
                'cor' => 'amarelo',
                'descricao' => static function (Produto $produto): string {
                    $nome = trim((string) ($produto->nome ?? 'Produto'));
                    $mudancas = array_keys($produto->getChanges());

                    $camposPreco = [
                        'preco',
                        'preco_reposicao',
                        'preco_custo',
                        'preco_venda',
                        'preco_locacao',
                    ];

                    $precoAlterado = count(array_intersect($mudancas, $camposPreco)) > 0;

                    if ($precoAlterado) {
                        return "Editou o produto {$nome} - precos alterados";
                    }

                    return "Editou o produto {$nome}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'produto.excluido',
                'icone' => 'package-x',
                'cor' => 'vermelho',
                'descricao' => static function (Produto $produto): string {
                    $nome = trim((string) ($produto->nome ?? 'Produto'));
                    $codigo = trim((string) ($produto->codigo ?? ''));

                    if ($codigo === '') {
                        return "Excluiu o produto {$nome}";
                    }

                    return "Excluiu o produto {$nome} (Cod: {$codigo})";
                },
                'tags' => ['exclusao'],
            ],
            'entrada_estoque' => [
                'acao' => 'produto.entrada_estoque',
                'icone' => 'arrow-down-circle',
                'cor' => 'verde',
                'descricao' => static function (Produto $produto): string {
                    $nome = trim((string) ($produto->nome ?? 'Produto'));

                    $quantidade = isset($produto->audit_quantidade_movimentada)
                        ? (int) $produto->audit_quantidade_movimentada
                        : null;

                    $anterior = isset($produto->audit_estoque_anterior)
                        ? (int) $produto->audit_estoque_anterior
                        : null;

                    $posterior = isset($produto->audit_estoque_posterior)
                        ? (int) $produto->audit_estoque_posterior
                        : (int) ($produto->quantidade ?? 0);

                    if ($quantidade !== null && $anterior !== null) {
                        return "Entrada de {$quantidade} unidades no estoque de {$nome} - De: {$anterior} -> Para: {$posterior}";
                    }

                    return "Registrou entrada de estoque do produto {$nome}";
                },
                'tags' => ['estoque', 'entrada'],
            ],
            'saida_estoque' => [
                'acao' => 'produto.saida_estoque',
                'icone' => 'arrow-up-circle',
                'cor' => 'laranja',
                'descricao' => static function (Produto $produto): string {
                    $nome = trim((string) ($produto->nome ?? 'Produto'));

                    $quantidade = isset($produto->audit_quantidade_movimentada)
                        ? (int) $produto->audit_quantidade_movimentada
                        : null;

                    $anterior = isset($produto->audit_estoque_anterior)
                        ? (int) $produto->audit_estoque_anterior
                        : null;

                    $posterior = isset($produto->audit_estoque_posterior)
                        ? (int) $produto->audit_estoque_posterior
                        : (int) ($produto->quantidade ?? 0);

                    if ($quantidade !== null && $anterior !== null) {
                        return "Saida de {$quantidade} unidades do estoque de {$nome} - De: {$anterior} -> Para: {$posterior}";
                    }

                    return "Registrou saida de estoque do produto {$nome}";
                },
                'tags' => ['estoque', 'saida'],
            ],
        ];
    }
}
