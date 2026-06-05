<?php

namespace App\Domain\Locacao\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LocacaoAssinaturaClienteStorageService
{
    public function store(string $conteudo, string $nomeArquivo, int $idEmpresa, int $idLocacao, string $mimeType): string
    {
        $extension = pathinfo($nomeArquivo, PATHINFO_EXTENSION);
        $baseName = pathinfo($nomeArquivo, PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName !== '' ? $baseName : 'assinatura-cliente');
        $safeFileName = $safeBaseName . '-' . now()->format('YmdHis');

        if ($extension !== '') {
            $safeFileName .= '.' . strtolower($extension);
        }

        $storedPath = 'privado/empresa-' . $idEmpresa . '/locacoes/' . $idLocacao . '/assinaturas-clientes/' . $safeFileName;

        $stored = Storage::disk('s3')->put($storedPath, $conteudo, [
            'ContentType' => $mimeType !== '' ? $mimeType : 'image/png',
        ]);

        if ($stored === false) {
            throw new RuntimeException('Falha ao persistir a assinatura do cliente no disco S3 configurado.');
        }

        Log::info('Assinatura do cliente enviada com sucesso para o disco S3 privado.', [
            'empresa_id' => $idEmpresa,
            'locacao_id' => $idLocacao,
            'path' => $storedPath,
        ]);

        return $storedPath;
    }

    public function readPrivateBinary(string $storedValue): ?array
    {
        $privatePath = self::extractPrivatePath($storedValue);

        if ($privatePath === null) {
            return null;
        }

        try {
            if (!Storage::disk('s3')->exists($privatePath)) {
                return null;
            }

            return [
                'content' => (string) Storage::disk('s3')->get($privatePath),
                'mime' => (string) (Storage::disk('s3')->mimeType($privatePath) ?: 'image/png'),
                'path' => $privatePath,
            ];
        } catch (\Throwable $e) {
            Log::warning('Falha ao ler assinatura privada do cliente no disco S3.', [
                'path' => $privatePath,
                'erro' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function resolveInlineSource(?string $storedValue): ?string
    {
        $storedValue = self::normalizeStoredValue($storedValue);

        if ($storedValue === '') {
            return null;
        }

        if (str_starts_with($storedValue, 'data:')) {
            return $storedValue;
        }

        $privatePath = self::extractPrivatePath($storedValue);
        if ($privatePath !== null) {
            try {
                if (Storage::disk('s3')->exists($privatePath)) {
                    return self::toDataUri(
                        (string) Storage::disk('s3')->get($privatePath),
                        (string) (Storage::disk('s3')->mimeType($privatePath) ?: 'image/png')
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao resolver assinatura privada do cliente como data URI.', [
                    'path' => $privatePath,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        $localPath = self::extractLocalPath($storedValue);
        if ($localPath !== null && is_file($localPath)) {
            $mime = mime_content_type($localPath) ?: 'image/png';
            $content = file_get_contents($localPath);

            if ($content !== false) {
                return self::toDataUri($content, $mime);
            }
        }

        if (str_starts_with($storedValue, 'http://') || str_starts_with($storedValue, 'https://')) {
            try {
                $response = Http::timeout(20)->get($storedValue);

                if ($response->successful()) {
                    return self::toDataUri(
                        (string) $response->body(),
                        (string) ($response->header('Content-Type') ?: 'image/png')
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Falha ao resolver assinatura remota do cliente como data URI.', [
                    'url' => $storedValue,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $storedValue;
    }

    public static function isPrivateReference(?string $storedValue): bool
    {
        return self::extractPrivatePath((string) $storedValue) !== null;
    }

    private static function normalizeStoredValue(?string $storedValue): string
    {
        return str_replace(['https//', 'http//'], ['https://', 'http://'], trim((string) $storedValue));
    }

    private static function extractPrivatePath(string $storedValue): ?string
    {
        $storedValue = self::normalizeStoredValue($storedValue);

        if ($storedValue === '') {
            return null;
        }

        $bucket = trim((string) config('filesystems.disks.s3.bucket', ''), '/');

        $candidates = [$storedValue];
        $parsedPath = parse_url($storedValue, PHP_URL_PATH);
        if (is_string($parsedPath) && $parsedPath !== '') {
            $candidates[] = trim($parsedPath, '/');
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate, '/');

            if ($candidate === '') {
                continue;
            }

            if ($bucket !== '' && str_starts_with($candidate, $bucket . '/')) {
                $candidate = substr($candidate, strlen($bucket) + 1);
            }

            if (str_starts_with($candidate, 'privado/')) {
                return $candidate;
            }
        }

        return null;
    }

    private static function extractLocalPath(string $storedValue): ?string
    {
        $parsedPath = parse_url($storedValue, PHP_URL_PATH);
        $path = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : $storedValue;

        $path = trim((string) $path);
        if ($path === '' || str_starts_with($path, 'data:')) {
            return null;
        }

        return public_path(ltrim($path, '/'));
    }

    private static function toDataUri(string $content, string $mimeType): string
    {
        return 'data:' . ($mimeType !== '' ? $mimeType : 'image/png') . ';base64,' . base64_encode($content);
    }
}