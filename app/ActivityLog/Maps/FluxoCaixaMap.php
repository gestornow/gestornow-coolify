<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Models\FluxoCaixa;
use Closure;

class FluxoCaixaMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'fluxo_caixa';
    }

    public static function tags(): array
    {
        return ['fluxo_caixa', 'financeiro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof FluxoCaixa) {
                return 'Lançamento de fluxo de caixa';
            }

            $tipo = ($model->tipo === 'entrada') ? 'Entrada' : 'Saída';
            $valor = number_format((float) ($model->valor ?? 0), 2, ',', '.');

            return sprintf(
                'Lançamento #%d — %s — R$ %s',
                (int) $model->id_fluxo,
                $tipo,
                $valor
            );
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof FluxoCaixa) {
                return null;
            }

            return isset($model->valor) ? (float) $model->valor : null;
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
                'acao' => 'fluxo_caixa.criado',
                'icone' => 'plus-circle',
                'cor' => 'verde',
                'descricao' => static function (FluxoCaixa $lancamento): string {
                    $tipo = ($lancamento->tipo === 'entrada') ? 'entrada' : 'saída';
                    $valor = number_format((float) ($lancamento->valor ?? 0), 2, ',', '.');

                    return "Criou lançamento de {$tipo} no fluxo de caixa — R$ {$valor}";
                },
                'tags' => ['cadastro'],
            ],
            'updated' => [
                'acao' => 'fluxo_caixa.editado',
                'icone' => 'edit',
                'cor' => 'amarelo',
                'descricao' => static function (FluxoCaixa $lancamento): string {
                    return sprintf(
                        'Editou o lançamento #%d do fluxo de caixa',
                        (int) $lancamento->id_fluxo
                    );
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'fluxo_caixa.excluido',
                'icone' => 'trash',
                'cor' => 'vermelho',
                'descricao' => static function (FluxoCaixa $lancamento): string {
                    $tipo = ($lancamento->tipo === 'entrada') ? 'entrada' : 'saída';
                    $valor = number_format((float) ($lancamento->valor ?? 0), 2, ',', '.');

                    return "Excluiu lançamento de {$tipo} do fluxo de caixa — R$ {$valor}";
                },
                'tags' => ['exclusao'],
            ],
            'lancamento_entrada' => [
                'acao' => 'fluxo_caixa.entrada',
                'icone' => 'arrow-down-circle',
                'cor' => 'verde',
                'descricao' => static function (FluxoCaixa $lancamento): string {
                    $valor = number_format((float) ($lancamento->valor ?? 0), 2, ',', '.');
                    $categoria = (string) ($lancamento->categoria->nome ?? 'Sem categoria');
                    $conta = (string) ($lancamento->banco->nome_banco ?? 'Sem conta/banco');

                    return "Lançamento de entrada — R$ {$valor} — Categoria: {$categoria} — Destino: {$conta}";
                },
                'tags' => ['entrada', 'lancamento_manual'],
            ],
            'lancamento_saida' => [
                'acao' => 'fluxo_caixa.saida',
                'icone' => 'arrow-up-circle',
                'cor' => 'vermelho',
                'descricao' => static function (FluxoCaixa $lancamento): string {
                    $valor = number_format((float) ($lancamento->valor ?? 0), 2, ',', '.');
                    $categoria = (string) ($lancamento->categoria->nome ?? 'Sem categoria');
                    $conta = (string) ($lancamento->banco->nome_banco ?? 'Sem conta/banco');

                    return "Lançamento de saída — R$ {$valor} — Categoria: {$categoria} — Origem: {$conta}";
                },
                'tags' => ['saida', 'lancamento_manual'],
            ],
            'estorno' => [
                'acao' => 'fluxo_caixa.estorno',
                'icone' => 'rotate-ccw',
                'cor' => 'laranja',
                'descricao' => static function (FluxoCaixa $lancamento): string {
                    $valor = number_format((float) ($lancamento->valor ?? 0), 2, ',', '.');

                    return sprintf(
                        'Estornou lançamento #%d do fluxo de caixa — R$ %s',
                        (int) $lancamento->id_fluxo,
                        $valor
                    );
                },
                'tags' => ['estorno'],
            ],
        ];
    }
}
