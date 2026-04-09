<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\LocacaoDespesa;
use Closure;

class LocacaoDespesaMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'LocacaoDespesa';
    }

    public static function tags(): array
    {
        return ['locacao', 'despesas', 'financeiro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof LocacaoDespesa) {
                return 'Despesa da locacao';
            }

            $descricao = self::descricaoDespesa($model);
            $contrato = self::numeroContrato($model);

            return "Despesa: {$descricao} - Contrato #{$contrato}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof LocacaoDespesa) {
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
                'acao' => 'locacao_despesa.adicionada',
                'icone' => 'dollar-sign',
                'cor' => 'laranja',
                'descricao' => static function (LocacaoDespesa $despesa): string {
                    $descricao = self::descricaoDespesa($despesa);
                    $contrato = self::numeroContrato($despesa);
                    $valor = self::moeda($despesa->valor ?? 0);
                    $tipo = self::tipoLegivel($despesa->tipo);

                    return "Adicionou despesa '{$descricao}' na locacao #{$contrato} - {$valor} ({$tipo})";
                },
                'tags' => ['despesa_adicionada'],
            ],
            'updated' => [
                'acao' => 'locacao_despesa.editada',
                'icone' => 'dollar-sign',
                'cor' => 'amarelo',
                'descricao' => static function (LocacaoDespesa $despesa): string {
                    $descricao = self::descricaoDespesa($despesa);
                    $contrato = self::numeroContrato($despesa);

                    return "Editou despesa '{$descricao}' na locacao #{$contrato}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'locacao_despesa.removida',
                'icone' => 'dollar-sign',
                'cor' => 'vermelho',
                'descricao' => static function (LocacaoDespesa $despesa): string {
                    $descricao = self::descricaoDespesa($despesa);
                    $contrato = self::numeroContrato($despesa);
                    $valor = self::moeda($despesa->valor ?? 0);

                    return "Removeu despesa '{$descricao}' da locacao #{$contrato} - {$valor}";
                },
                'tags' => ['despesa_removida'],
            ],
        ];
    }

    private static function descricaoDespesa(LocacaoDespesa $despesa): string
    {
        $descricao = trim((string) ($despesa->descricao ?? ''));

        return $descricao !== '' ? $descricao : 'Despesa nao identificada';
    }

    private static function numeroContrato(LocacaoDespesa $despesa): string
    {
        $numero = trim((string) ($despesa->locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($despesa->id_locacao ?? '-');
    }

    private static function tipoLegivel($tipo): string
    {
        $chave = trim((string) $tipo);
        if ($chave === '') {
            return 'Nao informado';
        }

        $tipos = LocacaoDespesa::tipos();

        return $tipos[$chave] ?? $chave;
    }

    private static function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }
}
