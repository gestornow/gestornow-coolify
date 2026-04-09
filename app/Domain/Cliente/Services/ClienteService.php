<?php

namespace App\Domain\Cliente\Services;

use App\Domain\Cliente\Models\Cliente;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class ClienteService
{
    public function __construct(
        private ClienteImageService $imageService
    ) {}
    /**
     * Retorna lista paginada de clientes
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getClienteList(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = Cliente::query();

        // Filtro por empresa (se não passar id_empresa, mostra todos)
        if (!empty($filters['id_empresa'])) {
            $query->where('id_empresa', $filters['id_empresa']);
        }

        // Filtro por status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filtro por tipo de pessoa
        if (!empty($filters['id_tipo_pessoa'])) {
            $query->where('id_tipo_pessoa', $filters['id_tipo_pessoa']);
        }

        // Filtro por filial
        if (!empty($filters['id_filial'])) {
            $query->where('id_filial', $filters['id_filial']);
        }

        // Busca por nome, CPF/CNPJ, email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('razao_social', 'like', "%{$search}%");
            });
        }

        // Ordenar por nome
        $query->orderBy('nome', 'asc');

        $clientes = $query->paginate($perPage);
        
        // Adicionar fotos dos clientes
        if ($clientes->count() > 0) {
            // Agrupar clientes por empresa
            $empresasIds = $clientes->pluck('id_empresa')->unique()->toArray();
            $fotosMap = [];
            
            foreach ($empresasIds as $empresaId) {
                if ($empresaId) {
                    $fotosEmpresa = $this->imageService->getClientesPhotosUrls($empresaId);
                    $fotosMap = $fotosMap + $fotosEmpresa;
                }
            }
            
            // Adicionar foto_url a cada cliente
            $clientes->getCollection()->transform(function ($cliente) use ($fotosMap) {
                $cliente->definirFotoUrlExterna($fotosMap[$cliente->id_clientes] ?? null);
                return $cliente;
            });
        }

        return $clientes;
    }

    /**
     * Criar novo cliente
     *
     * @param array $data
     * @return Cliente
     */
    public function create(array $data): Cliente
    {
        $data = $this->preencherIdEmpresaContexto($data);
        $data = $this->removerCamposNaoPersistentes($data);
        $this->validateClienteData($data);

        // Remover token CSRF e campos de sistema
        unset($data['_token']);
        unset($data['deleted_at']);
        unset($data['updated_at']);
        unset($data['created_at']);

        // Definir status padrão como ativo se não informado
        if (!isset($data['status'])) {
            $data['status'] = 'ativo';
        }

        // Definir tipo de pessoa padrão se não informado
        if (!isset($data['id_tipo_pessoa'])) {
            $data['id_tipo_pessoa'] = 1; // Pessoa Física
        }

        return Cliente::create($data);
    }

    /**
     * Atualizar cliente existente
     *
     * @param int $id
     * @param array $data
     * @return Cliente
     */
    public function update(int $id, array $data): Cliente
    {
        $cliente = $this->getClienteById($id);

        if (!$cliente) {
            throw ValidationException::withMessages([
                'cliente' => ['Cliente não encontrado.']
            ]);
        }

        $data = $this->preencherIdEmpresaContexto($data, (int) $cliente->id_empresa);
        $data = $this->removerCamposNaoPersistentes($data);
        $this->validateClienteData($data, $id);

        // Remover campos que não devem ser atualizados
        unset($data['_token']);
        unset($data['_method']);
        unset($data['id_clientes']);
        unset($data['deleted_at']);
        unset($data['updated_at']);
        unset($data['created_at']);

        $cliente->update($data);

        return $cliente->refresh();
    }

    /**
     * Validar dados do cliente
     *
     * @param array $data
     * @param int|null $ignoreId
     * @return void
     * @throws ValidationException
     */
    private function validateClienteData(array $data, ?int $ignoreId = null): void
    {
        $idEmpresa = (int) ($data['id_empresa'] ?? 0);

        $rules = [
            'id_empresa' => 'required|integer|exists:empresa,id_empresa',
            'id_filial' => 'nullable|integer|exists:empresa,id_empresa',
            'nome' => 'required|string|max:255',
            'cep' => 'nullable|string|max:9',
            'endereco' => 'nullable|string|max:255',
            'numero' => 'nullable|string|max:50',
            'complemento' => 'nullable|string|max:255',
            'rg_ie' => 'nullable|string|max:20',
            'razao_social' => 'nullable|string|max:255',
            'bairro' => 'nullable|string|max:100',
            'cidade' => 'nullable|string|max:255',
            'uf' => 'nullable|string|size:2',
            'email' => 'nullable|email|max:255',
            'endereco_entrega' => 'nullable|string|max:255',
            'numero_entrega' => 'nullable|string|max:50',
            'complemento_entrega' => 'nullable|string|max:255',
            'cep_entrega' => 'nullable|string|max:9',
            'telefone' => 'nullable|string|max:20',
            'data_nascimento' => 'nullable|date',
            'status' => 'nullable|string|in:ativo,inativo,bloqueado',
            'id_tipo_pessoa' => 'nullable|integer|in:1,2',
            'foto' => 'nullable|string|max:255',
            'nomeImagemCliente' => 'nullable|string|max:255',
        ];

        // Validação de CPF/CNPJ único
        if (isset($data['cpf_cnpj'])) {
            $uniqueRule = Rule::unique('clientes', 'cpf_cnpj')
                ->where(function ($query) use ($idEmpresa) {
                    $query->where('id_empresa', $idEmpresa)
                        ->whereNull('deleted_at');
                });

            if ($ignoreId) {
                $uniqueRule->ignore($ignoreId, 'id_clientes');
            }

            $rules['cpf_cnpj'] = ['nullable', 'string', 'max:18', $uniqueRule];
        }

        // Validação de email único
        if (isset($data['email']) && !empty($data['email'])) {
            $uniqueRule = Rule::unique('clientes', 'email')
                ->where(function ($query) use ($idEmpresa) {
                    $query->where('id_empresa', $idEmpresa)
                        ->whereNull('deleted_at');
                });

            if ($ignoreId) {
                $uniqueRule->ignore($ignoreId, 'id_clientes');
            }

            $rules['email'] = ['nullable', 'email', 'max:255', $uniqueRule];
        }

        $validator = Validator::make($data, $rules, [
            'cpf_cnpj.unique' => 'Este CPF/CNPJ já está cadastrado.',
            'email.unique' => 'Este e-mail já está cadastrado para outro cliente.',
        ], [
            'cpf_cnpj' => 'CPF/CNPJ',
            'email' => 'e-mail',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    private function preencherIdEmpresaContexto(array $data, ?int $fallbackEmpresa = null): array
    {
        $idEmpresaSessao = session('id_empresa');
        if (!empty($idEmpresaSessao)) {
            $data['id_empresa'] = (int) $idEmpresaSessao;
            return $data;
        }

        $idEmpresaAtual = isset($data['id_empresa']) ? trim((string) $data['id_empresa']) : '';

        if ($idEmpresaAtual !== '') {
            $data['id_empresa'] = (int) $idEmpresaAtual;
            return $data;
        }

        $idEmpresaContexto = $fallbackEmpresa;

        if (!empty($idEmpresaContexto)) {
            $data['id_empresa'] = (int) $idEmpresaContexto;
        }

        return $data;
    }

    private function removerCamposNaoPersistentes(array $data): array
    {
        unset($data['foto_url']);
        unset($data['fotoFilename']);
        return $data;
    }

    /**
     * Deletar cliente
     *
     * @param int $id
     * @return void
     */
    public function destroy(int $id): void
    {
        $cliente = $this->getClienteById($id);

        if (!$cliente) {
            throw ValidationException::withMessages([
                'cliente' => ['Cliente não encontrado.']
            ]);
        }

        $cliente->delete();
    }

    /**
     * Deletar múltiplos clientes
     *
     * @param array $ids
     * @return int
     */
    public function destroyMultiple(array $ids): int
    {
        return Cliente::whereIn('id_clientes', $ids)->delete();
    }

    /**
     * Contar clientes por filtros
     *
     * @param array $filters
     * @return int
     */
    public function countClientes(array $filters = []): int
    {
        $query = Cliente::query();

        if (!empty($filters['id_empresa'])) {
            $query->where('id_empresa', $filters['id_empresa']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['id_tipo_pessoa'])) {
            $query->where('id_tipo_pessoa', $filters['id_tipo_pessoa']);
        }

        return $query->count();
    }

    /**
     * Retornar um cliente por ID
     *
     * @param int $id
     * @return Cliente|null
     */
    public function getClienteById(int $id): ?Cliente
    {
        $cliente = Cliente::find($id);

        if ($cliente && !empty($cliente->id_empresa)) {
            $cliente->definirFotoUrlExterna(
                $this->imageService->getClientePhotoUrl((int) $cliente->id_empresa, (int) $cliente->id_clientes)
            );
        }

        return $cliente;
    }

    /**
     * Buscar clientes por termo
     *
     * @param string $term
     * @param int|null $idEmpresa
     * @return Collection
     */
    public function searchClientes(string $term, ?int $idEmpresa = null): Collection
    {
        $query = Cliente::query();

        if ($idEmpresa) {
            $query->where('id_empresa', $idEmpresa);
        }

        $query->where(function ($q) use ($term) {
            $q->where('nome', 'like', "%{$term}%")
                ->orWhere('cpf_cnpj', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('razao_social', 'like', "%{$term}%");
        });

        return $query->limit(50)->get();
    }

    /**
     * Obter estatísticas de clientes
     *
     * @param int|null $idEmpresa
     * @return array
     */
    public function getStatistics(?int $idEmpresa = null): array
    {
        $query = Cliente::query();

        if ($idEmpresa) {
            $query->where('id_empresa', $idEmpresa);
        }

        return [
            'total' => (clone $query)->count(),
            'ativos' => (clone $query)->where('status', 'ativo')->count(),
            'inativos' => (clone $query)->where('status', 'inativo')->count(),
            'bloqueados' => (clone $query)->where('status', 'bloqueado')->count(),
            'pessoa_fisica' => (clone $query)->where('id_tipo_pessoa', 1)->count(),
            'pessoa_juridica' => (clone $query)->where('id_tipo_pessoa', 2)->count(),
        ];
    }
}
