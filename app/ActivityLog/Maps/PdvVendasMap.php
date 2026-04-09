<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Venda\Models\Venda;
use Closure;

class PdvVendasMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'pdv_venda';
    }

    public static function tags(): array
    {
        return ['pdv', 'vendas'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Venda) {
                return 'Venda PDV';
            }

            $numero = (int) ($model->numero_venda ?? 0);

            if ($numero > 0) {
                return sprintf('Venda PDV #%d', $numero);
            }

            $id = (int) ($model->id_venda ?? 0);

            if ($id > 0) {
                return sprintf('Venda PDV #%d', $id);
            }

            return 'Venda PDV';
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof Venda) {
                return null;
            }

            return isset($model->total) ? (float) $model->total : null;
        };
    }

    public static function camposSensiveis(): array
    {
        return [
            'observacoes',
        ];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'pdv.venda_criada',
                'icone' => 'receipt',
                'cor' => 'verde',
                'descricao' => static function (Venda $venda): string {
                    $numero = (int) ($venda->numero_venda ?? 0);
                    return "Criou venda PDV #{$numero}";
                },
                'tags' => ['novo_cadastro'],
            ],
            'updated' => [
                'acao' => 'pdv.venda_editada',
                'icone' => 'receipt-text',
                'cor' => 'amarelo',
                'descricao' => static function (Venda $venda): string {
                    $numero = (int) ($venda->numero_venda ?? 0);
                    return "Editou venda PDV #{$numero}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'pdv.venda_excluida',
                'icone' => 'trash-2',
                'cor' => 'vermelho',
                'descricao' => static function (Venda $venda): string {
                    $numero = (int) ($venda->numero_venda ?? 0);
                    return "Excluiu venda PDV #{$numero}";
                },
                'tags' => ['exclusao'],
            ],
            'finalizacao' => [
                'acao' => 'pdv.venda_finalizada',
                'icone' => 'badge-check',
                'cor' => 'verde-escuro',
                'descricao' => static function (Venda $venda): string {
                    $numero = (int) ($venda->numero_venda ?? 0);
                    return "Finalizou venda PDV #{$numero}";
                },
                'tags' => ['finalizacao'],
            ],
            'cancelamento' => [
                'acao' => 'pdv.venda_cancelada',
                'icone' => 'x-circle',
                'cor' => 'vermelho',
                'descricao' => static function (Venda $venda): string {
                    $numero = (int) ($venda->numero_venda ?? 0);
                    return "Cancelou venda PDV #{$numero}";
                },
                'tags' => ['cancelamento'],
            ],
        ];
    }
}