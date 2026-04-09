<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\LocacaoProduto;
use Closure;

class LocacaoProdutosMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'LocacaoProduto';
    }

    public static function tags(): array
    {
        return ['locacao', 'produtos', 'estoque'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof LocacaoProduto) {
                return 'Item da locacao';
            }

            $produto = self::nomeProduto($model);
            $quantidade = max(1, (int) ($model->quantidade ?? 1));
            $contrato = self::numeroContrato($model);

            return "Produto {$produto} (x{$quantidade}) - Contrato #{$contrato}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof LocacaoProduto) {
                return null;
            }

            $precoUnitario = (float) ($model->preco_unitario ?? 0);
            $quantidade = max(1, (int) ($model->quantidade ?? 1));

            return round($precoUnitario * $quantidade, 2);
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
                'acao' => 'locacao_produto.adicionado',
                'icone' => 'package-plus',
                'cor' => 'verde',
                'descricao' => static function (LocacaoProduto $item): string {
                    $quantidade = max(1, (int) ($item->quantidade ?? 1));
                    $produto = self::nomeProduto($item);
                    $contrato = self::numeroContrato($item);
                    $precoUnitario = self::moeda($item->preco_unitario ?? 0);
                    $total = self::moeda(self::valorItem($item));

                    return "Adicionou {$quantidade}x {$produto} na locacao #{$contrato} - {$precoUnitario}/un - Total: {$total}";
                },
                'tags' => ['item_adicionado'],
            ],
            'updated' => [
                'acao' => 'locacao_produto.editado',
                'icone' => 'package',
                'cor' => 'amarelo',
                'descricao' => static function (LocacaoProduto $item): string {
                    $produto = self::nomeProduto($item);
                    $contrato = self::numeroContrato($item);

                    return "Editou item {$produto} na locacao #{$contrato}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'locacao_produto.removido',
                'icone' => 'package-minus',
                'cor' => 'vermelho',
                'descricao' => static function (LocacaoProduto $item): string {
                    $quantidade = max(1, (int) ($item->quantidade ?? 1));
                    $produto = self::nomeProduto($item);
                    $contrato = self::numeroContrato($item);
                    $total = self::moeda(self::valorItem($item));

                    return "Removeu {$quantidade}x {$produto} da locacao #{$contrato} - {$total}";
                },
                'tags' => ['item_removido'],
            ],
        ];
    }

    private static function nomeProduto(LocacaoProduto $item): string
    {
        $nome = trim((string) ($item->produto->nome ?? ''));

        return $nome !== '' ? $nome : 'Produto nao identificado';
    }

    private static function numeroContrato(LocacaoProduto $item): string
    {
        $numero = trim((string) ($item->locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($item->id_locacao ?? '-');
    }

    private static function valorItem(LocacaoProduto $item): float
    {
        if (isset($item->preco_total) && (float) $item->preco_total > 0) {
            return (float) $item->preco_total;
        }

        $precoUnitario = (float) ($item->preco_unitario ?? 0);
        $quantidade = max(1, (int) ($item->quantidade ?? 1));

        return round($precoUnitario * $quantidade, 2);
    }

    private static function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
