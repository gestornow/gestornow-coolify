<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\ProdutoTerceirosLocacao;
use Closure;

class LocacaoProdutoTerceiroMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'ProdutoTerceirosLocacao';
    }

    public static function tags(): array
    {
        return ['locacao', 'terceiros', 'fornecedores'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof ProdutoTerceirosLocacao) {
                return 'Produto terceiro da locacao';
            }

            $descricao = self::descricaoProduto($model);
            $fornecedor = self::nomeFornecedor($model);
            $contrato = self::numeroContrato($model);

            return "Terceiro: {$descricao} ({$fornecedor}) - Contrato #{$contrato}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof ProdutoTerceirosLocacao) {
                return null;
            }

            return isset($model->valor_total) ? (float) $model->valor_total : null;
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
                'acao' => 'locacao_terceiro.adicionado',
                'icone' => 'truck',
                'cor' => 'roxo',
                'descricao' => static function (ProdutoTerceirosLocacao $item): string {
                    $descricao = self::descricaoProduto($item);
                    $codigo = self::codigoProduto($item);
                    $fornecedor = self::nomeFornecedor($item);
                    $contrato = self::numeroContrato($item);
                    $quantidade = max(1, (int) ($item->quantidade ?? 1));
                    $valorUnitario = self::moeda($item->preco_unitario ?? 0);
                    $valorTotal = self::moeda($item->valor_total ?? 0);

                    return "Adicionou produto de terceiro '{$descricao}' (Cod: {$codigo}) do fornecedor {$fornecedor} na locacao #{$contrato} - {$quantidade}x {$valorUnitario} - Total: {$valorTotal}";
                },
                'tags' => ['terceiro_adicionado'],
            ],
            'updated' => [
                'acao' => 'locacao_terceiro.editado',
                'icone' => 'truck',
                'cor' => 'amarelo',
                'descricao' => static function (ProdutoTerceirosLocacao $item): string {
                    $descricao = self::descricaoProduto($item);
                    $contrato = self::numeroContrato($item);

                    $descricaoBase = "Editou produto de terceiro '{$descricao}' na locacao #{$contrato}";

                    $mudancas = $item->getChanges();
                    $temStatusNovo = array_key_exists('status', $mudancas);

                    if (!$temStatusNovo) {
                        return $descricaoBase;
                    }

                    $statusNovo = self::statusLegivel($mudancas['status'] ?? null);
                    $statusAnterior = self::statusLegivel($item->getOriginal('status'));

                    return $descricaoBase . " - status: '{$statusAnterior}' -> '{$statusNovo}'";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'locacao_terceiro.removido',
                'icone' => 'truck',
                'cor' => 'vermelho',
                'descricao' => static function (ProdutoTerceirosLocacao $item): string {
                    $descricao = self::descricaoProduto($item);
                    $contrato = self::numeroContrato($item);
                    $valorTotal = self::moeda($item->valor_total ?? 0);

                    return "Removeu produto de terceiro '{$descricao}' da locacao #{$contrato} - {$valorTotal}";
                },
                'tags' => ['terceiro_removido'],
            ],
            'status_alterado' => [
                'acao' => 'locacao_terceiro.status_alterado',
                'icone' => 'truck',
                'cor' => 'azul',
                'descricao' => static function (ProdutoTerceirosLocacao $item): string {
                    $descricao = self::descricaoProduto($item);
                    $contrato = self::numeroContrato($item);

                    $statusAnterior = self::statusLegivel($item->getOriginal('status'));
                    $statusNovo = self::statusLegivel($item->status ?? null);

                    return "Alterou status do produto terceiro '{$descricao}' de '{$statusAnterior}' para '{$statusNovo}' na locacao #{$contrato}";
                },
                'tags' => ['status', 'terceiro'],
            ],
        ];
    }

    private static function descricaoProduto(ProdutoTerceirosLocacao $item): string
    {
        $descricao = trim((string) ($item->nome_produto_manual ?? ''));

        if ($descricao !== '') {
            return $descricao;
        }

        $descricaoProdutoTerceiro = trim((string) ($item->produtoTerceiro->nome ?? ''));
        if ($descricaoProdutoTerceiro !== '') {
            return $descricaoProdutoTerceiro;
        }

        $descricaoManual = trim((string) ($item->descricao_manual ?? ''));
        if ($descricaoManual !== '') {
            return $descricaoManual;
        }

        return 'Produto terceiro nao identificado';
    }

    private static function codigoProduto(ProdutoTerceirosLocacao $item): string
    {
        $codigo = trim((string) ($item->produtoTerceiro->codigo ?? ''));

        return $codigo !== '' ? $codigo : '-';
    }

    private static function nomeFornecedor(ProdutoTerceirosLocacao $item): string
    {
        $nome = trim((string) ($item->fornecedor->nome_fantasia ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $razao = trim((string) ($item->fornecedor->razao_social ?? ''));
        if ($razao !== '') {
            return $razao;
        }

        return 'Fornecedor nao identificado';
    }

    private static function numeroContrato(ProdutoTerceirosLocacao $item): string
    {
        $numero = trim((string) ($item->locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($item->id_locacao ?? '-');
    }

    private static function statusLegivel($status): string
    {
        $mapa = [
            'pendente' => 'Pendente',
            'solicitado' => 'Solicitado',
            'confirmado' => 'Confirmado',
            'entregue' => 'Entregue',
        ];

        $chave = trim(strtolower((string) $status));

        if ($chave === '') {
            return 'Nao informado';
        }

        return $mapa[$chave] ?? ucfirst($chave);
    }

    private static function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
