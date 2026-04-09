<?php

namespace App\Services\ContasAReceber;

use App\Models\ContasAReceber;
use App\Models\PagamentoContaReceber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Illuminate\Pagination\LengthAwarePaginator;

class ContasAReceberService
{
    /**
     * Listar contas a receber com filtros e paginação
     */
    public function listar(
        int $idEmpresa,
        array $filtros = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = ContasAReceber::with(['cliente', 'fornecedor', 'categoria', 'formaPagamento', 'banco'])
            ->where('id_empresa', $idEmpresa);

        // Filtro por status
        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        // Filtro por cliente
        if (!empty($filtros['id_clientes'])) {
            $query->where('id_clientes', $filtros['id_clientes']);
        }

        // Filtro por fornecedor
        if (!empty($filtros['id_fornecedores'])) {
            $query->where('id_fornecedores', $filtros['id_fornecedores']);
        }

        // Filtro por categoria
        if (!empty($filtros['id_categoria_contas'])) {
            $query->where('id_categoria_contas', $filtros['id_categoria_contas']);
        }

        // Filtro por mês
        if (!empty($filtros['mes_filtro']) && $filtros['mes_filtro'] !== 'todos') {
            $data = explode('-', $filtros['mes_filtro']);
            if (count($data) === 2) {
                $ano = $data[0];
                $mes = $data[1];
                $query->whereYear('data_vencimento', $ano)
                      ->whereMonth('data_vencimento', $mes);
            }
        }

        // Filtro por período
        if (!empty($filtros['data_inicio']) && !empty($filtros['data_fim'])) {
            $query->whereBetween('data_vencimento', [
                $filtros['data_inicio'],
                $filtros['data_fim']
            ]);
        }

        // Filtro de busca por descrição ou documento
        if (!empty($filtros['busca'])) {
            $busca = $filtros['busca'];
            $query->where(function($q) use ($busca) {
                $q->where('descricao', 'like', "%{$busca}%")
                  ->orWhere('documento', 'like', "%{$busca}%");
            });
        }

        // Ordenação
        $orderBy = $filtros['order_by'] ?? 'data_vencimento';
        $orderDirection = $filtros['order_direction'] ?? 'asc';
        $query->orderBy($orderBy, $orderDirection);

        return $query->paginate($perPage);
    }

    /**
     * Criar uma nova conta a receber
     */
    public function criar(array $dados): ContasAReceber
    {
        DB::beginTransaction();

        try {
            // Definir valores padrão
            $dados['valor_pago'] = $dados['valor_pago'] ?? 0;
            $dados['juros'] = $dados['juros'] ?? 0;
            $dados['multa'] = $dados['multa'] ?? 0;
            $dados['desconto'] = $dados['desconto'] ?? 0;
            $dados['status'] = $dados['status'] ?? 'pendente';

            $conta = ContasAReceber::create($dados);

            // Se a conta já foi paga na criação, registrar no fluxo de caixa
            if ($conta->status === 'pago' && $conta->data_pagamento) {
                DB::table('fluxo_caixa')->insert([
                    'id_empresa' => $conta->id_empresa,
                    'tipo' => 'entrada',
                    'descricao' => "Recebimento: {$conta->descricao}",
                    'valor' => $conta->valor_pago ?: $conta->valor_total,
                    'data_movimentacao' => $conta->data_pagamento,
                    'id_conta_receber' => $conta->id_contas,
                    'id_bancos' => $conta->id_bancos,
                    'id_categoria_fluxo' => $conta->id_categoria_contas,
                    'id_forma_pagamento' => $conta->id_forma_pagamento,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();
            return $conta;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar conta a receber: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Atualizar uma conta a receber existente
     */
    public function atualizar(ContasAReceber $conta, array $dados): ContasAReceber
    {
        DB::beginTransaction();

        try {
            // Não permitir atualizar se tiver pagamentos registrados
            if ($conta->valor_pago > 0 && $conta->pagamentos()->count() > 0) {
                throw new InvalidArgumentException(
                    'Não é possível editar uma conta que já possui pagamentos registrados. Exclua os pagamentos primeiro.'
                );
            }

            $conta->update($dados);

            DB::commit();
            return $conta->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar conta a receber: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Excluir uma conta a receber
     */
    public function excluir(ContasAReceber $conta): void
    {
        DB::beginTransaction();

        try {
            // Verificar se há pagamentos registrados
            if ($conta->pagamentos()->count() > 0) {
                throw new InvalidArgumentException(
                    'Não é possível excluir uma conta que possui pagamentos registrados. Exclua os pagamentos primeiro.'
                );
            }

            // Deletar entrada no fluxo de caixa se existir
            DB::table('fluxo_caixa')
                ->where('id_conta_receber', $conta->id_contas)
                ->delete();

            $conta->delete();

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir conta a receber: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar uma conta por ID
     */
    public function buscarPorId(int $id, int $idEmpresa): ?ContasAReceber
    {
        return ContasAReceber::with([
            'cliente',
            'fornecedor',
            'categoria',
            'formaPagamento',
            'banco',
            'pagamentos'
        ])
        ->where('id_contas', $id)
        ->where('id_empresa', $idEmpresa)
        ->first();
    }

    /**
     * Obter estatísticas das contas a receber
     */
    public function obterEstatisticas(int $idEmpresa, ?string $mesFiltro = null): array
    {
        $query = ContasAReceber::where('id_empresa', $idEmpresa);

        // Aplicar filtro de mês se fornecido
        if ($mesFiltro && $mesFiltro !== 'todos') {
            $data = explode('-', $mesFiltro);
            if (count($data) === 2) {
                $ano = $data[0];
                $mes = $data[1];
                $query->whereYear('data_vencimento', $ano)
                      ->whereMonth('data_vencimento', $mes);
            }
        }

        $totalReceber = (clone $query)->where('status', '!=', 'pago')->sum('valor_total');
        $totalRecebido = (clone $query)->where('status', 'pago')->sum('valor_total');
        $totalPendente = (clone $query)->where('status', 'pendente')->sum('valor_total');
        $totalVencido = (clone $query)->where('status', 'pendente')
            ->where('data_vencimento', '<', now()->toDateString())
            ->sum('valor_total');
        $totalAVencer = (clone $query)->where('status', 'pendente')
            ->where('data_vencimento', '>=', now()->toDateString())
            ->sum('valor_total');

        $countTotal = (clone $query)->count();
        $countRecebido = (clone $query)->where('status', 'pago')->count();
        $countPendente = (clone $query)->where('status', 'pendente')->count();
        $countVencido = (clone $query)->where('status', 'pendente')
            ->where('data_vencimento', '<', now()->toDateString())
            ->count();

        return [
            'total_receber' => $totalReceber,
            'total_recebido' => $totalRecebido,
            'total_pendente' => $totalPendente,
            'total_vencido' => $totalVencido,
            'total_a_vencer' => $totalAVencer,
            'count_total' => $countTotal,
            'count_recebido' => $countRecebido,
            'count_pendente' => $countPendente,
            'count_vencido' => $countVencido,
            'percentual_recebido' => $countTotal > 0 ? round(($countRecebido / $countTotal) * 100, 2) : 0,
        ];
    }

    /**
     * Marcar conta como cancelada
     */
    public function cancelar(ContasAReceber $conta, ?string $motivo = null): void
    {
        DB::beginTransaction();

        try {
            // Não permitir cancelar se tiver pagamentos
            if ($conta->valor_pago > 0) {
                throw new InvalidArgumentException('Não é possível cancelar uma conta que já possui pagamentos registrados.');
            }

            $conta->update([
                'status' => 'cancelado',
                'observacoes' => $conta->observacoes . ($motivo ? "\n\nCancelado: {$motivo}" : '')
            ]);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao cancelar conta a receber: ' . $e->getMessage());
            throw $e;
        }
    }
    /**
     * Registrar um recebimento (parcial ou total) em uma conta
     */
    public function registrarRecebimento(
        ContasAReceber $conta,
        float $valorRecebido,
        ?int $idFormaPagamento,
        ?int $idBanco,
        string $dataRecebimento,
        ?string $observacoes = null
    ): PagamentoContaReceber {
        // Validar valor do recebimento
        $valorRestante = $conta->valor_total - $conta->valor_pago;
        
        if ($valorRecebido <= 0) {
            throw new InvalidArgumentException('O valor do recebimento deve ser maior que zero.');
        }
        
        if ($valorRecebido > $valorRestante) {
            throw new InvalidArgumentException('O valor do recebimento não pode ser maior que o valor restante da conta.');
        }

        DB::beginTransaction();

        try {
            // Criar entrada no fluxo de caixa
            $idFluxoCaixa = DB::table('fluxo_caixa')->insertGetId([
                'id_empresa' => $conta->id_empresa,
                'tipo' => 'entrada',
                'descricao' => "Recebimento: {$conta->descricao}",
                'valor' => $valorRecebido,
                'data_movimentacao' => $dataRecebimento,
                'id_conta_receber' => $conta->id_contas,
                'id_forma_pagamento' => $idFormaPagamento,
                'id_bancos' => $idBanco,
                'id_categoria_fluxo' => $conta->id_categoria_contas,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Criar registro do recebimento
            $recebimento = PagamentoContaReceber::create([
                'id_conta_receber' => $conta->id_contas,
                'id_empresa' => $conta->id_empresa,
                'data_pagamento' => $dataRecebimento,
                'valor_pago' => $valorRecebido,
                'id_forma_pagamento' => $idFormaPagamento,
                'id_bancos' => $idBanco,
                'observacoes' => $observacoes,
                'id_usuario' => Auth::id(),
                'id_fluxo_caixa' => $idFluxoCaixa,
            ]);

            // Atualizar conta
            $novoValorPago = $conta->valor_pago + $valorRecebido;
            $conta->update([
                'valor_pago' => $novoValorPago,
                'status' => $novoValorPago >= $conta->valor_total ? 'pago' : 'pendente',
                'data_pagamento' => $novoValorPago >= $conta->valor_total ? $dataRecebimento : null,
            ]);

            DB::commit();

            return $recebimento;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao registrar recebimento: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Excluir um recebimento e recalcular a conta
     */
    public function excluirRecebimento(PagamentoContaReceber $recebimento): void
    {
        DB::beginTransaction();

        try {
            $conta = $recebimento->conta;
            $valorRecebimento = $recebimento->valor_pago;
            $idFluxoCaixa = $recebimento->id_fluxo_caixa ?? null;

            // Deletar o recebimento
            $recebimento->delete();

            // Atualizar o valor_pago da conta
            $novoValorPago = max(0, $conta->valor_pago - $valorRecebimento);
            
            $conta->update([
                'valor_pago' => $novoValorPago,
                'status' => $novoValorPago == 0 ? 'pendente' : ($novoValorPago < $conta->valor_total ? 'pendente' : 'pago'),
                'data_pagamento' => $novoValorPago < $conta->valor_total ? null : $conta->data_pagamento,
            ]);

            // Deletar entrada no fluxo de caixa
            if ($idFluxoCaixa) {
                DB::table('fluxo_caixa')->where('id_fluxo', $idFluxoCaixa)->delete();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir recebimento: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar histórico de recebimentos de uma conta
     */
    public function buscarHistoricoRecebimentos(ContasAReceber $conta): array
    {
        $recebimentos = $conta->pagamentos()
            ->with(['formaPagamento', 'banco', 'usuario'])
            ->get()
            ->map(fn($recebimento) => [
                'id' => $recebimento->id_pagamento,
                'data_pagamento' => $recebimento->data_pagamento->format('d/m/Y'),
                'valor_pago' => $recebimento->valor_pago,
                'forma_pagamento' => $recebimento->formaPagamento->nome ?? '-',
                'banco' => $recebimento->banco->nome_banco ?? '-',
                'observacoes' => $recebimento->observacoes,
                'usuario' => $recebimento->usuario->name ?? '-',
                'created_at' => $recebimento->created_at->format('d/m/Y H:i'),
            ]);

        return [
            'id_conta' => $conta->id_contas,
            'recebimentos' => $recebimentos,
            'total_pago' => $conta->valor_pago,
            'valor_total' => $conta->valor_total,
            'valor_restante' => $conta->valorRestante,
        ];
    }

    /**
     * Verificar se usuário tem permissão para acessar a conta
     */
    public function pertenceAEmpresaDoUsuario(ContasAReceber $conta, int $idEmpresa): bool
    {
        return $conta->id_empresa === $idEmpresa;
    }
}
