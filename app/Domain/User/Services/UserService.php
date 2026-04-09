<?php

namespace App\Domain\User\Services;

use App\Domain\Auth\Repositories\UsuarioRepository;
use App\Domain\Auth\Models\Usuario;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserService
{
    public function __construct(
        private UsuarioRepository $userRepository,
        private UserImageService $imageService
    ) {}
    
    /**
     * Retorna lista paginada de usuários. Permite sobrescrever o tamanho da página.
     * As fotos dos usuários são carregadas da API de arquivos.
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getUserList(array $filters = [], int $perPage = 15)
    {
        $users = $this->userRepository->paginate($filters, $perPage);
        
        // Extrair IDs dos usuários da página atual
        $userIds = $users->pluck('id_usuario')->toArray();
        
        // Buscar URLs das fotos para estes usuários
        $photosMap = $this->imageService->getUsersPhotosUrls($userIds);
        
        // Adicionar foto_url a cada usuário
        $users->getCollection()->transform(function ($user) use ($photosMap) {
            $user->foto_url = $photosMap[$user->id_usuario] ?? null;
            
            // Gerar inicial para avatar fallback
            if (empty($user->inicial)) {
                $user->inicial = $this->generateInitial($user->nome);
            }
            
            return $user;
        });
        
        return $users;
    }
    
    /**
     * Gera a inicial do nome para o avatar
     *
     * @param string|null $nome
     * @return string
     */
    private function generateInitial(?string $nome): string
    {
        if (empty($nome)) {
            return '?';
        }
        
        $words = explode(' ', trim($nome));
        
        // Se tiver mais de uma palavra, pegar primeira letra de cada uma (máximo 2)
        if (count($words) > 1) {
            return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
        }
        
        // Se tiver apenas uma palavra, pegar as duas primeiras letras
        return mb_strtoupper(mb_substr($nome, 0, 2));
    }

    public function create(array $data): Usuario
    {
        $this->validateUserData($data);
        
        // Processar is_suporte
        $data['is_suporte'] = isset($data['is_suporte']) && $data['is_suporte'] == '1' ? 1 : 0;
        unset($data['finalidade']);
        
        // Remover campos sensíveis que não devem ser setados via formulário
        unset($data['session_token']);
        unset($data['remember_token']);
        // Manter codigo_reset pois é necessário para validação de senha do usuário criado
        // unset($data['codigo_reset']);
        unset($data['google_calendar_token']);
        unset($data['_token']); // CSRF token do Laravel
        
        // Definir status padrão como ativo se não informado
        if (!isset($data['status'])) {
            $data['status'] = 'ativo';
        }
        
        return $this->userRepository->create($data);
    }

    private function validateUserData(array $data, ?int $ignoreId = null): void
    {
        // Base rules (conditional based on what's being updated)
        $rules = [
            'telefone' => 'nullable|string|max:50',
            'cpf' => 'nullable|string|max:20',
            'cep' => 'nullable|string|max:10',
            'endereco' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:100',
            'comissao' => 'nullable|numeric|min:0|max:100',
            'observacoes' => 'nullable|string',
            'id_empresa' => 'nullable|integer',
            'status' => 'nullable|string|in:ativo,inativo,bloqueado',
            'is_suporte' => 'nullable|boolean',
        ];

        // Only require login and nome if they're being set (for creation or full update)
        if (isset($data['login'])) {
            $uniqueRule = Rule::unique('usuarios', 'login')
                ->whereNull('deleted_at');

            if ($ignoreId) {
                $uniqueRule->ignore($ignoreId, 'id_usuario');
            }

            $rules['login'] = ['required', 'string', 'max:255', $uniqueRule];
        }

        if (isset($data['nome'])) {
            $rules['nome'] = 'required|string|max:255';
        }

        // For creation (when ignoreId is null), require login and nome
        if ($ignoreId === null) {
            $rules['login'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('usuarios', 'login')->whereNull('deleted_at'),
            ];
            $rules['nome'] = 'required|string|max:255';
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    public function update(int $id, array $data): Usuario
    {
        $usuario = $this->userRepository->findById($id);

        if (!$usuario) {
            throw ValidationException::withMessages([
                'user' => ['Usuário não encontrado.']
            ]);
        }

        $this->validateUserData($data, $id);
        
        // Processar is_suporte APENAS se foi enviado no request
        // Isso evita resetar o campo quando apenas foto_url/foto_filename são atualizados
        if (array_key_exists('is_suporte', $data)) {
            $data['is_suporte'] = $data['is_suporte'] == '1' ? 1 : 0;
        }
        unset($data['finalidade']);
        
        // Se está atualizando foto, invalidar cache de imagens
        if (isset($data['foto_url']) || isset($data['foto_filename'])) {
            $this->imageService->invalidateCache([$id]);
        }
        
        // Remover campos sensíveis que não devem ser atualizados via formulário
        // CRÍTICO: Não permitir alteração destes campos para evitar logout
        unset($data['session_token']);
        unset($data['remember_token']);
        unset($data['senha']);
        unset($data['codigo_reset']);
        unset($data['google_calendar_token']);
        unset($data['_token']); // CSRF token do Laravel
        unset($data['_method']); // Method spoofing
        unset($data['id_usuario']); // Prevenir alteração do ID
        
        // Se estiver atualizando o usuário logado, preservar alguns dados críticos
        if (\Auth::check() && \Auth::id() === $id) {
            // Não permitir que o usuário logado se desative ou bloqueie
            if (isset($data['status']) && !in_array($data['status'], ['ativo'])) {
                unset($data['status']);
            }
        }
        
        $this->userRepository->update($usuario, $data);

        return $usuario->refresh();
    }

    public function userByFilter(array $filters = []): Collection
    {
        return $this->userRepository->all($filters);
    }

    public function destroy(int $id): void
    {
        $usuario = $this->userRepository->findById($id);

        if (!$usuario) {
            throw ValidationException::withMessages([
                'user' => ['Usuário não encontrado.']
            ]);
        }

        $this->userRepository->delete($usuario);
    }

    public function countUsers(array $filters = []): int
    {
        return $this->userRepository->count($filters);
    }

    public function statsUsers(): array
    {
        return $this->userRepository->getStatistics();
    }

    /**
     * Retorna um usuário pelo ID (ou null se não existir)
     */
    public function getUserById(int $id): ?Usuario
    {
        return $this->userRepository->findById($id);
    }

    // Adicione este método se precisar de busca por termo
    public function searchUsers(string $term): Collection
    {
        return $this->userRepository->search($term);
    }

    /**
     * Alterar senha do usuário
     */
    public function alterarSenha(int $id, string $senhaAtual, string $novaSenha): Usuario
    {
        $usuario = $this->userRepository->findById($id);

        if (!$usuario) {
            throw ValidationException::withMessages([
                'usuario' => ['Usuário não encontrado.']
            ]);
        }

        // Verificar se a senha atual está correta
        if (!\Hash::check($senhaAtual, $usuario->senha)) {
            \Log::warning('Tentativa de alterar senha com senha atual incorreta', [
                'id_usuario' => $id,
                'ip' => \Request::ip()
            ]);

            throw ValidationException::withMessages([
                'senha_atual' => ['A senha atual está incorreta.']
            ]);
        }

        // Atualizar a senha
        $usuario->senha = \Hash::make($novaSenha);
        $usuario->save();

        \Log::info('Senha do usuário alterada com sucesso', [
            'id_usuario' => $id,
            'nome' => $usuario->nome,
            'timestamp' => now()
        ]);

        return $usuario;
    }
}