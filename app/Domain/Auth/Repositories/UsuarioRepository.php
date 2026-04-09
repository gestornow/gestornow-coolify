<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UsuarioRepository
{
    public function __construct(
        private Usuario $model
    ) {}

    // ════════════════════════════════════════════════
    // BUSCAR USUÁRIOS
    // ════════════════════════════════════════════════

    public function findById(int $id): ?Usuario
    {
        return $this->model->with('empresa')->find($id);
    }

    /**
     * Busca usuário deletado por ID (soft delete)
     */
    public function findTrashedById(int $id): ?Usuario
    {
        return $this->model->onlyTrashed()->find($id);
    }

    public function findByLogin(string $login): ?Usuario
    {
        return $this->model->where('login', strtolower(trim($login)))->first();
    }

    public function findByCpf(string $cpf): ?Usuario
    {
        $cpfLimpo = preg_replace('/\D/', '', $cpf);
        return $this->model->where('cpf', $cpfLimpo)->first();
    }

    public function findByCodigoReset(string $codigo): ?Usuario
    {
        return $this->model->where('codigo_reset', $codigo)->first();
    }

    public function findBySessionToken(string $token): ?Usuario
    {
        return $this->model->where('session_token', $token)->first();
    }

    // ════════════════════════════════════════════════
    // CRUD BÁSICO
    // ════════════════════════════════════════════════

    public function create(array $data): Usuario
    {
        return $this->model->create($data);
    }

    public function update(Usuario $usuario, array $data): bool
    {
        return $usuario->update($data);
    }

    public function delete(Usuario $usuario): bool
    {
        return $usuario->delete();
    }

    /**
     * Restaura um usuário deletado (soft delete)
     */
    public function restore(Usuario $usuario): bool
    {
        return $usuario->restore();
    }

    /**
     * Deleta permanentemente um usuário
     */
    public function forceDelete(Usuario $usuario): bool
    {
        return $usuario->forceDelete();
    }

    // ════════════════════════════════════════════════
    // LISTAGENS E PAGINAÇÃO
    // ════════════════════════════════════════════════

    /**
     * Retorna usuários paginados com filtros
     */
   public function paginate(array $filters = [], int $perPage = 15)
    {
       // Use the centralized applyFilters so pagination respects all supported filters
       // Eager load empresa to avoid N+1 when rendering list
       $query = $this->model->newQuery()->with('empresa');
       $this->applyFilters($query, $filters);

       return $query->paginate($perPage);
    }

    /**
     * Retorna todos os usuários com filtros (sem paginação)
     */
    public function all(array $filters = []): Collection
    {
        $query = $this->model->query()->with('empresa');
        
        $this->applyFilters($query, $filters);
        
        return $query->get();
    }

    public function buscarPorFiltros(array $filtros): Collection
    {
        return $this->all($filtros);
    }

    // ════════════════════════════════════════════════
    // BUSCAS POR EMPRESA
    // ════════════════════════════════════════════════

    public function findByEmpresa(int $idEmpresa): Collection
    {
        return $this->model->empresa($idEmpresa)->get();
    }

    public function ativosPorEmpresa(int $idEmpresa): Collection
    {
        return $this->model->empresa($idEmpresa)->ativo()->get();
    }

    public function contarUsuariosPorEmpresa(int $idEmpresa): int
    {
        return $this->model->empresa($idEmpresa)->ativo()->count();
    }

    // ════════════════════════════════════════════════
    // BUSCAS ESPECÍFICAS
    // ════════════════════════════════════════════════

    public function usuariosSuporte(): Collection
    {
        return $this->model->suporte()->ativo()->get();
    }

    public function ultimosAcessos(int $limite = 10): Collection
    {
        return $this->model->ativo()
            ->whereNotNull('data_ultimo_acesso')
            ->orderBy('data_ultimo_acesso', 'desc')
            ->limit($limite)
            ->with('empresa')
            ->get();
    }

    /**
     * Busca usuários ativos
     */
    public function getAtivos(): Collection
    {
        return $this->model->ativo()
            ->orderBy('nome')
            ->get();
    }

    /**
     * Busca usuários bloqueados
     */
    public function getBloqueados(): Collection
    {
        return $this->model->bloqueado()
            ->orderBy('nome')
            ->get();
    }

    // ════════════════════════════════════════════════
    // VALIDAÇÕES DE EXISTÊNCIA
    // ════════════════════════════════════════════════

    public function loginExiste(string $login, ?int $excludeId = null): bool
    {
        $query = $this->model->where('login', strtolower(trim($login)));
        
        if ($excludeId) {
            $query->where('id_usuario', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    public function cpfExiste(string $cpf, ?int $excludeId = null): bool
    {
        $cpfLimpo = preg_replace('/\D/', '', $cpf);
        $query = $this->model->where('cpf', $cpfLimpo);
        
        if ($excludeId) {
            $query->where('id_usuario', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    public function emailExiste(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('login', strtolower(trim($email)));
        
        if ($excludeId) {
            $query->where('id_usuario', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    // ════════════════════════════════════════════════
    // CONTADORES E ESTATÍSTICAS
    // ════════════════════════════════════════════════

    /**
     * Conta usuários com filtros opcionais
     */
    public function count(array $filters = []): int
    {
        $query = $this->model->query();
        
        $this->applyFilters($query, $filters);
        
        return $query->count();
    }

    /**
     * Retorna estatísticas gerais dos usuários
     */
    public function getStatistics(): array
    {
        return [
            'total' => $this->model->count(),
            'ativos' => $this->model->where('status', 'ativo')->count(),
            'bloqueados' => $this->model->where('status', 'bloqueado')->count(),
            'inativos' => $this->model->where('status', 'inativo')->count(),
            'deletados' => $this->model->onlyTrashed()->count(),
        ];
    }

    // ════════════════════════════════════════════════
    // MÉTODO PRIVADO - APLICAR FILTROS
    // ════════════════════════════════════════════════

    /**
     * Aplica filtros na query
     */
    private function applyFilters($query, array $filters): void
    {
        // Normalize alternative keys: "filial" or "empresa" -> id_empresa
        if (isset($filters['filial']) && !isset($filters['id_empresa'])) {
            $filters['id_empresa'] = $filters['filial'];
        }
        if (isset($filters['empresa']) && !isset($filters['id_empresa'])) {
            $filters['id_empresa'] = $filters['empresa'];
        }

        // If no company filter provided, enforce default to logged-in user's company
        // This guarantees listing shows only users from the same company by default.
        if (empty($filters['id_empresa']) && Auth::check()) {
            $filters['id_empresa'] = Auth::user()->id_empresa ?? null;
        }

        // Busca geral (nome, login, cpf)
        if (!empty($filters['search'])){
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                  ->orWhere('login', 'like', "%{$search}%")
                  ->orWhere('cpf', 'like', "%{$search}%");
            });
        }

        // Filtro por status (aceita 'status' simples ou 'status_in' como array)
        if (isset($filters['status_in']) && is_array($filters['status_in'])) {
            $query->whereIn('status', $filters['status_in']);
        } elseif (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtro por finalidade (cargo/função)
        if (isset($filters['finalidade']) && $filters['finalidade'] !== '') {
            $query->where('finalidade', $filters['finalidade']);
        }

        // Filtro por nome específico
        if (isset($filters['nome'])) {
            $query->where('nome', 'like', '%' . $filters['nome'] . '%');
        }

        // Filtro por login específico
        if (isset($filters['login'])) {
            $query->where('login', 'like', '%' . $filters['login'] . '%');
        }

        // Filtro por empresa
        if (isset($filters['id_empresa'])) {
            $query->where('id_empresa', $filters['id_empresa']);
        }

        // Filtro por suporte
        if (isset($filters['is_suporte'])) {
            $query->where('is_suporte', (bool) $filters['is_suporte']);
        }

        // Filtro por CPF específico
        if (isset($filters['cpf'])) {
            $cpfLimpo = preg_replace('/\D/', '', $filters['cpf']);
            $query->where('cpf', 'like', '%' . $cpfLimpo . '%');
        }

        // Ordenação
        $sortBy = $filters['sort_by'] ?? 'nome';
        $sortOrder = $filters['sort_order'] ?? 'asc';
        $query->orderBy($sortBy, $sortOrder);
    }
}