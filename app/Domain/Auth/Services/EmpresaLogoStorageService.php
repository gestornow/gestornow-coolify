<?php

namespace App\Domain\Auth\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class EmpresaLogoStorageService
{
    public function store(UploadedFile $logo, int $idEmpresa): string
    {
        $extension = $logo->getClientOriginalExtension() ?: $logo->extension() ?: 'png';
        $fileName = 'logo_empresa_' . $idEmpresa . '_' . time() . '.' . $extension;
        $directory = 'logos-empresa/' . $idEmpresa;

        $storedPath = Storage::disk('s3')->putFileAs(
            $directory,
            $logo,
            $fileName,
            [
                'ContentType' => (string) ($logo->getMimeType() ?: 'application/octet-stream'),
            ]
        );

        if ($storedPath === false) {
            throw new RuntimeException('Falha ao persistir a logo no disco S3 configurado.');
        }

        $url = $this->buildPublicUrl($storedPath);

        if (!is_string($url) || trim($url) === '') {
            throw new RuntimeException('Falha ao gerar URL publica para a logo enviada.');
        }

        Log::info('Logo enviada com sucesso para o disco S3.', [
            'empresa_id' => $idEmpresa,
            'path' => $storedPath,
            'url' => $url,
        ]);

        return $url;
    }

    public static function publicUrlFromPath(string $path): string
    {
        $normalizedPath = ltrim($path, '/');

        if ($normalizedPath === '') {
            return '';
        }

        $diskConfig = (array) config('filesystems.disks.s3', []);
        $baseUrl = trim((string) Arr::get($diskConfig, 'url', ''));
        $bucket = trim((string) Arr::get($diskConfig, 'bucket', ''));

        if ($baseUrl !== '') {
            $baseUrl = rtrim($baseUrl, '/');

            if ($bucket !== '' && !str_ends_with($baseUrl, '/' . $bucket)) {
                return $baseUrl . '/' . $bucket . '/' . $normalizedPath;
            }

            return $baseUrl . '/' . $normalizedPath;
        }

        return (string) Storage::disk('s3')->url($normalizedPath);
    }

    private function buildPublicUrl(string $storedPath): string
    {
        return self::publicUrlFromPath($storedPath);
    }
}