<?php

namespace App\Helpers;

class PdfAssetHelper
{
    public static function resolveCompanyConfigImage($empresa, array|string $configKeys, bool $mapUploadsLogoToApi = false): ?string
    {
        if (!$empresa) {
            return null;
        }

        $configuracoes = $empresa->configuracoes ?? null;

        if (is_string($configuracoes)) {
            $decoded = json_decode($configuracoes, true);
            $configuracoes = is_array($decoded) ? $decoded : [];
        }

        $configuracoes = is_array($configuracoes) ? $configuracoes : [];
        $keys = is_array($configKeys) ? $configKeys : [$configKeys];

        $url = null;
        foreach ($keys as $key) {
            $candidate = $configuracoes[$key] ?? ($empresa->{$key} ?? null);
            if (!empty($candidate)) {
                $url = $candidate;
                break;
            }
        }

        return self::resolveImageSource($url, $mapUploadsLogoToApi);
    }

    public static function resolveImageSource(?string $url, bool $mapUploadsLogoToApi = false): ?string
    {
        $source = trim((string) ($url ?? ''));

        $source = str_replace(['https//', 'http//'], ['https://', 'http://'], $source);

        if ($source === '') {
            return null;
        }

        if (str_starts_with($source, 'data:image/')) {
            return $source;
        }

        $normalized = $source;

        if (!str_starts_with($normalized, 'http://') && !str_starts_with($normalized, 'https://')) {
            $normalized = '/' . ltrim($normalized, '/');

            if ($mapUploadsLogoToApi && str_contains($normalized, '/uploads/logos/imagens/')) {
                $apiBase = rtrim((string) config('services.gestornow_api.base_url', ''), '/');

                if ($apiBase === '') {
                    $apiBase = rtrim((string) config('custom.api_files_url', env('API_FILES_URL', '')), '/');
                }

                if ($apiBase === '') {
                    $apiBase = 'https://api.gestornow.com';
                }

                $normalized = $apiBase !== ''
                    ? $apiBase . $normalized
                    : asset(ltrim($normalized, '/'));
            } else {
                $normalized = asset(ltrim($normalized, '/'));
            }
        }

        $path = parse_url($normalized, PHP_URL_PATH);
        $localFile = $path ? public_path(ltrim($path, '/')) : null;

        if ($localFile && file_exists($localFile)) {
            return $localFile;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);

            $content = @file_get_contents($normalized, false, $context);

            if ($content !== false && !empty($content)) {
                $mimeType = 'image/png';
                $imageInfo = @getimagesizefromstring($content);

                if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
                    $mimeType = $imageInfo['mime'];
                }

                return 'data:' . $mimeType . ';base64,' . base64_encode($content);
            }
        }

        return $normalized;
    }
}
