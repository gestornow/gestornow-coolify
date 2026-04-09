<?php

namespace App\ActivityLog\Maps;

use App\ActivityLog\Contracts\ActivityMap;
use App\Domain\Locacao\Models\LocacaoRetornoPatrimonio;
use Closure;

class LocacaoRetornoPatrimonioMap implements ActivityMap
{
    public static function entidadeTipo(): string
    {
        return 'LocacaoRetornoPatrimonio';
    }

    public static function tags(): array
    {
        return ['locacao', 'patrimonios', 'retorno'];
    }

    public static function label(): Closure
    {
        return static function ($model): string {
            if (!$model instanceof LocacaoRetornoPatrimonio) {
                return 'Retorno de patrimonio';
            }

            $idPatrimonio = (int) ($model->id_patrimonio ?? 0);
            $serie = self::numeroSerie($model);
            $contrato = self::numeroContrato($model);

            return "Retorno: Patrimonio #{$idPatrimonio} (S/N: {$serie}) - Contrato #{$contrato}";
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
                'acao' => 'locacao_retorno.registrado',
                'icone' => 'corner-down-left',
                'cor' => 'azul',
                'descricao' => static function (LocacaoRetornoPatrimonio $retorno): string {
                    $idPatrimonio = (int) ($retorno->id_patrimonio ?? 0);
                    $serie = self::numeroSerie($retorno);
                    $produto = self::nomeProduto($retorno);
                    $contrato = self::numeroContrato($retorno);
                    $status = self::statusLegivel($retorno->status_retorno ?? null);

                    $descricao = "Registrou retorno do patrimonio #{$idPatrimonio} (S/N: {$serie} - {$produto}) da locacao #{$contrato} - Status: {$status}";

                    $statusChave = trim(strtolower((string) ($retorno->status_retorno ?? '')));
                    if ($statusChave === 'avariado') {
                        $descricao .= ' - AVARIA REGISTRADA';
                    }

                    if ($statusChave === 'extraviado') {
                        $descricao .= ' - PATRIMONIO EXTRAVIADO';
                    }

                    return $descricao;
                },
                'tags' => ['retorno_registrado'],
            ],
            'updated' => [
                'acao' => 'locacao_retorno.editado',
                'icone' => 'corner-down-left',
                'cor' => 'amarelo',
                'descricao' => static function (LocacaoRetornoPatrimonio $retorno): string {
                    $idPatrimonio = (int) ($retorno->id_patrimonio ?? 0);
                    $contrato = self::numeroContrato($retorno);
                    $descricaoBase = "Editou registro de retorno do patrimonio #{$idPatrimonio} da locacao #{$contrato}";

                    $mudancas = $retorno->getChanges();
                    $temStatusNovo = array_key_exists('status_retorno', $mudancas);
                    if (!$temStatusNovo) {
                        return $descricaoBase;
                    }

                    $statusAnterior = self::statusLegivel($retorno->getOriginal('status_retorno'));
                    $statusNovo = self::statusLegivel($mudancas['status_retorno'] ?? null);

                    return $descricaoBase . " - status: '{$statusAnterior}' -> '{$statusNovo}'";
                },
                'tags' => ['edicao'],
            ],
        ];
    }

    private static function numeroContrato(LocacaoRetornoPatrimonio $retorno): string
    {
        $numero = trim((string) ($retorno->locacao->numero_contrato ?? ''));

        if ($numero !== '') {
            return $numero;
        }

        return (string) ($retorno->id_locacao ?? '-');
    }

    private static function numeroSerie(LocacaoRetornoPatrimonio $retorno): string
    {
        $serie = trim((string) ($retorno->patrimonio->numero_serie ?? ''));

        if ($serie !== '') {
            return $serie;
        }

        $codigo = trim((string) ($retorno->patrimonio->codigo_patrimonio ?? ''));

        if ($codigo !== '') {
            return $codigo;
        }

        return 'sem numero de serie';
    }

    private static function nomeProduto(LocacaoRetornoPatrimonio $retorno): string
    {
        $nomeViaItem = trim((string) ($retorno->produtoLocacao->produto->nome ?? ''));
        if ($nomeViaItem !== '') {
            return $nomeViaItem;
        }

        $nomeViaPatrimonio = trim((string) ($retorno->patrimonio->produto->nome ?? ''));
        if ($nomeViaPatrimonio !== '') {
            return $nomeViaPatrimonio;
        }

        return 'Produto nao identificado';
    }

    private static function statusLegivel($status): string
    {
        $mapa = [
            'normal' => 'Normal',
            'avariado' => 'Avariado',
            'extraviado' => 'Extraviado',
            'devolvido' => 'Devolvido',
            'pendente' => 'Pendente',
        ];

        $chave = trim(strtolower((string) $status));

        if ($chave === '') {
            return 'Nao informado';
        }

        return $mapa[$chave] ?? ucfirst($chave);
    }
}
