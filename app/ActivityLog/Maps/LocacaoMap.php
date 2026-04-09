<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\Locacao;
use Closure;
use DateTimeInterface;

class LocacaoMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'Locacao';
    }

    public static function tags(): array
    {
        return ['locacao', 'contratos'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof Locacao) {
                return 'Locacao';
            }

            $numeroContrato = self::numeroContrato($model);
            $cliente = self::nomeCliente($model);
            $valorFinal = self::moeda($model->valor_final ?? 0);
            $aditivo = self::aditivoTexto($model);

            return "Contrato #{$numeroContrato} - {$cliente} - {$valorFinal}{$aditivo}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof Locacao) {
                return null;
            }

            return isset($model->valor_final) ? (float) $model->valor_final : null;
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
                'acao' => 'locacao.criada',
                'icone' => 'file-plus',
                'cor' => 'verde',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Criou locacao #{$numeroContrato} para {$cliente} - {$valorFinal}";
                },
                'tags' => ['novo_contrato'],
            ],
            'updated' => [
                'acao' => 'locacao.editada',
                'icone' => 'edit',
                'cor' => 'amarelo',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);

                    if (self::alterouFinanceiro($locacao)) {
                        return "Editou locacao #{$numeroContrato} - {$cliente} - valores alterados";
                    }

                    return "Editou locacao #{$numeroContrato} - {$cliente}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'locacao.excluida',
                'icone' => 'file-x',
                'cor' => 'vermelho',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Excluiu locacao #{$numeroContrato} - {$cliente} - {$valorFinal}";
                },
                'tags' => ['exclusao'],
            ],
            'orcamento_criado' => [
                'acao' => 'locacao.orcamento_criado',
                'icone' => 'file-text',
                'cor' => 'cinza',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroOrcamento = self::numeroOrcamento($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Criou orcamento #{$numeroOrcamento} para {$cliente} - {$valorFinal}";
                },
                'tags' => ['orcamento'],
            ],
            'aprovacao' => [
                'acao' => 'locacao.aprovada',
                'icone' => 'check-circle',
                'cor' => 'verde-escuro',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Aprovou a locacao #{$numeroContrato} - {$cliente} - {$valorFinal}";
                },
                'tags' => ['aprovacao', 'status'],
            ],
            'cancelamento' => [
                'acao' => 'locacao.cancelada',
                'icone' => 'x-circle',
                'cor' => 'vermelho',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Cancelou a locacao #{$numeroContrato} - {$cliente} - {$valorFinal}";
                },
                'tags' => ['cancelamento', 'status'],
            ],
            'encerramento' => [
                'acao' => 'locacao.encerrada',
                'icone' => 'lock',
                'cor' => 'cinza-escuro',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Encerrou a locacao #{$numeroContrato} - {$cliente} - {$valorFinal}";
                },
                'tags' => ['encerramento', 'status'],
            ],
            'medicao_finalizada' => [
                'acao' => 'locacao.medicao_finalizada',
                'icone' => 'clipboard-check',
                'cor' => 'azul',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $valorLimite = self::moeda($locacao->valor_limite_medicao ?? 0);

                    return "Finalizou medicao da locacao #{$numeroContrato} - {$cliente} - {$valorLimite}";
                },
                'tags' => ['medicao', 'status'],
            ],
            'renovacao' => [
                'acao' => 'locacao.renovada',
                'icone' => 'refresh-cw',
                'cor' => 'azul',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $dataFim = self::data($locacao->data_fim);
                    $valorFinal = self::moeda($locacao->valor_final ?? 0);

                    return "Renovou a locacao #{$numeroContrato} - nova data fim: {$dataFim} - {$valorFinal}";
                },
                'tags' => ['renovacao'],
            ],
            'aditivo_gerado' => [
                'acao' => 'locacao.aditivo_gerado',
                'icone' => 'plus-circle',
                'cor' => 'roxo',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);
                    $cliente = self::nomeCliente($locacao);
                    $aditivo = (int) ($locacao->aditivo ?? 0);
                    $numeroAditivo = $aditivo > 0 ? $aditivo : 1;

                    return "Gerou aditivo #{$numeroAditivo} para locacao #{$numeroContrato} - {$cliente}";
                },
                'tags' => ['aditivo'],
            ],
            'status_logistica' => [
                'acao' => 'locacao.status_logistica_alterado',
                'icone' => 'truck',
                'cor' => 'azul-claro',
                'descricao' => static function (Locacao $locacao): string {
                    $numeroContrato = self::numeroContrato($locacao);

                    $statusAnterior = self::statusLogisticaLegivel(
                        $locacao->getOriginal('status_logistica')
                    );
                    $statusNovo = self::statusLogisticaLegivel($locacao->status_logistica);

                    return "Alterou status logistico da locacao #{$numeroContrato} de '{$statusAnterior}' para '{$statusNovo}'";
                },
                'tags' => ['logistica', 'status'],
            ],
        ];
    }

    private static function numeroContrato(Locacao $locacao): string
    {
        $numero = trim((string) ($locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($locacao->id_locacao ?? '-');
    }

    private static function numeroOrcamento(Locacao $locacao): string
    {
        if (!empty($locacao->numero_orcamento)) {
            return (string) $locacao->numero_orcamento;
        }

        $numeroContrato = trim((string) ($locacao->numero_contrato ?? ''));

        if ($numeroContrato !== '') {
            return $numeroContrato;
        }

        return (string) ($locacao->id_locacao ?? '-');
    }

    private static function nomeCliente(Locacao $locacao): string
    {
        $cliente = $locacao->cliente;

        $nome = trim((string) ($cliente->nome ?? ''));
        if ($nome !== '') {
            return $nome;
        }

        $razaoSocial = trim((string) ($cliente->razao_social ?? ''));
        if ($razaoSocial !== '') {
            return $razaoSocial;
        }

        return 'Cliente nao identificado';
    }

    private static function aditivoTexto(Locacao $locacao): string
    {
        $aditivo = (int) ($locacao->aditivo ?? 0);

        if ($aditivo <= 0) {
            return '';
        }

        return " (Aditivo #{$aditivo})";
    }

    private static function alterouFinanceiro(Locacao $locacao): bool
    {
        $camposFinanceiros = [
            'valor_total',
            'valor_frete',
            'valor_desconto',
            'valor_acrescimo',
            'valor_imposto',
            'valor_final',
        ];

        $camposAlterados = array_keys($locacao->getChanges());

        return count(array_intersect($camposFinanceiros, $camposAlterados)) > 0;
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

    private static function statusLogisticaLegivel($status): string
    {
        $mapa = [
            'para_separar' => 'Para Separar',
            'pronto_patio' => 'Pronto / No Patio',
            'em_rota' => 'Em Rota',
            'entregue' => 'Entregue',
            'aguardando_coleta' => 'Aguardando Coleta',
        ];

        $chave = trim((string) $status);

        if ($chave === '') {
            return 'Nao informado';
        }

        return $mapa[$chave] ?? $chave;
    }
}
