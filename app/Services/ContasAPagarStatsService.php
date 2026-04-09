<?php

namespace App\Services;

use App\Models\ContasAPagar;
use Illuminate\Support\Facades\DB;

class ContasAPagarStatsService
{
    /**
     * Calcular estatísticas das contas a pagar
     *
     * @param int $idEmpresa
     * @param string|null $mesFiltro Formato Y-m ou null para todos
     * @return array
     */
    public function getStats($idEmpresa, ?string $mesFiltro = null): array
    {
        $query = ContasAPagar::where('id_empresa', $idEmpresa);
        
        // Aplicar filtro de mês se fornecido
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $this->applyMonthFilter($query, $mesFiltro);
        }
        
        return [
            'total' => (clone $query)->count(),
            'pendentes' => (clone $query)->where('status', 'pendente')->count(),
            'pagas' => (clone $query)->where('status', 'pago')->count(),
            'vencidas' => (clone $query)->vencidas()->count(),
            'valor_total_pendente' => (clone $query)
                ->whereIn('status', ['pendente', 'vencido', 'parcelado'])
                ->sum('valor_total'),
            'valor_total_pago' => (clone $query)
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
        $query = DB::table('contas_a_pagar')
            ->select(
                'categoria_contas.nome as categoria',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(contas_a_pagar.valor_total) as valor_total')
            )
            ->leftJoin('categoria_contas', 'contas_a_pagar.id_categoria_contas', '=', 'categoria_contas.id_categoria_contas')
            ->where('contas_a_pagar.id_empresa', $idEmpresa);
        
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $year = substr($mesFiltro, 0, 4);
            $month = substr($mesFiltro, 5, 2);
            $query->whereYear('contas_a_pagar.data_vencimento', '=', $year)
                  ->whereMonth('contas_a_pagar.data_vencimento', '=', $month);
        }
        
        return $query->groupBy('categoria_contas.nome')
            ->orderByDesc('valor_total')
            ->get();
    }

    /**
     * Obter resumo por fornecedor
     *
     * @param int $idEmpresa
     * @param string|null $mesFiltro
     * @return \Illuminate\Support\Collection
     */
    public function getStatsByFornecedor(int $idEmpresa, ?string $mesFiltro = null)
    {
        $query = DB::table('contas_a_pagar')
            ->select(
                'fornecedores.nome as fornecedor',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(contas_a_pagar.valor_total) as valor_total')
            )
            ->leftJoin('fornecedores', 'contas_a_pagar.id_fornecedores', '=', 'fornecedores.id_fornecedores')
            ->where('contas_a_pagar.id_empresa', $idEmpresa);
        
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $year = substr($mesFiltro, 0, 4);
            $month = substr($mesFiltro, 5, 2);
            $query->whereYear('contas_a_pagar.data_vencimento', '=', $year)
                  ->whereMonth('contas_a_pagar.data_vencimento', '=', $month);
        }
        
        return $query->groupBy('fornecedores.nome')
            ->orderByDesc('valor_total')
            ->limit(10)
            ->get();
    }

    /**
     * Obter contas vencendo nos próximos dias
     *
     * @param int $idEmpresa
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getVencendoEmDias(int $idEmpresa, int $days = 7)
    {
        return ContasAPagar::where('id_empresa', $idEmpresa)
            ->whereIn('status', ['pendente', 'parcelado'])
            ->whereBetween('data_vencimento', [now(), now()->addDays($days)])
            ->orderBy('data_vencimento')
            ->get();
    }
}
