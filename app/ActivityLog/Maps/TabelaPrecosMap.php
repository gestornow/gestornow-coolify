<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Produto\Models\TabelaPreco;
use Closure;

class TabelaPrecosMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'TabelaPreco';
    }

    public static function tags(): array
    {
        return ['tabela_precos', 'produtos', 'financeiro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof TabelaPreco) {
                return 'Tabela de precos';
            }

            $nomeTabela = self::nomeTabela($model);
            $nomeProduto = self::nomeProduto($model);

            return "Tabela '{$nomeTabela}' - Produto: {$nomeProduto}";
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
                'acao' => 'tabela_precos.criada',
                'icone' => 'grid',
                'cor' => 'verde',
                'descricao' => static function (TabelaPreco $tabela): string {
                    $nomeTabela = self::nomeTabela($tabela);
                    $nomeProduto = self::nomeProduto($tabela);

                    return "Criou tabela de precos '{$nomeTabela}' para o produto {$nomeProduto}";
                },
                'tags' => ['novo_cadastro'],
            ],
            'updated' => [
                'acao' => 'tabela_precos.editada',
                'icone' => 'grid',
                'cor' => 'amarelo',
                'descricao' => static function (TabelaPreco $tabela): string {
                    $nomeTabela = self::nomeTabela($tabela);
                    $nomeProduto = self::nomeProduto($tabela);
                    $faixasAlteradas = self::contarFaixasAlteradas($tabela);

                    return "Editou tabela de precos '{$nomeTabela}' do produto {$nomeProduto} - {$faixasAlteradas} faixas de preco alteradas";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'tabela_precos.excluida',
                'icone' => 'grid',
                'cor' => 'vermelho',
                'descricao' => static function (TabelaPreco $tabela): string {
                    $nomeTabela = self::nomeTabela($tabela);
                    $nomeProduto = self::nomeProduto($tabela);

                    return "Excluiu tabela de precos '{$nomeTabela}' do produto {$nomeProduto}";
                },
                'tags' => ['exclusao'],
            ],
        ];
    }

    private static function nomeTabela(TabelaPreco $tabela): string
    {
        $nome = trim((string) ($tabela->nome ?? ''));

        return $nome !== '' ? $nome : 'Sem nome';
    }

    private static function nomeProduto(TabelaPreco $tabela): string
    {
        $produto = $tabela->produto;
        $nome = trim((string) ($produto->nome ?? ''));

        return $nome !== '' ? $nome : 'Produto nao identificado';
    }

    private static function contarFaixasAlteradas(TabelaPreco $tabela): int
    {
        $camposAlterados = array_keys($tabela->getChanges());
        $faixas = self::camposFaixas();

        return count(array_intersect($camposAlterados, $faixas));
    }

    private static function camposFaixas(): array
    {
        return [
            'd1', 'd2', 'd3', 'd4', 'd5', 'd6', 'd7', 'd8', 'd9', 'd10',
            'd11', 'd12', 'd13', 'd14', 'd15', 'd16', 'd17', 'd18', 'd19', 'd20',
            'd21', 'd22', 'd23', 'd24', 'd25', 'd26', 'd27', 'd28', 'd29', 'd30',
            'd60', 'd120', 'd360',
        ];
    }
}
