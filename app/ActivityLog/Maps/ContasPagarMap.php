<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Models\ContasAPagar;
use Closure;

class ContasPagarMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'conta_pagar';
    }

    public static function tags(): array
    {
        return ['contas_pagar'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof ContasAPagar) {
                return 'Conta a pagar';
            }

            $descricao = trim((string) ($model->descricao ?? ''));

            if ($descricao === '') {
                return 'Conta a pagar #' . $model->id_contas;
            }

            return 'Conta a pagar #' . $model->id_contas . ' — ' . $descricao;
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof ContasAPagar) {
                return null;
            }

            return isset($model->valor_total) ? (float) $model->valor_total : null;
        };
    }

    public static function camposSensiveis(): array
    {
        return [
            'senha',
            'remember_token',
            'session_token',
            'codigo_reset',
            'google_calendar_token',
        ];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'conta_pagar.criada',
                'icone' => 'plus',
                'cor' => 'verde',
                'descricao' => static function (ContasAPagar $conta): string {
                    return sprintf(
                        'Criou a conta a pagar #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['financeiro', 'cadastro'],
            ],
            'updated' => [
                'acao' => 'conta_pagar.editada',
                'icone' => 'edit',
                'cor' => 'amarelo',
                'descricao' => static function (ContasAPagar $conta): string {
                    return sprintf(
                        'Editou a conta a pagar #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['financeiro', 'edicao'],
            ],
            'deleted' => [
                'acao' => 'conta_pagar.excluida',
                'icone' => 'trash',
                'cor' => 'vermelho',
                'descricao' => static function (ContasAPagar $conta): string {
                    return sprintf(
                        'Excluiu a conta a pagar #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['financeiro', 'exclusao'],
            ],
            'baixa' => [
                'acao' => 'conta_pagar.baixa',
                'icone' => 'cash',
                'cor' => 'azul-escuro',
                'descricao' => static function (ContasAPagar $conta): string {
                    return sprintf(
                        'Registrou baixa na conta a pagar #%d — valor pago atual R$ %s',
                        $conta->id_contas,
                        number_format((float) ($conta->valor_pago ?? 0), 2, ',', '.')
                    );
                },
                'tags' => ['financeiro', 'pagamento'],
            ],
            'estorno' => [
                'acao' => 'conta_pagar.estornada',
                'icone' => 'rotate-clockwise',
                'cor' => 'laranja',
                'descricao' => static function (ContasAPagar $conta): string {
                    return sprintf(
                        'Estornou pagamento da conta a pagar #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['financeiro', 'pagamento', 'estorno'],
            ],
        ];
    }
}
