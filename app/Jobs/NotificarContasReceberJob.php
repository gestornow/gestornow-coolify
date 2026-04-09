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

class NotificarContasReceberJob implements ShouldQueue
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
            "SELECT cr.id_contas, cr.descricao, cr.valor_total,
                    cr.data_vencimento, cr.id_empresa,
                    u.id_usuario, u.nome AS nome_usuario
             FROM contas_a_receber cr
             INNER JOIN usuarios u ON u.id_usuario = cr.id_usuario AND u.id_empresa = cr.id_empresa
             WHERE cr.data_vencimento = :hoje
               AND cr.status = 'pendente'
               AND cr.deleted_at IS NULL",
            ['hoje' => $hoje]
        );

        foreach ($contas as $conta) {
            $this->criarNotificacaoConta(
                $notificacaoService,
                $conta,
                'conta_receber_vencendo',
                'warning',
                'Conta a receber vence hoje',
                'A conta "%s" vence hoje.'
            );
        }
    }

    private function notificarAtrasadas(NotificacaoService $notificacaoService): void
    {
        $hoje = now()->toDateString();

        $contas = DB::select(
            "SELECT cr.id_contas, cr.descricao, cr.valor_total,
                    cr.data_vencimento, cr.id_empresa,
                    u.id_usuario, u.nome AS nome_usuario
             FROM contas_a_receber cr
             INNER JOIN usuarios u ON u.id_usuario = cr.id_usuario AND u.id_empresa = cr.id_empresa
             WHERE cr.data_vencimento < :hoje
               AND cr.status = 'pendente'
               AND cr.deleted_at IS NULL",
            ['hoje' => $hoje]
        );

        foreach ($contas as $conta) {
            $this->criarNotificacaoConta(
                $notificacaoService,
                $conta,
                'conta_receber_atrasada',
                'danger',
                'Conta a receber em atraso',
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
                    'icone' => 'fa-hand-holding-dollar',
                    'cor' => $cor,
                    'link' => '/financeiro/contas-a-receber/' . (int) $conta->id_contas . '/edit',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Falha ao criar notificacao de conta a receber.', [
                'id_contas' => $conta->id_contas,
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
                'tipo' => $tipo,
                'erro' => $e->getMessage(),
            ]);
        }
    }
}