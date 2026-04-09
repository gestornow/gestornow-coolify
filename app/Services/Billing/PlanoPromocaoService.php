<?php

namespace App\Services\Billing;

use App\Domain\Auth\Models\Empresa;
use App\Models\Plano;
use App\Models\PlanoPromocao;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class PlanoPromocaoService
{
    /**
     * Calcula os valores finais com base na promoção vigente do plano.
     */
    public function calcularValoresPromocionais(Plano $plano, Empresa $empresa, ?Carbon $referencia = null): array
    {
        $valorMensalOriginal = (float) $plano->valor;
        $valorAdesaoOriginal = (float) $plano->adesao;

        $resultadoBase = [
            'valor_mensal_original' => round($valorMensalOriginal, 2),
            'valor_adesao_original' => round($valorAdesaoOriginal, 2),
            'valor_mensal_final' => round($valorMensalOriginal, 2),
            'valor_adesao_final' => round($valorAdesaoOriginal, 2),
            'promocao_aplicada' => null,
        ];

        if (!Schema::hasTable('planos_promocoes')) {
            return $resultadoBase;
        }

        $referencia = $referencia ? $referencia->copy()->startOfDay() : now()->startOfDay();

        // Busca promoção vigente (ativa e dentro do período de datas)
        $promocao = PlanoPromocao::query()
            ->where('id_plano', $plano->id_plano)
            ->vigentes($referencia)
            ->orderBy('id', 'desc')
            ->first();

        if (!$promocao) {
            return $resultadoBase;
        }

        $valorMensalFinal = max(0, $valorMensalOriginal - (float) $promocao->desconto_mensal);
        $valorAdesaoFinal = max(0, $valorAdesaoOriginal - (float) $promocao->desconto_adesao);

        return [
            'valor_mensal_original' => round($valorMensalOriginal, 2),
            'valor_adesao_original' => round($valorAdesaoOriginal, 2),
            'valor_mensal_final' => round($valorMensalFinal, 2),
            'valor_adesao_final' => round($valorAdesaoFinal, 2),
            'promocao_aplicada' => [
                'id' => (int) $promocao->id,
                'nome' => (string) $promocao->nome,
                'desconto_mensal' => round((float) $promocao->desconto_mensal, 2),
                'desconto_adesao' => round((float) $promocao->desconto_adesao, 2),
                'data_inicio' => $promocao->data_inicio?->format('d/m/Y'),
                'data_fim' => $promocao->data_fim?->format('d/m/Y'),
            ],
        ];
    }

    /**
     * Retorna a promoção vigente para um plano
     */
    public function obterPromocaoVigente(Plano $plano, ?Carbon $referencia = null): ?PlanoPromocao
    {
        if (!Schema::hasTable('planos_promocoes')) {
            return null;
        }

        $referencia = $referencia ?? now();

        return PlanoPromocao::query()
            ->where('id_plano', $plano->id_plano)
            ->vigentes($referencia)
            ->orderBy('id', 'desc')
            ->first();
    }
}
