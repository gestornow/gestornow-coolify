<?php

namespace App\Services\Billing;

use App\Domain\Auth\Models\Empresa;
use App\Models\Plano;
use App\Models\PlanoContratado;
use App\Models\PlanoContratadoModulo;
use Illuminate\Support\Facades\DB;

class PlanoProvisioningService
{
    /**
     * Ativa um plano para a empresa e sincroniza os módulos contratados.
     */
    public function ativarPlano(
        Empresa $empresa,
        Plano $plano,
        ?float $valorMensal = null,
        ?float $valorAdesao = null,
        ?string $observacoes = null,
        bool $reativarEmpresa = true
    ): PlanoContratado {
        return DB::transaction(function () use ($empresa, $plano, $valorMensal, $valorAdesao, $observacoes, $reativarEmpresa) {
            PlanoContratado::where('id_empresa', $empresa->id_empresa)
                ->where('status', 'ativo')
                ->update(['status' => 'inativo']);

            $contrato = PlanoContratado::create([
                'id_empresa' => $empresa->id_empresa,
                'nome' => $plano->nome,
                'valor' => $valorMensal ?? (float) $plano->valor,
                'adesao' => $valorAdesao ?? (float) $plano->adesao,
                'data_contratacao' => now(),
                'status' => 'ativo',
                'observacoes' => $observacoes,
            ]);

            $plano->loadMissing('modulos');

            foreach ($plano->modulos as $moduloPlano) {
                PlanoContratadoModulo::create([
                    'id_plano_contratado' => $contrato->id,
                    'id_modulo' => $moduloPlano->id_modulo,
                    'limite' => $moduloPlano->limite,
                    'ativo' => 1,
                ]);
            }

            if ($reativarEmpresa && in_array($empresa->status, ['teste', 'teste bloqueado', 'bloqueado'], true)) {
                $empresa->update([
                    'status' => 'ativo',
                    'data_bloqueio' => null,
                    'data_fim_teste' => null,
                ]);
            }

            return $contrato;
        });
    }
}
