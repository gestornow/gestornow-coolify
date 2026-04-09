<?php

namespace App\Domain\Cliente\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ClienteImageService
{
    private string $apiBaseUrl;
    private int $cacheMinutes = 30; // Cache por 30 minutos

    public function __construct()
    {
        // URL base da API de arquivos
        // Você pode configurar isso em config/custom.php ou .env
        $this->apiBaseUrl = $this->normalizeApiBaseUrl(
            (string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com'))
        );
    }

    /**
     * Busca as URLs das fotos de todos os clientes da API
     * Utiliza cache para otimizar performance
     *
     * @param int $idEmpresa ID da empresa
     * @param array|null $clienteIds Array de IDs de clientes para filtrar (opcional)
     * @return array Array associativo [id_cliente => foto_url]
     */
    public function getClientesPhotosUrls(int $idEmpresa, ?array $clienteIds = null): array
    {
        try {
            // Gerar chave de cache única
            $cacheKey = $this->generateCacheKey($idEmpresa, $clienteIds);
            
            // Tentar obter do cache primeiro
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                if ($this->isValidCachedPhotosMap($cached, $idEmpresa)) {
                    return $cached;
                }

                // Cache corrompido (ex: api.gestornow.comn). Remover e refazer.
                Cache::forget($cacheKey);
            }

            // Se não estiver em cache, buscar da API
            $photosMap = $this->fetchPhotosFromApi($idEmpresa, $clienteIds);
            
            // Armazenar em cache
            Cache::put($cacheKey, $photosMap, now()->addMinutes($this->cacheMinutes));
            
            Log::info('ClienteImageService: Fotos de clientes carregadas da API e cacheadas', [
                'id_empresa' => $idEmpresa,
                'total_clientes' => count($photosMap),
                'cache_minutes' => $this->cacheMinutes
            ]);

            return $photosMap;

        } catch (\Exception $e) {
            Log::error('ClienteImageService: Erro ao buscar fotos de clientes', [
                'id_empresa' => $idEmpresa,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Em caso de erro, retornar array vazio para não quebrar a aplicação
            return [];
        }
    }

    /**
     * Busca a URL da foto de um cliente específico
     *
     * @param int $idEmpresa ID da empresa
     * @param int $idCliente ID do cliente
     * @return string|null URL da foto ou null se não encontrada
     */
    public function getClientePhotoUrl(int $idEmpresa, int $idCliente): ?string
    {
        $photosMap = $this->getClientesPhotosUrls($idEmpresa, [$idCliente]);
        return $photosMap[$idCliente] ?? null;
    }

    /**
     * Invalida o cache de fotos dos clientes
     * Útil após upload, atualização ou exclusão de imagem
     *
     * @param int $idEmpresa ID da empresa
     * @param array|null $clienteIds IDs específicos para invalidar (null = todos)
     * @return void
     */
    public function invalidateCache(int $idEmpresa, ?array $clienteIds = null): void
    {
        try {
            if ($clienteIds === null) {
                // Invalidar todo o cache de fotos de clientes desta empresa
                Cache::forget("cliente_photos_empresa_{$idEmpresa}_all");
                Log::info('ClienteImageService: Cache completo de fotos invalidado', [
                    'id_empresa' => $idEmpresa
                ]);
            } else {
                // Invalidar cache específico
                $cacheKey = $this->generateCacheKey($idEmpresa, $clienteIds);
                Cache::forget($cacheKey);
                
                // Também invalidar o cache geral pois pode conter esses clientes
                Cache::forget("cliente_photos_empresa_{$idEmpresa}_all");
                
                Log::info('ClienteImageService: Cache de fotos invalidado', [
                    'id_empresa' => $idEmpresa,
                    'cliente_ids' => $clienteIds
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('ClienteImageService: Erro ao invalidar cache', [
                'id_empresa' => $idEmpresa,
                'erro' => $e->getMessage()
            ]);
        }
    }

    /**
     * Busca fotos da API de arquivos
     *
     * @param int $idEmpresa ID da empresa
     * @param array|null $clienteIds Array de IDs para filtrar
     * @return array [id_cliente => foto_url]
     */
    private function fetchPhotosFromApi(int $idEmpresa, ?array $clienteIds = null): array
    {
        $photosMap = [];

        try {
            // Endpoint para listar imagens de clientes da empresa
            $endpoint = "{$this->apiBaseUrl}/api/clientes/imagens/{$idEmpresa}";
            
            Log::debug('ClienteImageService: Fazendo requisição à API', [
                'endpoint' => $endpoint,
                'cliente_ids' => $clienteIds
            ]);

            // Fazer requisição HTTP
            $response = Http::timeout(10)->get($endpoint);

            if (!$response->successful()) {
                Log::warning('ClienteImageService: Resposta não bem-sucedida da API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            // Validar estrutura da resposta
            if (!isset($data['success']) || !$data['success'] || !isset($data['data']['files'])) {
                Log::warning('ClienteImageService: Estrutura de resposta inválida', [
                    'response' => $data
                ]);
                return [];
            }

            $files = $data['data']['files'];

            // Processar cada arquivo e extrair o id_cliente do nome do arquivo
            foreach ($files as $file) {
                $filename = $file['name'] ?? null;
                if (empty($filename)) {
                    continue;
                }

                $idCliente = $this->extractClienteIdFromFilename($filename);
                if ($idCliente === null) {
                    continue;
                }

                // Se temos um filtro de IDs, aplicar
                if ($clienteIds !== null && !in_array($idCliente, $clienteIds)) {
                    continue;
                }

                // Construir a URL (não confiar no campo `url`, que pode vir corrompido)
                $builtUrl = $this->buildClienteImageUrl($idEmpresa, $filename);
                if ($builtUrl === null) {
                    continue;
                }

                $photosMap[$idCliente] = $builtUrl;
            }

        } catch (\Exception $e) {
            Log::error('ClienteImageService: Erro ao buscar da API', [
                'erro' => $e->getMessage(),
                'endpoint' => $endpoint ?? 'unknown'
            ]);
        }

        return $photosMap;
    }

    /**
     * Extrai o ID do cliente do nome do arquivo
     * Formato esperado: clientes_{nome}_{idCliente}_{idEmpresa}_{uuid}.jpg
     *
     * @param string $filename
     * @return int|null
     */
    private function extractClienteIdFromFilename(string $filename): ?int
    {
        try {
            // Formato típico: clientes_image_{idCliente}_{idEmpresa}_{uuid}.jpg
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $parts = explode('_', $nameWithoutExt);

            // Pega sempre o "idCliente" como o 3º item a partir do fim (idCliente, idEmpresa, uuid)
            if (count($parts) >= 4 && $parts[0] === 'clientes') {
                $idCliente = (int) $parts[count($parts) - 3];
                return $idCliente > 0 ? $idCliente : null;
            }

        } catch (\Exception $e) {
            Log::warning('ClienteImageService: Erro ao extrair ID do cliente do filename', [
                'filename' => $filename,
                'erro' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function buildClienteImageUrl(int $idEmpresa, string $filename): ?string
    {
        $baseUrl = $this->apiBaseUrl;
        if (empty($baseUrl)) {
            return null;
        }

        $filename = trim($filename);
        if ($filename === '') {
            return null;
        }

        return rtrim($baseUrl, '/') . "/uploads/clientes/imagens/{$idEmpresa}/" . $filename;
    }

    private function normalizeApiBaseUrl(string $url): string
    {
        $url = trim($url);
        $url = rtrim($url, '/');

        // Corrigir typo comum que causa `ERR_NAME_NOT_RESOLVED`
        $url = str_replace('api.gestornow.comn', 'api.gestornow.com', $url);
        $url = str_replace('api.gestornow.comN', 'api.gestornow.com', $url);

        return $url;
    }

    private function isValidCachedPhotosMap(mixed $cached, int $idEmpresa): bool
    {
        if (!is_array($cached)) {
            return false;
        }

        foreach ($cached as $idCliente => $url) {
            if (!is_int($idCliente) && !ctype_digit((string) $idCliente)) {
                return false;
            }
            if (!is_string($url) || $url === '') {
                return false;
            }
            // Detectar domínio inválido
            if (stripos($url, 'api.gestornow.comn') !== false) {
                return false;
            }
            // Deve ser a URL construída padrão
            if (stripos($url, "/uploads/clientes/imagens/{$idEmpresa}/") === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gera chave de cache única baseada nos parâmetros
     *
     * @param int $idEmpresa ID da empresa
     * @param array|null $clienteIds Array de IDs de clientes
     * @return string
     */
    private function generateCacheKey(int $idEmpresa, ?array $clienteIds): string
    {
        if ($clienteIds === null || empty($clienteIds)) {
            return "cliente_photos_empresa_{$idEmpresa}_all";
        }

        sort($clienteIds);
        return "cliente_photos_empresa_{$idEmpresa}_" . md5(implode(',', $clienteIds));
    }
}
