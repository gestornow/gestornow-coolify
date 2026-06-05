<?php

namespace App\Http\Controllers;

use App\Domain\Storage\Services\PrivateFileService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArquivoPrivadoController extends Controller
{
    public function __construct(private readonly PrivateFileService $privateFileService)
    {
    }

    /**
     * Faz o download de um arquivo privado do R2.
     *
     * Requer autenticação e que o arquivo pertença à empresa da sessão.
     *
     * @param  string  $path  Path/key do arquivo no formato base64 URL-safe
     */
    public function download(Request $request, string $path): StreamedResponse|JsonResponse
    {
        $idEmpresa = (int) session('id_empresa');

        if (!$idEmpresa) {
            return response()->json(['message' => 'Empresa não identificada na sessão.'], 403);
        }

        $pathDecodificado = $this->decodificarPath($path);

        if ($pathDecodificado === null) {
            return response()->json(['message' => 'Path de arquivo inválido.'], 400);
        }

        try {
            return $this->privateFileService->download($pathDecodificado, $idEmpresa);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'Acesso negado') ? 403 : 404;

            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Exibe um arquivo privado do R2 inline (stream).
     *
     * Requer autenticação e que o arquivo pertença à empresa da sessão.
     *
     * @param  string  $path  Path/key do arquivo no formato base64 URL-safe
     */
    public function stream(Request $request, string $path): StreamedResponse|JsonResponse
    {
        $idEmpresa = (int) session('id_empresa');

        if (!$idEmpresa) {
            return response()->json(['message' => 'Empresa não identificada na sessão.'], 403);
        }

        $pathDecodificado = $this->decodificarPath($path);

        if ($pathDecodificado === null) {
            return response()->json(['message' => 'Path de arquivo inválido.'], 400);
        }

        try {
            return $this->privateFileService->stream($pathDecodificado, $idEmpresa);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'Acesso negado') ? 403 : 404;

            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Gera e retorna uma URL temporária assinada para o arquivo privado.
     *
     * Requer autenticação e que o arquivo pertença à empresa da sessão.
     *
     * @param  string  $path  Path/key do arquivo no formato base64 URL-safe
     */
    public function urlTemporaria(Request $request, string $path): JsonResponse
    {
        $idEmpresa = (int) session('id_empresa');

        if (!$idEmpresa) {
            return response()->json(['message' => 'Empresa não identificada na sessão.'], 403);
        }

        $pathDecodificado = $this->decodificarPath($path);

        if ($pathDecodificado === null) {
            return response()->json(['message' => 'Path de arquivo inválido.'], 400);
        }

        $minutos = (int) $request->query('minutos', 60);
        $minutos = max(1, min($minutos, 1440)); // entre 1 minuto e 24 horas

        try {
            $url = $this->privateFileService->urlTemporaria($pathDecodificado, $idEmpresa, $minutos);

            return response()->json([
                'url'      => $url,
                'expira_em' => $minutos . ' minuto(s)',
            ]);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'Acesso negado') ? 403 : 404;

            return response()->json(['message' => $e->getMessage()], $status);
        }
    }

    /**
     * Decodifica o path recebido da URL (base64 URL-safe) para o path real.
     */
    private function decodificarPath(string $path): ?string
    {
        $decoded = base64_decode(strtr($path, '-_', '+/'), strict: true);

        if ($decoded === false || trim($decoded) === '') {
            return null;
        }

        return $decoded;
    }
}
