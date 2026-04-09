<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Models\FaturamentoLocacao;
use Closure;
use DateTimeInterface;

class FaturamentoLocacaoMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'FaturamentoLocacao';
    }

    public static function tags(): array
    {
        return ['faturamento', 'locacao', 'financeiro'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof FaturamentoLocacao) {
                return 'Fatura de locacao';
            }

            $numeroFatura = self::numeroFatura($model);
            $numeroContrato = self::numeroContrato($model);
            $cliente = self::nomeCliente($model);
            $valorTotal = self::moeda($model->valor_total ?? 0);

            return "Fatura #{$numeroFatura} - Contrato #{$numeroContrato} - {$cliente} - {$valorTotal}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof FaturamentoLocacao) {
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
                'acao' => 'fatura_locacao.gerada',
                'icone' => 'file-text',
                'cor' => 'verde',
                'descricao' => static function (FaturamentoLocacao $fatura): string {
                    $numeroFatura = self::numeroFatura($fatura);
                    $numeroContrato = self::numeroContrato($fatura);
                    $cliente = self::nomeCliente($fatura);
                    $valor = self::moeda($fatura->valor_total ?? 0);
                    $vencimento = self::data($fatura->data_vencimento);

                    return "Gerou fatura #{$numeroFatura} para locacao #{$numeroContrato} - {$cliente} - {$valor} - Venc.: {$vencimento}";
                },
                'tags' => ['fatura_gerada', 'financeiro'],
            ],
            'updated' => [
                'acao' => 'fatura_locacao.editada',
                'icone' => 'file-text',
                'cor' => 'amarelo',
                'descricao' => static function (FaturamentoLocacao $fatura): string {
                    $numeroFatura = self::numeroFatura($fatura);
                    $numeroContrato = self::numeroContrato($fatura);
                    $cliente = self::nomeCliente($fatura);

                    $descricao = "Editou fatura #{$numeroFatura} da locacao #{$numeroContrato} - {$cliente}";

                    $valorAntes = $fatura->getOriginal('valor_total');
                    $valorDepois = $fatura->valor_total;
                    if ((float) $valorAntes !== (float) $valorDepois) {
                        $descricao .= ' - valor alterado: ' . self::moeda($valorAntes) . ' -> ' . self::moeda($valorDepois);
                    }

                    $vencAntes = $fatura->getOriginal('data_vencimento');
                    $vencDepois = $fatura->data_vencimento;
                    if ((string) $vencAntes !== (string) $vencDepois) {
                        $descricao .= ' - vencimento alterado: ' . self::data($vencAntes) . ' -> ' . self::data($vencDepois);
                    }

                    return $descricao;
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'fatura_locacao.excluida',
                'icone' => 'file-x',
                'cor' => 'vermelho',
                'descricao' => static function (FaturamentoLocacao $fatura): string {
                    $numeroFatura = self::numeroFatura($fatura);
                    $numeroContrato = self::numeroContrato($fatura);
                    $cliente = self::nomeCliente($fatura);
                    $valor = self::moeda($fatura->valor_total ?? 0);

                    return "Excluiu fatura #{$numeroFatura} da locacao #{$numeroContrato} - {$cliente} - {$valor}";
                },
                'tags' => ['exclusao'],
            ],
            'cancelamento' => [
                'acao' => 'fatura_locacao.cancelada',
                'icone' => 'x-circle',
                'cor' => 'vermelho',
                'descricao' => static function (FaturamentoLocacao $fatura): string {
                    $numeroFatura = self::numeroFatura($fatura);
                    $numeroContrato = self::numeroContrato($fatura);
                    $cliente = self::nomeCliente($fatura);
                    $valor = self::moeda($fatura->valor_total ?? 0);
                    $idConta = (int) ($fatura->id_conta_receber ?? 0);

                    if ($idConta > 0) {
                        return "Cancelou fatura #{$numeroFatura} da locacao #{$numeroContrato} - {$cliente} - {$valor} - Conta a receber #{$idConta} tambem cancelada automaticamente";
                    }

                    return "Cancelou fatura #{$numeroFatura} da locacao #{$numeroContrato} - {$cliente} - {$valor}";
                },
                'tags' => ['cancelamento', 'financeiro'],
            ],
            'faturamento_lote' => [
                'acao' => 'fatura_locacao.lote_gerado',
                'icone' => 'layers',
                'cor' => 'verde-escuro',
                'descricao' => static function (FaturamentoLocacao $fatura): string {
                    $idGrupo = trim((string) ($fatura->id_grupo_faturamento ?? ''));
                    $valor = self::moeda($fatura->valor_total ?? 0);

                    if ($idGrupo === '') {
                        return "Gerou lote de faturamento - Total: {$valor}";
                    }

                    return "Gerou lote de faturamento #{$idGrupo} - Total parcial da fatura: {$valor}";
                },
                'tags' => ['lote', 'faturamento', 'financeiro'],
            ],
        ];
    }

    private static function numeroFatura(FaturamentoLocacao $fatura): string
    {
        $numero = trim((string) ($fatura->numero_fatura ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($fatura->id_faturamento_locacao ?? '-');
    }

    private static function numeroContrato(FaturamentoLocacao $fatura): string
    {
        $locacao = $fatura->locacao;
        $numero = trim((string) ($locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($fatura->id_locacao ?? '-');
    }

    private static function nomeCliente(FaturamentoLocacao $fatura): string
    {
        $cliente = $fatura->cliente;

        $nome = trim((string) ($cliente->nome ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $razao = trim((string) ($cliente->razao_social ?? ''));
        if ($razao !== '') {
            return $razao;
        }

        return 'Cliente nao identificado';
    }

    private static function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    private static function data($valor): string
    {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format('d/m/Y');
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return '-';
        }

        try {
            return date('d/m/Y', strtotime($texto));
        } catch (\Throwable $e) {
            return $texto;
        }
    }
}
