<?php

namespace App\Domain\User\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UserImageService
{
    private string $apiBaseUrl;
    private int $cacheMinutes = 30; // Cache por 30 minutos

    public function __construct()
    {
        // URL base da API de arquivos
        // Você pode configurar isso em config/custom.php ou .env
        $this->apiBaseUrl = config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com'));
    }

    /**
     * Busca as URLs das fotos de todos os usuários da API
     * Utiliza cache para otimizar performance
     *
     * @param array|null $userIds Array de IDs de usuários para filtrar (opcional)
     * @return array Array associativo [id_usuario => foto_url]
     */
    public function getUsersPhotosUrls(?array $userIds = null): array
    {
        try {
            // Usar sempre a mesma chave de cache para simplificar invalidação
            $cacheKey = 'user_photos_all';
            
            // Tentar obter do cache primeiro
            $allPhotos = Cache::get($cacheKey);
            
            if ($allPhotos === null) {
                // Se não estiver em cache, buscar da API (todas as fotos)
                $allPhotos = $this->fetchPhotosFromApi(null);
                
                // Armazenar em cache
                Cache::put($cacheKey, $allPhotos, now()->addMinutes($this->cacheMinutes));
                
                Log::info('UserImageService: Fotos de usuários carregadas da API e cacheadas', [
                    'total_users' => count($allPhotos),
                    'cache_minutes' => $this->cacheMinutes
                ]);
            } else {
                Log::debug('UserImageService: Usando cache para fotos de usuários', [
                    'cache_key' => $cacheKey,
                    'total_users' => count($allPhotos)
                ]);
            }

            // Se IDs específicos foram solicitados, filtrar o resultado
            if ($userIds !== null && !empty($userIds)) {
                return array_intersect_key($allPhotos, array_flip($userIds));
            }

            return $allPhotos;

        } catch (\Exception $e) {
            Log::error('UserImageService: Erro ao buscar fotos de usuários', [
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Em caso de erro, retornar array vazio para não quebrar a aplicação
            return [];
        }
    }

    /**
     * Busca a URL da foto de um usuário específico
     *
     * @param int $idUsuario
     * @return string|null URL da foto ou null se não encontrada
     */
    public function getUserPhotoUrl(int $idUsuario): ?string
    {
        $photosMap = $this->getUsersPhotosUrls([$idUsuario]);
        return $photosMap[$idUsuario] ?? null;
    }

    /**
     * Invalida o cache de fotos dos usuários
     * Útil após upload, atualização ou exclusão de imagem
     *
     * @param array|null $userIds IDs específicos (parâmetro mantido por compatibilidade, mas sempre limpa todo o cache)
     * @return void
     */
    public function invalidateCache(?array $userIds = null): void
    {
        try {
            // Sempre invalidar o cache completo para garantir consistência
            Cache::forget('user_photos_all');
            
            Log::info('UserImageService: Cache de fotos invalidado', [
                'triggered_by_user_ids' => $userIds
            ]);
        } catch (\Exception $e) {
            Log::warning('UserImageService: Erro ao invalidar cache', [
                'erro' => $e->getMessage()
            ]);
        }
    }

    /**
     * Busca fotos da API de arquivos
     *
     * @param array|null $userIds
     * @return array [id_usuario => foto_url]
     */
    private function fetchPhotosFromApi(?array $userIds = null): array
    {
        $photosMap = [];

        try {
            // Endpoint para listar TODAS as imagens de usuários (todas as empresas)
            $endpoint = "{$this->apiBaseUrl}/api/usuarios/imagens";
            
            Log::debug('UserImageService: Fazendo requisição à API', [
                'endpoint' => $endpoint,
                'user_ids' => $userIds
            ]);

            // Fazer requisição HTTP
            $response = Http::timeout(10)->get($endpoint);

            if (!$response->successful()) {
                Log::warning('UserImageService: Resposta não bem-sucedida da API', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();

            // Validar estrutura da resposta
            if (!isset($data['success']) || !$data['success'] || !isset($data['data']['files'])) {
                Log::warning('UserImageService: Estrutura de resposta inválida', [
                    'response' => $data
                ]);
                return [];
            }

            $files = $data['data']['files'];

            // Processar cada arquivo e extrair o id_usuario do nome do arquivo
            foreach ($files as $file) {
                $idUsuario = $this->extractUserIdFromFilename($file['name']);
                
                if ($idUsuario !== null) {
                    // Se temos um filtro de IDs, aplicar
                    if ($userIds !== null && !in_array($idUsuario, $userIds)) {
                        continue;
                    }

                    // Cada usuário pode ter apenas 1 foto, então sobrescrever é ok
                    // (a API já garante isso)
                    $photosMap[$idUsuario] = $file['url'];
                }
            }

            Log::debug('UserImageService: Fotos processadas', [
                'total_files' => count($files),
                'mapped_users' => count($photosMap)
            ]);

        } catch (\Exception $e) {
            Log::error('UserImageService: Erro ao buscar da API', [
                'erro' => $e->getMessage(),
                'endpoint' => $endpoint ?? 'unknown'
            ]);
        }

        return $photosMap;
    }

    /**
     * Extrai o ID do usuário do nome do arquivo
     * Formato esperado: usuarios_{nome}_{idUsuario}_{idEmpresa}_{uuid}.jpg
     *
     * @param string $filename
     * @return int|null
     */
    private function extractUserIdFromFilename(string $filename): ?int
    {
        try {
            // Padrão: usuarios_{nome}_{idUsuario}_{idEmpresa}_{uuid}.ext
            // Exemplo: usuarios_foto-perfil_151_1_abc123.jpg
            
            // Remover extensão
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            
            // Dividir por underscore
            $parts = explode('_', $nameWithoutExt);
            
            // Precisamos de pelo menos 4 partes: [usuarios, nome, idUsuario, idEmpresa, ...]
            if (count($parts) >= 4 && $parts[0] === 'usuarios') {
                // O idUsuario está na terceira posição (índice 2)
                // Mas o nome pode conter underscores, então precisamos pegar de trás para frente
                
                // Formato: usuarios_[nome com possíveis _]_idUsuario_idEmpresa_uuid
                // Os últimos 3 componentes são sempre: idUsuario, idEmpresa, uuid
                $idUsuario = (int) $parts[count($parts) - 3];
                
                return $idUsuario > 0 ? $idUsuario : null;
            }

        } catch (\Exception $e) {
            Log::warning('UserImageService: Erro ao extrair ID do usuário do filename', [
                'filename' => $filename,
                'erro' => $e->getMessage()
            ]);
        }

        return null;
    }
}
