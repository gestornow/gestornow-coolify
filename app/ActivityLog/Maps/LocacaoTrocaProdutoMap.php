<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\LocacaoTrocaProduto;
use Closure;

class LocacaoTrocaProdutoMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'LocacaoTrocaProduto';
    }

    public static function tags(): array
    {
        return ['locacao', 'produtos', 'troca', 'estoque'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof LocacaoTrocaProduto) {
                return 'Troca de produto da locacao';
            }

            $produtoAnterior = self::nomeProdutoAnterior($model);
            $produtoNovo = self::nomeProdutoNovo($model);
            $contrato = self::numeroContrato($model);

            return "Troca: {$produtoAnterior} -> {$produtoNovo} - Contrato #{$contrato}";
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
                'acao' => 'locacao_troca.registrada',
                'icone' => 'repeat',
                'cor' => 'roxo',
                'descricao' => static function (LocacaoTrocaProduto $troca): string {
                    $contrato = self::numeroContrato($troca);
                    $produtoAnterior = self::nomeProdutoAnterior($troca);
                    $produtoNovo = self::nomeProdutoNovo($troca);
                    $quantidade = max(1, (int) ($troca->quantidade ?? 1));
                    $motivo = self::motivoTroca($troca);
                    $estoqueMovimentado = self::simNao((bool) ($troca->estoque_movimentado ?? false));

                    return "Trocou produto na locacao #{$contrato} - {$produtoAnterior} (x{$quantidade}) -> {$produtoNovo} - Motivo: {$motivo} - Estoque movimentado: {$estoqueMovimentado}";
                },
                'tags' => ['troca_produto', 'estoque'],
            ],
            'updated' => [
                'acao' => 'locacao_troca.editada',
                'icone' => 'repeat',
                'cor' => 'amarelo',
                'descricao' => static function (LocacaoTrocaProduto $troca): string {
                    $contrato = self::numeroContrato($troca);
                    $produtoAnterior = self::nomeProdutoAnterior($troca);
                    $produtoNovo = self::nomeProdutoNovo($troca);

                    return "Editou troca de produto na locacao #{$contrato} - {$produtoAnterior} -> {$produtoNovo}";
                },
                'tags' => ['edicao', 'troca_produto'],
            ],
            'deleted' => [
                'acao' => 'locacao_troca.removida',
                'icone' => 'repeat',
                'cor' => 'vermelho',
                'descricao' => static function (LocacaoTrocaProduto $troca): string {
                    $contrato = self::numeroContrato($troca);
                    $produtoAnterior = self::nomeProdutoAnterior($troca);
                    $produtoNovo = self::nomeProdutoNovo($troca);

                    return "Removeu registro de troca na locacao #{$contrato} - {$produtoAnterior} -> {$produtoNovo}";
                },
                'tags' => ['exclusao', 'troca_produto'],
            ],
        ];
    }

    private static function numeroContrato(LocacaoTrocaProduto $troca): string
    {
        $numero = trim((string) ($troca->locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($troca->id_locacao ?? '-');
    }

    private static function nomeProdutoAnterior(LocacaoTrocaProduto $troca): string
    {
        $nome = trim((string) ($troca->produtoAnterior->nome ?? ''));

        return $nome !== '' ? $nome : 'Produto anterior nao identificado';
    }

    private static function nomeProdutoNovo(LocacaoTrocaProduto $troca): string
    {
        $nome = trim((string) ($troca->produtoNovo->nome ?? ''));

        return $nome !== '' ? $nome : 'Produto novo nao identificado';
    }

    private static function motivoTroca(LocacaoTrocaProduto $troca): string
    {
        $motivo = trim((string) ($troca->motivo ?? ''));

        return $motivo !== '' ? $motivo : 'Nao informado';
    }

    private static function simNao(bool $valor): string
    {
        return $valor ? 'Sim' : 'Nao';
    }
}
