<?php

namespace App\Services\ContasAPagar;

use App\Models\ContasAPagar;
use App\Models\PagamentoContaPagar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ContasAPagarService
{
    /**
     * Registrar um pagamento (parcial ou total) em uma conta
     */
    public function registrarPagamento(
        ContasAPagar $conta,
        float $valorPago,
        ?int $idFormaPagamento,
        ?int $idBanco,
        string $dataPagamento,
        ?string $observacoes = null
    ): PagamentoContaPagar {
        // Validar valor do pagamento
        $valorRestante = $conta->valor_total - $conta->valor_pago;
        
        if ($valorPago <= 0) {
            throw new InvalidArgumentException('O valor do pagamento deve ser maior que zero.');
        }
        
        if ($valorPago > $valorRestante) {
            throw new InvalidArgumentException('O valor do pagamento não pode ser maior que o valor restante da conta.');
        }

        DB::beginTransaction();

        try {
            // Criar entrada no fluxo de caixa
            $idFluxoCaixa = DB::table('fluxo_caixa')->insertGetId([
                'id_empresa' => $conta->id_empresa,
                'tipo' => 'saida',
                'descricao' => "Pagamento: {$conta->descricao}",
                'valor' => $valorPago,
                'data_movimentacao' => $dataPagamento,
                'id_conta_pagar' => $conta->id_contas,
                'id_forma_pagamento' => $idFormaPagamento,
                'id_bancos' => $idBanco,
                'id_categoria_fluxo' => $conta->id_categoria_contas,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Criar registro do pagamento
            $pagamento = PagamentoContaPagar::create([
                'id_conta_pagar' => $conta->id_contas,
                'id_empresa' => $conta->id_empresa,
                'data_pagamento' => $dataPagamento,
                'valor_pago' => $valorPago,
                'id_forma_pagamento' => $idFormaPagamento,
                'id_bancos' => $idBanco,
                'observacoes' => $observacoes,
                'id_usuario' => Auth::id(),
                'id_fluxo_caixa' => $idFluxoCaixa,
            ]);

            // Atualizar conta
            $novoValorPago = $conta->valor_pago + $valorPago;
            $conta->update([
                'valor_pago' => $novoValorPago,
                'status' => $novoValorPago >= $conta->valor_total ? 'pago' : 'pendente',
                'data_pagamento' => $novoValorPago >= $conta->valor_total ? $dataPagamento : null,
            ]);

            DB::commit();

            return $pagamento;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao registrar pagamento: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Excluir um pagamento e recalcular a conta
     */
    public function excluirPagamento(PagamentoContaPagar $pagamento): void
    {
        DB::beginTransaction();

        try {
            $conta = $pagamento->conta;
            $valorPagamento = $pagamento->valor_pago;
            $idFluxoCaixa = $pagamento->id_fluxo_caixa;

            // Deletar o pagamento
            $pagamento->delete();

            // Atualizar o valor_pago da conta
            $novoValorPago = max(0, $conta->valor_pago - $valorPagamento);
            
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
            Log::error('Erro ao excluir pagamento: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar histórico de pagamentos de uma conta
     */
    public function buscarHistoricoPagamentos(ContasAPagar $conta): array
    {
        $pagamentos = $conta->pagamentos()
            ->with(['formaPagamento', 'banco', 'usuario'])
            ->get()
            ->map(fn($pagamento) => [
                'id' => $pagamento->id_pagamento,
                'data_pagamento' => $pagamento->data_pagamento->format('d/m/Y'),
                'valor_pago' => $pagamento->valor_pago,
                'forma_pagamento' => $pagamento->formaPagamento->nome ?? '-',
                'banco' => $pagamento->banco->nome_banco ?? '-',
                'observacoes' => $pagamento->observacoes,
                'usuario' => $pagamento->usuario->name ?? '-',
                'created_at' => $pagamento->created_at->format('d/m/Y H:i'),
            ]);

        return [
            'id_conta' => $conta->id_contas,
            'pagamentos' => $pagamentos,
            'total_pago' => $conta->valor_pago,
            'valor_total' => $conta->valor_total,
            'valor_restante' => $conta->valorRestante,
        ];
    }

    /**
     * Verificar se usuário tem permissão para acessar a conta
     */
    public function pertenceAEmpresaDoUsuario(ContasAPagar $conta, int $idEmpresa): bool
    {
        return $conta->id_empresa === $idEmpresa;
    }
}
