<?php

namespace App\Http\Controllers;

use App\Services\NotificacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificacaoController extends Controller
{
    public function __construct(private readonly NotificacaoService $notificacaoService)
    {
    }

    public function index(): JsonResponse
    {
        $usuario = Auth::user();

        $idUsuario = (int) (($usuario->id_usuario ?? null) ?: session('user_id'));
        $idEmpresa = (int) (session('id_empresa') ?? 0);

        if ($idUsuario <= 0) {
            return response()->json(['message' => 'Usuario nao autenticado.'], 401);
        }

        if ($idEmpresa <= 0) {
            return response()->json(['message' => 'Empresa da sessao nao encontrada.'], 422);
        }

        $notificacoes = $this->notificacaoService->listar($idUsuario, $idEmpresa, 20)
            ->map(static function ($n) {
                return [
                    'id' => (int) $n->id,
                    'titulo' => (string) $n->titulo,
                    'mensagem' => (string) $n->mensagem,
                    'tipo' => (string) $n->tipo,
                    'icone' => (string) $n->icone,
                    'cor' => (string) $n->cor,
                    'link' => $n->link,
                    'lida_em' => optional($n->lida_em)->toDateTimeString(),
                    'created_at' => optional($n->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json(['data' => $notificacoes]);
    }

    public function count(): JsonResponse
    {
        $usuario = Auth::user();

        $idUsuario = (int) (($usuario->id_usuario ?? null) ?: session('user_id'));
        $idEmpresa = (int) (session('id_empresa') ?? 0);

        if ($idUsuario <= 0) {
            return response()->json(['message' => 'Usuario nao autenticado.'], 401);
        }

        if ($idEmpresa <= 0) {
            return response()->json(['message' => 'Empresa da sessao nao encontrada.'], 422);
        }

        $total = $this->notificacaoService->countNaoLidas($idUsuario, $idEmpresa);

        return response()->json(['total' => $total]);
    }

    public function marcarLida(int $id): JsonResponse
    {
        $usuario = Auth::user();

        $idUsuario = (int) (($usuario->id_usuario ?? null) ?: session('user_id'));
        $idEmpresa = (int) (session('id_empresa') ?? 0);

        if ($idUsuario <= 0) {
            return response()->json(['message' => 'Usuario nao autenticado.'], 401);
        }

        if ($idEmpresa <= 0) {
            return response()->json(['message' => 'Empresa da sessao nao encontrada.'], 422);
        }

        // Escopo por usuario e empresa impede operar notificacoes de terceiros.
        $this->notificacaoService->marcarComoLida((int) $id, $idUsuario, $idEmpresa);

        return response()->json(['message' => 'Notificacao marcada como lida.']);
    }

    public function marcarTodasLidas(): JsonResponse
    {
        $usuario = Auth::user();

        $idUsuario = (int) (($usuario->id_usuario ?? null) ?: session('user_id'));
        $idEmpresa = (int) (session('id_empresa') ?? 0);

        if ($idUsuario <= 0) {
            return response()->json(['message' => 'Usuario nao autenticado.'], 401);
        }

        if ($idEmpresa <= 0) {
            return response()->json(['message' => 'Empresa da sessao nao encontrada.'], 422);
        }

        $this->notificacaoService->marcarTodasComoLidas($idUsuario, $idEmpresa);

        return response()->json(['message' => 'Todas as notificacoes foram marcadas como lidas.']);
    }

    public function apagar(int $id): JsonResponse
    {
        $usuario = Auth::user();

        $idUsuario = (int) (($usuario->id_usuario ?? null) ?: session('user_id'));
        $idEmpresa = (int) (session('id_empresa') ?? ($usuario->id_empresa ?? 0));

        if ($idUsuario <= 0) {
            return response()->json(['message' => 'Usuario nao autenticado.'], 401);
        }

        if ($idEmpresa <= 0) {
            return response()->json(['message' => 'Empresa da sessao nao encontrada.'], 422);
        }

        // Escopo por usuario e empresa impede apagar notificacoes de terceiros.
        $this->notificacaoService->excluir((int) $id, $idUsuario, $idEmpresa);

        return response()->json(['message' => 'Notificacao apagada com sucesso.']);
    }

    public function apagarTodas(): JsonResponse
    {
        $usuario = Auth::user();

        $idUsuario = (int) (($usuario->id_usuario ?? null) ?: session('user_id'));
        $idEmpresa = (int) (session('id_empresa') ?? ($usuario->id_empresa ?? 0));

        if ($idUsuario <= 0) {
            return response()->json(['message' => 'Usuario nao autenticado.'], 401);
        }

        if ($idEmpresa <= 0) {
            return response()->json(['message' => 'Empresa da sessao nao encontrada.'], 422);
        }

        $this->notificacaoService->excluirTodas($idUsuario, $idEmpresa);

        return response()->json(['message' => 'Todas as notificacoes foram apagadas.']);
    }
}