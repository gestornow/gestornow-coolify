<?php

namespace App\Domain\Storage\Services;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileService
{
    /**
     * Disco R2 privado configurado em config/filesystems.php.
     */
    private const DISCO = 'r2-private';

    /**
     * Prefixo esperado para arquivos privados (validação de escopo).
     */
    private const PREFIXO = 'privado';

    /**
     * Gera uma URL assinada temporária para leitura do arquivo.
     * A URL não deve ser persistida no banco de dados.
     *
     * @throws \RuntimeException quando o arquivo não existe ou não pertence à empresa.
     */
    public function urlTemporaria(string $path, int $idEmpresa, int $minutos = 60): string
    {
        $this->validarEscopo($path, $idEmpresa);
        $this->garantirExistencia($path);

        $expiracao = now()->addMinutes($minutos);

        return (string) Storage::disk(self::DISCO)->temporaryUrl($path, $expiracao);
    }

    /**
     * Retorna uma resposta de download (força download no browser).
     *
     * @throws \RuntimeException quando o arquivo não existe ou não pertence à empresa.
     */
    public function download(string $path, int $idEmpresa, ?string $nomeDownload = null): StreamedResponse
    {
        $this->validarEscopo($path, $idEmpresa);
        $this->garantirExistencia($path);

        $nomeDownload = $nomeDownload ?? basename($path);

        return Storage::disk(self::DISCO)->download($path, $nomeDownload);
    }

    /**
     * Retorna uma resposta de stream (exibe inline no browser, ex.: PDFs e imagens).
     *
     * @throws \RuntimeException quando o arquivo não existe ou não pertence à empresa.
     */
    public function stream(string $path, int $idEmpresa): StreamedResponse
    {
        $this->validarEscopo($path, $idEmpresa);
        $this->garantirExistencia($path);

        $mime = Storage::disk(self::DISCO)->mimeType($path) ?: 'application/octet-stream';

        $response = new StreamedResponse(function () use ($path) {
            $stream = Storage::disk(self::DISCO)->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', 'inline; filename="' . basename($path) . '"');
        $response->headers->set('Cache-Control', 'private, no-store, no-cache');

        return $response;
    }

    /**
     * Verifica se um arquivo existe no disco privado.
     */
    public function existe(string $path, int $idEmpresa): bool
    {
        $this->validarEscopo($path, $idEmpresa);

        return Storage::disk(self::DISCO)->exists($path);
    }

    /**
     * Valida que o path pertence à empresa informada, garantindo isolamento de escopo.
     *
     * @throws \RuntimeException
     */
    private function validarEscopo(string $path, int $idEmpresa): void
    {
        $prefixoEsperado = self::PREFIXO . '/empresa-' . $idEmpresa . '/';

        if (!str_starts_with($path, $prefixoEsperado)) {
            throw new \RuntimeException(
                'Acesso negado: o arquivo solicitado não pertence à empresa ' . $idEmpresa . '.'
            );
        }
    }

    /**
     * Lança exceção caso o arquivo não exista no disco privado.
     *
     * @throws \RuntimeException
     */
    private function garantirExistencia(string $path): void
    {
        if (!Storage::disk(self::DISCO)->exists($path)) {
            throw new \RuntimeException('Arquivo privado não encontrado: ' . $path);
        }
    }
}
