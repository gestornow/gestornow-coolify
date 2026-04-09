<?php

namespace App\Services;

use App\Models\Notificacao;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NotificacaoService
{
    public function criar(
        int $idUsuario,
        int $idEmpresa,
        string $titulo,
        string $mensagem,
        string $tipo,
        array $extra = []
    ): Notificacao {
        $id = DB::table('notificacoes')->insertGetId([
            'id_usuario' => $idUsuario,
            'id_empresa' => $idEmpresa,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'tipo' => $tipo,
            'icone' => $extra['icone'] ?? 'bell',
            'cor' => $extra['cor'] ?? 'warning',
            'link' => $extra['link'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Notificacao::query()->findOrFail($id);
    }

    public function jaNotificouHoje(int $idUsuario, int $idEmpresa, string $tipo): bool
    {
        $hoje = now()->toDateString();

        $resultado = DB::select(
            'SELECT COUNT(*) AS total FROM notificacoes
             WHERE id_usuario = :id_usuario
               AND id_empresa = :id_empresa
               AND tipo = :tipo
               AND DATE(created_at) = :hoje',
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
                'tipo' => $tipo,
                'hoje' => $hoje,
            ]
        );

        $total = (int) ($resultado[0]->total ?? 0);

        return $total > 0;
    }

    public function marcarComoLida(int $id, int $idUsuario, int $idEmpresa): void
    {
        DB::update(
            'UPDATE notificacoes SET lida_em = NOW(), updated_at = NOW()
             WHERE id = :id
               AND id_usuario = :id_usuario
               AND id_empresa = :id_empresa
               AND lida_em IS NULL',
            [
                'id' => $id,
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ]
        );
    }

    public function marcarTodasComoLidas(int $idUsuario, int $idEmpresa): void
    {
        DB::update(
            'UPDATE notificacoes SET lida_em = NOW(), updated_at = NOW()
             WHERE id_usuario = :id_usuario
               AND id_empresa = :id_empresa
               AND lida_em IS NULL',
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ]
        );
    }

    public function excluir(int $id, int $idUsuario, int $idEmpresa): void
    {
        DB::delete(
            'DELETE FROM notificacoes
             WHERE id = :id
               AND id_usuario = :id_usuario
               AND id_empresa = :id_empresa',
            [
                'id' => $id,
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ]
        );
    }

    public function excluirTodas(int $idUsuario, int $idEmpresa): void
    {
        DB::delete(
            'DELETE FROM notificacoes
             WHERE id_usuario = :id_usuario
               AND id_empresa = :id_empresa',
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ]
        );
    }

    public function countNaoLidas(int $idUsuario, int $idEmpresa): int
    {
        $resultado = DB::select(
            'SELECT COUNT(*) AS total FROM notificacoes
             WHERE id_usuario = :id_usuario
               AND id_empresa = :id_empresa
               AND lida_em IS NULL',
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ]
        );

        return (int) ($resultado[0]->total ?? 0);
    }

    public function listar(int $idUsuario, int $idEmpresa, int $limite = 20): Collection
    {
        $rows = DB::select(
            'SELECT id, titulo, mensagem, tipo, icone, cor, link, lida_em, created_at
             FROM notificacoes
             WHERE id_usuario = :id_usuario AND id_empresa = :id_empresa
             ORDER BY created_at DESC LIMIT ' . (int) $limite,
            [
                'id_usuario' => $idUsuario,
                'id_empresa' => $idEmpresa,
            ]
        );

        return Notificacao::hydrate(array_map(static fn ($row) => (array) $row, $rows));
    }
}