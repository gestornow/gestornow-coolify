<?php

namespace App\Jobs;

use App\Services\NotificacaoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificarContasPagarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificacaoService $notificacaoService): void
    {
        $this->notificarVencendoHoje($notificacaoService);
        $this->notificarAtrasadas($notificacaoService);
    }

    private function notificarVencendoHoje(NotificacaoService $notificacaoService): void
    {
        $hoje = now()->toDateString();

        $contas = DB::select(
            "SELECT cp.id_contas, cp.descricao, cp.valor_total,
                    cp.data_vencimento, cp.id_empresa,
                    u.id_usuario, u.nome AS nome_usuario
             FROM contas_a_pagar cp
             INNER JOIN usuarios u ON u.id_usuario = cp.id_usuario AND u.id_empresa = cp.id_empresa
             WHERE cp.data_vencimento = :hoje
               AND cp.status = 'pendente'
               AND cp.deleted_at IS NULL",
            ['hoje' => $hoje]
        );

        foreach ($contas as $conta) {
            $this->criarNotificacaoConta(
                $notificacaoService,
                $conta,
                'conta_pagar_vencendo',
                'warning',
                'Conta a pagar vence hoje',
                'A conta "%s" vence hoje.'
            );
        }
    }

    private function notificarAtrasadas(NotificacaoService $notificacaoService): void
    {
        $hoje = now()->toDateString();

        $contas = DB::select(
            "SELECT cp.id_contas, cp.descricao, cp.valor_total,
                    cp.data_vencimento, cp.id_empresa,
                    u.id_usuario, u.nome AS nome_usuario
             FROM contas_a_pagar cp
             INNER JOIN usuarios u ON u.id_usuario = cp.id_usuario AND u.id_empresa = cp.id_empresa
             WHERE cp.data_vencimento < :hoje
               AND cp.status = 'pendente'
               AND cp.deleted_at IS NULL",
            ['hoje' => $hoje]
        );

        foreach ($contas as $conta) {
            $this->criarNotificacaoConta(
                $notificacaoService,
                $conta,
                'conta_pagar_atrasada',
                'danger',
                'Conta a pagar em atraso',
                'A conta "%s" esta em atraso.'
            );
        }
    }

    private function criarNotificacaoConta(
        NotificacaoService $notificacaoService,
        object $conta,
        string $tipo,
        string $cor,
        string $titulo,
        string $mensagemTemplate
    ): void {
        $idUsuario = (int) $conta->id_usuario;
        $idEmpresa = (int) $conta->id_empresa;

        if ($notificacaoService->jaNotificouHoje($idUsuario, $idEmpresa, $tipo)) {
            return;
        }

        try {
            $notificacaoService->criar(
                $idUsuario,
                $idEmpresa,
                $titulo,
                sprintf($mensagemTemplate, (string) $conta->descricao),
                $tipo,
                [
                    'icone' => 'fa-file-invoice-dollar',
                    'cor' => $cor,
                    'link' => '/financeiro/contas-a-pagar/' . (int) $conta->id_contas . '/edit',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Falha ao criar notificacao de conta a pagar.', [
                'id_contas' => $conta->id_contas,
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
                'tipo' => $tipo,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}