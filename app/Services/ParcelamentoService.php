<?php

namespace App\Services;

use App\Models\ContasAPagar;
use App\Models\ContasAReceber;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ParcelamentoService
{
    /**
     * Criar parcelas de uma conta
     * 
     * @param array $dados Dados da conta original
     * @param int $numeroParcelas Número de parcelas
     * @param string $tipo Tipo da conta: 'pagar' ou 'receber'
     * @param int $intervaloDias Intervalo em dias entre parcelas (padrão: 30)
     * @return array Array com as contas criadas
     */
    public function criarParcelas(array $dados, int $numeroParcelas, string $tipo = 'pagar', int $intervaloDias = 30)
    {
        if ($numeroParcelas < 2) {
            throw new \Exception('O número de parcelas deve ser maior que 1');
        }

        $model = $tipo === 'pagar' ? ContasAPagar::class : ContasAReceber::class;
        $idParcelamento = Str::uuid()->toString();
        $valorParcela = round($dados['valor_total'] / $numeroParcelas, 2);
        $dataVencimento = Carbon::parse($dados['data_vencimento']);
        $contas = [];

        // Ajustar última parcela para compensar arredondamento
        $totalParcelas = $valorParcela * $numeroParcelas;
        $diferenca = $dados['valor_total'] - $totalParcelas;

        DB::beginTransaction();
        try {
            for ($i = 1; $i <= $numeroParcelas; $i++) {
                $valorFinal = $valorParcela;
                
                // Adicionar diferença de arredondamento na última parcela
                if ($i === $numeroParcelas) {
                    $valorFinal += $diferenca;
                }

                $dadosParcela = array_merge($dados, [
                    'descricao' => $dados['descricao'] . " (Parcela {$i}/{$numeroParcelas})",
                    'valor_total' => $valorFinal,
                    'valor_pago' => 0,
                    'data_vencimento' => $dataVencimento->copy()->addDays($intervaloDias * ($i - 1)),
                    'data_pagamento' => null,
                    'status' => 'pendente',
                    'numero_parcela' => $i,
                    'total_parcelas' => $numeroParcelas,
                    'id_parcelamento' => $idParcelamento,
                ]);

                $conta = $model::create($dadosParcela);
                $contas[] = $conta;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $contas;
    }

    /**
     * Criar contas recorrentes
     * 
     * @param array $dados Dados da conta original
     * @param string $tipoRecorrencia Tipo: diario, semanal, quinzenal, mensal, bimestral, trimestral, semestral, anual
     * @param int|null $quantidadeRecorrencias Quantidade de repetições (null = infinito, mas limitamos a 60)
     * @param string $tipo Tipo da conta: 'pagar' ou 'receber'
     * @return array Array com as contas criadas
     */
    public function criarRecorrencias(array $dados, string $tipoRecorrencia, ?int $quantidadeRecorrencias = null, string $tipo = 'pagar')
    {
        $tiposPermitidos = ['diario', 'semanal', 'quinzenal', 'mensal', 'bimestral', 'trimestral', 'semestral', 'anual'];
        
        if (!in_array($tipoRecorrencia, $tiposPermitidos)) {
            throw new \Exception('Tipo de recorrência inválido');
        }

        // Limitar recorrências infinitas a 60 (ou mais para tipos diários/semanais)
        $limiteMaximo = in_array($tipoRecorrencia, ['diario', 'semanal', 'quinzenal']) ? 365 : 60;
        if (is_null($quantidadeRecorrencias) || $quantidadeRecorrencias > $limiteMaximo) {
            $quantidadeRecorrencias = $limiteMaximo;
        }

        if ($quantidadeRecorrencias < 2) {
            throw new \Exception('A quantidade de recorrências deve ser maior que 1');
        }

        $model = $tipo === 'pagar' ? ContasAPagar::class : ContasAReceber::class;
        $idRecorrencia = Str::uuid()->toString();
        $dataVencimento = Carbon::parse($dados['data_vencimento']);
        $contas = [];

        // Mapa de dias por tipo de recorrência
        $diasIncremento = [
            'diario' => 1,
            'semanal' => 7,
            'quinzenal' => 15,
            'mensal' => 30,
            'bimestral' => 60,
            'trimestral' => 90,
            'semestral' => 180,
            'anual' => 365,
        ];

        $dias = $diasIncremento[$tipoRecorrencia];

        DB::beginTransaction();
        try {
            for ($i = 0; $i < $quantidadeRecorrencias; $i++) {
                $dadosRecorrencia = array_merge($dados, [
                    'data_vencimento' => $dataVencimento->copy()->addDays($dias * $i),
                    'data_pagamento' => null,
                    'status' => 'pendente',
                    'valor_pago' => 0,
                    'tipo_recorrencia' => $tipoRecorrencia,
                    'quantidade_recorrencias' => $quantidadeRecorrencias,
                    'id_recorrencia' => $idRecorrencia,
                    'is_recorrente' => true,
                ]);

                $conta = $model::create($dadosRecorrencia);
                $contas[] = $conta;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return $contas;
    }

    /**
     * Cancelar todas as parcelas de um parcelamento
     * 
     * @param string $idParcelamento
     * @param string $tipo Tipo da conta: 'pagar' ou 'receber'
     * @return int Número de parcelas canceladas
     */
    public function cancelarParcelas(string $idParcelamento, string $tipo = 'pagar')
    {
        $model = $tipo === 'pagar' ? ContasAPagar::class : ContasAReceber::class;
        
        return $model::where('id_parcelamento', $idParcelamento)
            ->where('status', '!=', 'pago')
            ->update(['status' => 'cancelado']);
    }

    /**
     * Cancelar todas as recorrências futuras
     * 
     * @param string $idRecorrencia
     * @param string $tipo Tipo da conta: 'pagar' ou 'receber'
     * @param bool $incluirPagas Se deve cancelar também as já pagas
     * @return int Número de recorrências canceladas
     */
    public function cancelarRecorrencias(string $idRecorrencia, string $tipo = 'pagar', bool $incluirPagas = false)
    {
        $model = $tipo === 'pagar' ? ContasAPagar::class : ContasAReceber::class;
        $query = $model::where('id_recorrencia', $idRecorrencia);

        if (!$incluirPagas) {
            $query->where('status', '!=', 'pago');
        }

        return $query->update(['status' => 'cancelado']);
    }
}
