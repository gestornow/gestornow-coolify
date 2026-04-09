<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Models\ContasAReceber;
use Closure;

class ContasReceberMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'conta_receber';
    }

    public static function tags(): array
    {
        return ['contas_receber', 'financeiro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof ContasAReceber) {
                return 'Conta a receber';
            }

            $clienteNome = trim((string) ($model->cliente->nome ?? ''));
            $descricao = trim((string) ($model->descricao ?? ''));

            if ($clienteNome !== '') {
                return 'Conta #' . $model->id_contas . ' — ' . $clienteNome;
            }

            if ($descricao !== '') {
                return 'Conta #' . $model->id_contas . ' — ' . $descricao;
            }

            return 'Conta #' . $model->id_contas;
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof ContasAReceber) {
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
                'acao' => 'conta_receber.criada',
                'icone' => 'file-plus',
                'cor' => 'verde',
                'descricao' => static function (ContasAReceber $conta): string {
                    return sprintf(
                        'Criou a conta a receber #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['cadastro'],
            ],
            'updated' => [
                'acao' => 'conta_receber.editada',
                'icone' => 'edit',
                'cor' => 'amarelo',
                'descricao' => static function (ContasAReceber $conta): string {
                    return sprintf(
                        'Editou a conta a receber #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'conta_receber.excluida',
                'icone' => 'trash',
                'cor' => 'vermelho',
                'descricao' => static function (ContasAReceber $conta): string {
                    return sprintf(
                        'Excluiu a conta a receber #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['exclusao'],
            ],
            'baixa' => [
                'acao' => 'conta_receber.baixa',
                'icone' => 'check-circle',
                'cor' => 'verde-escuro',
                'descricao' => static function (ContasAReceber $conta): string {
                    return sprintf(
                        'Baixa na conta #%d — Recebido: R$ %s',
                        $conta->id_contas,
                        number_format((float) ($conta->valor_pago ?? 0), 2, ',', '.')
                    );
                },
                'tags' => ['recebimento'],
            ],
            'baixa_parcial' => [
                'acao' => 'conta_receber.baixa_parcial',
                'icone' => 'check-square',
                'cor' => 'ciano',
                'descricao' => static function (ContasAReceber $conta): string {
                    $valorPago = (float) ($conta->valor_pago ?? 0);
                    $valorTotal = (float) ($conta->valor_total ?? 0);
                    $saldo = max(0, $valorTotal - $valorPago);

                    $sufixoParcela = (!is_null($conta->numero_parcela) && !is_null($conta->total_parcelas))
                        ? sprintf(' — Parcela %d/%d', (int) $conta->numero_parcela, (int) $conta->total_parcelas)
                        : '';

                    return sprintf(
                        'Baixa parcial na conta #%d — Recebido: R$ %s — Saldo: R$ %s%s',
                        $conta->id_contas,
                        number_format($valorPago, 2, ',', '.'),
                        number_format($saldo, 2, ',', '.'),
                        $sufixoParcela
                    );
                },
                'tags' => ['recebimento', 'parcial'],
            ],
            'estorno' => [
                'acao' => 'conta_receber.estorno',
                'icone' => 'rotate-ccw',
                'cor' => 'laranja',
                'descricao' => static function (ContasAReceber $conta): string {
                    return sprintf(
                        'Estornou recebimento da conta a receber #%d — %s',
                        $conta->id_contas,
                        (string) ($conta->descricao ?? 'Sem descrição')
                    );
                },
                'tags' => ['estorno'],
            ],
            'baixa_boleto' => [
                'acao' => 'conta_receber.baixa_boleto',
                'icone' => 'credit-card',
                'cor' => 'azul',
                'descricao' => static function (ContasAReceber $conta): string {
                    return sprintf(
                        'Baixa automática por boleto pago na conta #%d — Valor: R$ %s',
                        $conta->id_contas,
                        number_format((float) ($conta->valor_pago ?? 0), 2, ',', '.')
                    );
                },
                'tags' => ['recebimento', 'boleto', 'automatico'],
            ],
        ];
    }
}
