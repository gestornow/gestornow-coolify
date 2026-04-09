<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FilterableContas
{
    /**
     * Aplicar filtros na query de contas
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    protected function applyFilters(Builder $query, Request $request): Builder
    {
        // Filtro de mês
        if ($request->filled('mes') && $request->mes !== 'todos') {
            $this->filterByMonth($query, $request->mes);
        }

        // Filtro de status com lógica especial
        if ($request->filled('status')) {
            $status = $request->status;
            
            // Status "vencido" - não é um status armazenado, mas calculado
            if ($status === 'vencido') {
                $query->where('status', '!=', 'pago')
                      ->where('status', '!=', 'cancelado')
                      ->where('data_vencimento', '<', now()->toDateString());
            } 
            // Status "parcelado" - filtrar por contas que têm id_parcelamento
            elseif ($status === 'parcelado') {
                $query->whereNotNull('id_parcelamento');
            }
            // Outros status - filtro direto
            else {
                $query->where('status', $status);
            }
        }

        // Filtro de fornecedor (contas a pagar)
        if ($request->filled('id_fornecedores')) {
            $query->where('id_fornecedores', $request->id_fornecedores);
        }

        // Filtro de cliente (contas a receber)
        if ($request->filled('id_cliente')) {
            $query->where('id_cliente', $request->id_cliente);
        }

        // Filtro de categoria
        if ($request->filled('id_categoria_contas')) {
            $query->where('id_categoria_contas', $request->id_categoria_contas);
        }

        // Filtro de banco
        if ($request->filled('id_bancos')) {
            $query->where('id_bancos', $request->id_bancos);
        }

        // Filtro de forma de pagamento
        if ($request->filled('id_forma_pagamento')) {
            $query->where('id_forma_pagamento', $request->id_forma_pagamento);
        }

        // Filtro de data inicial
        if ($request->filled('data_inicio')) {
            $query->whereDate('data_vencimento', '>=', $request->data_inicio);
        }

        // Filtro de data final
        if ($request->filled('data_fim')) {
            $query->whereDate('data_vencimento', '<=', $request->data_fim);
        }

        // Busca por texto
        if ($request->filled('busca')) {
            $this->filterBySearch($query, $request->busca);
        }

        // Filtro de tipo de lançamento
        if ($request->filled('tipo_lancamento')) {
            $query->where('tipo_lancamento', $request->tipo_lancamento);
        }

        // Filtro de contas parceladas
        if ($request->filled('is_parcelado')) {
            if ($request->is_parcelado == '1') {
                $query->whereNotNull('id_parcelamento');
            } else {
                $query->whereNull('id_parcelamento');
            }
        }

        // Filtro de contas recorrentes
        if ($request->filled('is_recorrente')) {
            $query->where('is_recorrente', $request->is_recorrente);
        }

        return $query;
    }

    /**
     * Filtrar por mês específico
     *
     * @param Builder $query
     * @param string $mesFiltro Formato Y-m
     * @return void
     */
    protected function filterByMonth(Builder $query, string $mesFiltro): void
    {
        $year = substr($mesFiltro, 0, 4);
        $month = substr($mesFiltro, 5, 2);

        $query->whereYear('data_vencimento', '=', $year)
              ->whereMonth('data_vencimento', '=', $month);
    }

    /**
     * Filtrar por busca de texto
     *
     * @param Builder $query
     * @param string $search
     * @return void
     */
    protected function filterBySearch(Builder $query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('descricao', 'like', '%' . $search . '%')
              ->orWhere('observacoes', 'like', '%' . $search . '%')
              ->orWhere('documento', 'like', '%' . $search . '%');
        });
    }

    /**
     * Obter parâmetros de ordenação
     *
     * @param Request $request
     * @return array ['column' => string, 'direction' => string]
     */
    protected function getOrderParams(Request $request): array
    {
        $allowedColumns = [
            'data_vencimento',
            'data_emissao',
            'data_pagamento',
            'valor_total',
            'valor_pago',
            'descricao',
            'status',
            'created_at'
        ];

        $orderBy = $request->get('order_by', 'data_vencimento');
        $orderDirection = $request->get('order_direction', 'asc');

        // Validar coluna
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'data_vencimento';
        }

        // Validar direção
        if (!in_array(strtolower($orderDirection), ['asc', 'desc'])) {
            $orderDirection = 'asc';
        }

        return [
            'column' => $orderBy,
            'direction' => $orderDirection
        ];
    }

    /**
     * Aplicar paginação
     *
     * @param Builder $query
     * @param Request $request
     * @param int $defaultPerPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function applyPagination(Builder $query, Request $request, int $defaultPerPage = 15)
    {
        $perPage = $request->get('per_page', $defaultPerPage);

        // Limitar entre 10 e 100
        $perPage = max(10, min(100, (int)$perPage));

        return $query->paginate($perPage);
    }
}
