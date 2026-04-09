<?php

namespace App\Http\Traits;

use App\Services\LimiteService;
use Illuminate\Http\JsonResponse;

trait VerificaLimite
{
    /**
     * Verifica se pode cadastrar cliente
     * 
     * @return JsonResponse|null Retorna JsonResponse se atingiu limite, null se pode continuar
     */
    protected function verificarLimiteCliente(): ?JsonResponse
    {
        $idEmpresa = session('id_empresa');
        $resultado = LimiteService::podecadastrarCliente($idEmpresa);

        if (!$resultado['pode']) {
            return $this->respostaLimiteAtingido($resultado);
        }

        return null;
    }

    /**
     * Verifica se pode cadastrar produto
     */
    protected function verificarLimiteProduto(): ?JsonResponse
    {
        $idEmpresa = session('id_empresa');
        $resultado = LimiteService::podecadastrarProduto($idEmpresa);

        if (!$resultado['pode']) {
            return $this->respostaLimiteAtingido($resultado);
        }

        return null;
    }

    /**
     * Verifica se pode cadastrar modelo de contrato
     */
    protected function verificarLimiteModeloContrato(): ?JsonResponse
    {
        $idEmpresa = session('id_empresa');
        $resultado = LimiteService::podecadastrarModeloContrato($idEmpresa);

        if (!$resultado['pode']) {
            return $this->respostaLimiteAtingido($resultado);
        }

        return null;
    }

    /**
     * Verifica se pode cadastrar usuário
     */
    protected function verificarLimiteUsuario(): ?JsonResponse
    {
        $idEmpresa = session('id_empresa');
        $resultado = LimiteService::podecadastrarUsuario($idEmpresa);

        if (!$resultado['pode']) {
            return $this->respostaLimiteAtingido($resultado);
        }

        return null;
    }

    /**
     * Verifica se pode marcar banco como gera boleto
     */
    protected function verificarLimiteBancoBoleto(): ?JsonResponse
    {
        $idEmpresa = session('id_empresa');
        $resultado = LimiteService::podeMarcarBancoGeraBoleto($idEmpresa);

        if (!$resultado['pode']) {
            return $this->respostaLimiteAtingido($resultado);
        }

        return null;
    }

    /**
     * Retorna resposta padronizada quando limite é atingido
     */
    protected function respostaLimiteAtingido(array $resultado): JsonResponse
    {
        return response()->json([
            'success' => false,
            'limite_atingido' => true,
            'message' => $resultado['mensagem'],
            'limite' => $resultado['limite'],
            'atual' => $resultado['atual'],
            'restante' => $resultado['restante'] ?? 0,
        ], 422);
    }

    /**
     * Verifica limite genérico e retorna resultado
     */
    protected function verificarLimite(string $tipo): ?JsonResponse
    {
        $idEmpresa = session('id_empresa');
        
        $resultado = match ($tipo) {
            'cliente' => LimiteService::podecadastrarCliente($idEmpresa),
            'produto' => LimiteService::podecadastrarProduto($idEmpresa),
            'modelo_contrato' => LimiteService::podecadastrarModeloContrato($idEmpresa),
            'usuario' => LimiteService::podecadastrarUsuario($idEmpresa),
            'banco_boleto' => LimiteService::podeMarcarBancoGeraBoleto($idEmpresa),
            default => ['pode' => true],
        };

        if (!$resultado['pode']) {
            return $this->respostaLimiteAtingido($resultado);
        }

        return null;
    }
}
