<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\LocacaoServico;
use Closure;

class LocacaoServicoMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'LocacaoServico';
    }

    public static function tags(): array
    {
        return ['locacao', 'servicos'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof LocacaoServico) {
                return 'Servico da locacao';
            }

            $descricao = self::descricaoServico($model);
            $contrato = self::numeroContrato($model);

            return "Servico: {$descricao} - Contrato #{$contrato}";
        };
    }

    public static function valor(): ?Closure
    {
        return static function ($model): ?float {
            if (!$model instanceof LocacaoServico) {
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
                'acao' => 'locacao_servico.adicionado',
                'icone' => 'tool',
                'cor' => 'verde',
                'descricao' => static function (LocacaoServico $servico): string {
                    $descricao = self::descricaoServico($servico);
                    $contrato = self::numeroContrato($servico);
                    $quantidade = max(1, (int) ($servico->quantidade ?? 1));
                    $precoUnitario = self::moeda($servico->preco_unitario ?? 0);
                    $valorTotal = self::moeda($servico->valor_total ?? 0);

                    $texto = "Adicionou servico '{$descricao}' na locacao #{$contrato} - {$quantidade}x {$precoUnitario} - Total: {$valorTotal}";

                    if (self::tipoItemLegivel($servico->tipo_item) === 'Terceiro') {
                        $fornecedor = trim((string) ($servico->fornecedor_nome ?? ''));
                        if ($fornecedor !== '') {
                            $texto .= " - Fornecedor: {$fornecedor}";
                        }
                    }

                    if ((bool) ($servico->gerar_conta_pagar ?? false)) {
                        $contaValor = self::moeda($servico->conta_valor ?? 0);
                        $contaVencimento = self::data($servico->conta_vencimento);
                        $parcelas = max(1, (int) ($servico->conta_parcelas ?? 1));

                        $texto .= " - Gera conta a pagar: {$contaValor} venc. {$contaVencimento} em {$parcelas}x";
                    }

                    return $texto;
                },
                'tags' => ['servico_adicionado'],
            ],
            'updated' => [
                'acao' => 'locacao_servico.editado',
                'icone' => 'tool',
                'cor' => 'amarelo',
                'descricao' => static function (LocacaoServico $servico): string {
                    $descricao = self::descricaoServico($servico);
                    $contrato = self::numeroContrato($servico);

                    return "Editou servico '{$descricao}' na locacao #{$contrato}";
                },
                'tags' => ['edicao'],
            ],
            'deleted' => [
                'acao' => 'locacao_servico.removido',
                'icone' => 'tool',
                'cor' => 'vermelho',
                'descricao' => static function (LocacaoServico $servico): string {
                    $descricao = self::descricaoServico($servico);
                    $contrato = self::numeroContrato($servico);
                    $valorTotal = self::moeda($servico->valor_total ?? 0);

                    return "Removeu servico '{$descricao}' da locacao #{$contrato} - {$valorTotal}";
                },
                'tags' => ['servico_removido'],
            ],
        ];
    }

    private static function descricaoServico(LocacaoServico $servico): string
    {
        $descricao = trim((string) ($servico->descricao ?? ''));

        return $descricao !== '' ? $descricao : 'Servico nao identificado';
    }

    private static function numeroContrato(LocacaoServico $servico): string
    {
        $numero = trim((string) ($servico->locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($servico->id_locacao ?? '-');
    }

    private static function tipoItemLegivel($tipo): string
    {
        $chave = trim(strtolower((string) $tipo));

        return match ($chave) {
            'proprio' => 'Proprio',
            'terceiro' => 'Terceiro',
            default => $chave !== '' ? ucfirst($chave) : 'Nao informado',
        };
    }

    private static function moeda($valor): string
    {
        return 'R$ ' . number_format((float) $valor, 2, ',', '.');
    }

    private static function data($valor): string
    {
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
