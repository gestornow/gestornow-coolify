<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Models\Boleto;
use Closure;

class BoletosMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'boleto';
    }

    public static function tags(): array
    {
        return ['boletos', 'financeiro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Boleto) {
                return 'Boleto';
            }

            $nossoNumero = trim((string) ($model->nosso_numero ?? ''));
            if ($nossoNumero !== '') {
                return sprintf('Boleto %s', $nossoNumero);
            }

            $id = (int) ($model->id_boleto ?? 0);
            if ($id > 0) {
                return sprintf('Boleto #%d', $id);
            }

            return 'Boleto';
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof Boleto) {
                return null;
            }

            if (isset($model->valor_pago) && (float) $model->valor_pago > 0) {
                return (float) $model->valor_pago;
            }

            return isset($model->valor_nominal) ? (float) $model->valor_nominal : null;
        };
    }

    public static function camposSensiveis(): array
    {
        return [
            'codigo_barras',
            'linha_digitavel',
            'json_resposta',
            'json_webhook',
            'url_pdf',
        ];
    }

    public static function eventos(): array
    {
        return [
            'created' => [
                'acao' => 'boleto.gerado',
                'icone' => 'barcode',
                'cor' => 'verde',
                'descricao' => static function (Boleto $boleto): string {
                    $id = (int) ($boleto->id_boleto ?? 0);
                    return "Gerou boleto #{$id}";
                },
                'tags' => ['geracao'],
            ],
            'updated' => [
                'acao' => 'boleto.atualizado',
                'icone' => 'refresh-cw',
                'cor' => 'amarelo',
                'descricao' => static function (Boleto $boleto): string {
                    $id = (int) ($boleto->id_boleto ?? 0);
                    return "Atualizou boleto #{$id}";
                },
                'tags' => ['atualizacao'],
            ],
            'deleted' => [
                'acao' => 'boleto.excluido',
                'icone' => 'trash-2',
                'cor' => 'vermelho',
                'descricao' => static function (Boleto $boleto): string {
                    $id = (int) ($boleto->id_boleto ?? 0);
                    return "Excluiu boleto #{$id}";
                },
                'tags' => ['exclusao'],
            ],
            'consulta' => [
                'acao' => 'boleto.consultado',
                'icone' => 'search',
                'cor' => 'azul',
                'descricao' => static function (Boleto $boleto): string {
                    $id = (int) ($boleto->id_boleto ?? 0);
                    return "Consultou situacao do boleto #{$id}";
                },
                'tags' => ['consulta'],
            ],
            'pdf_visualizado' => [
                'acao' => 'boleto.pdf_visualizado',
                'icone' => 'file-text',
                'cor' => 'azul-claro',
                'descricao' => static function (Boleto $boleto): string {
                    $id = (int) ($boleto->id_boleto ?? 0);
                    return "Visualizou PDF do boleto #{$id}";
                },
                'tags' => ['pdf'],
            ],
            'vencimento_alterado' => [
                'acao' => 'boleto.vencimento_alterado',
                'icone' => 'calendar-clock',
                'cor' => 'laranja',
                'descricao' => static function (Boleto $boleto): string {
                    $id = (int) ($boleto->id_boleto ?? 0);
                    return "Alterou vencimento de boleto #{$id}";
                },
                'tags' => ['vencimento', 'alteracao'],
            ],
        ];
    }
}