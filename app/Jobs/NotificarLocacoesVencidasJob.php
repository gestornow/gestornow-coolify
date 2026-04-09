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

class NotificarLocacoesVencidasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificacaoService $notificacaoService): void
    {
        $hoje = now()->toDateString();

        $locacoes = DB::select(
            "SELECT l.id_locacao, l.numero_contrato, l.data_fim, l.valor_final, l.id_empresa,
                    c.id_clientes, c.nome AS nome_cliente,
                    u.id_usuario, u.nome AS nome_usuario
             FROM locacao l
             INNER JOIN clientes c ON c.id_clientes = l.id_cliente AND c.id_empresa = l.id_empresa
             INNER JOIN usuarios u ON u.id_usuario = l.id_usuario AND u.id_empresa = l.id_empresa
             WHERE l.data_fim = :hoje
               AND l.status = 'aprovado'
               AND l.deleted_at IS NULL",
            ['hoje' => $hoje]
        );

        foreach ($locacoes as $locacao) {
            $idUsuario = (int) $locacao->id_usuario;
            $idEmpresa = (int) $locacao->id_empresa;
            $tipo = 'locacao_vencida';

            if ($notificacaoService->jaNotificouHoje($idUsuario, $idEmpresa, $tipo)) {
                continue;
            }

            $titulo = 'Locacao vence hoje';
            $mensagem = sprintf(
                'Contrato %s do cliente %s vence hoje.',
                (string) $locacao->numero_contrato,
                (string) $locacao->nome_cliente
            );

            try {
                $notificacaoService->criar(
                    $idUsuario,
                    $idEmpresa,
                    $titulo,
                    $mensagem,
                    $tipo,
                    [
                        'icone' => 'fa-calendar-xmark',
                        'cor' => 'danger',
                        'link' => '/locacoes/' . (int) $locacao->id_locacao,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Falha ao criar notificacao de locacao vencida.', [
                    'id_locacao' => $locacao->id_locacao,
                    'id_usuario' => $idUsuario,
                    'id_empresa' => $idEmpresa,
                    'erro' => $e->getMessage(),
                ]);
            }
        }
    }
}