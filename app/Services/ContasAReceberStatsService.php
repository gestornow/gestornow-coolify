<?php

namespace App\Services;

use App\Models\ContasAReceber;
use Illuminate\Support\Facades\DB;

class ContasAReceberStatsService
{
    /**
     * Calcular estatísticas das contas a receber
     *
     * @param int $idEmpresa
     * @param string|null $mesFiltro Formato Y-m ou null para todos
     * @return array
     */
    public function getStats($idEmpresa, ?string $mesFiltro = null): array
    {
        $query = ContasAReceber::where('id_empresa', $idEmpresa);
        
        // Aplicar filtro de mês se fornecido
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $this->applyMonthFilter($query, $mesFiltro);
        }
        
        return [
            'total' => (clone $query)->count(),
            'pendentes' => (clone $query)->where('status', 'pendente')->count(),
            'recebidas' => (clone $query)->where('status', 'pago')->count(),
            'vencidas' => (clone $query)->vencidas()->count(),
            'valor_total_pendente' => (clone $query)
                ->whereIn('status', ['pendente', 'vencido', 'parcelado'])
                ->sum('valor_total'),
            'valor_total_recebido' => (clone $query)
                ->where('status', 'pago')
                ->sum('valor_pago'),
        ];
    }

    /**
     * Aplicar filtro de mês na query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $mesFiltro
     * @return void
     */
    private function applyMonthFilter($query, string $mesFiltro): void
    {
        $year = substr($mesFiltro, 0, 4);
        $month = substr($mesFiltro, 5, 2);
        
        $query->whereYear('data_vencimento', '=', $year)
              ->whereMonth('data_vencimento', '=', $month);
    }

    /**
     * Obter resumo por categoria
     *
     * @param int $idEmpresa
     * @param string|null $mesFiltro
     * @return \Illuminate\Support\Collection
     */
    public function getStatsByCategory(int $idEmpresa, ?string $mesFiltro = null)
    {
        $query = DB::table('contas_a_receber')
            ->select(
                'categoria_contas.nome as categoria',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(contas_a_receber.valor_total) as valor_total')
            )
            ->leftJoin('categoria_contas', 'contas_a_receber.id_categoria_contas', '=', 'categoria_contas.id_categoria_contas')
            ->where('contas_a_receber.id_empresa', $idEmpresa);
        
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $year = substr($mesFiltro, 0, 4);
            $month = substr($mesFiltro, 5, 2);
            $query->whereYear('contas_a_receber.data_vencimento', '=', $year)
                  ->whereMonth('contas_a_receber.data_vencimento', '=', $month);
        }
        
        return $query->groupBy('categoria_contas.nome')
            ->orderByDesc('valor_total')
            ->get();
    }

    /**
     * Obter resumo por cliente
     *
     * @param int $idEmpresa
     * @param string|null $mesFiltro
     * @return \Illuminate\Support\Collection
     */
    public function getStatsByCliente(int $idEmpresa, ?string $mesFiltro = null)
    {
        $query = DB::table('contas_a_receber')
            ->select(
                'clientes.nome as cliente',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(contas_a_receber.valor_total) as valor_total')
            )
            ->leftJoin('clientes', 'contas_a_receber.id_clientes', '=', 'clientes.id_clientes')
            ->where('contas_a_receber.id_empresa', $idEmpresa);
        
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $year = substr($mesFiltro, 0, 4);
            $month = substr($mesFiltro, 5, 2);
            $query->whereYear('contas_a_receber.data_vencimento', '=', $year)
                  ->whereMonth('contas_a_receber.data_vencimento', '=', $month);
        }
        
        return $query->groupBy('clientes.nome')
            ->orderByDesc('valor_total')
            ->get();
    }

    /**
     * Obter estatísticas por período
     *
     * @param int $idEmpresa
     * @param string $dataInicio
     * @param string $dataFim
     * @return array
     */
    public function getStatsByPeriod(int $idEmpresa, string $dataInicio, string $dataFim): array
    {
        $query = ContasAReceber::where('id_empresa', $idEmpresa)
            ->whereBetween('data_vencimento', [$dataInicio, $dataFim]);
        
        return [
            'total' => (clone $query)->count(),
            'pendentes' => (clone $query)->where('status', 'pendente')->count(),
            'recebidas' => (clone $query)->where('status', 'pago')->count(),
            'vencidas' => (clone $query)->vencidas()->count(),
            'valor_total' => (clone $query)->sum('valor_total'),
            'valor_recebido' => (clone $query)->where('status', 'pago')->sum('valor_pago'),
        ];
    }
}
