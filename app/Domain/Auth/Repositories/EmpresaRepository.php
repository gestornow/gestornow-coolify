<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\Models\Empresa;
use Illuminate\Database\Eloquent\Collection;

class EmpresaRepository
{
    protected $model;

    public function __construct(Empresa $model)
    {
        $this->model = $model;
    }

    public function findById(int $id): ?Empresa
    {
        return $this->model->find($id);
    }

    public function findByCnpj(string $cnpj): ?Empresa
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        return $this->model->where('cnpj', $cnpjLimpo)->first();
    }

    public function findByCpf(string $cpf): ?Empresa
    {
        $cpfLimpo = preg_replace('/\D/', '', $cpf);
        return $this->model->where('cpf', $cpfLimpo)->first();
    }

    public function findByEmail(string $email): ?Empresa
    {
        return $this->model->where('email', $email)->first();
    }

    public function create(array $data): Empresa
    {
        return $this->model->create($data);
    }

    public function update(Empresa $empresa, array $data): bool
    {
        return $empresa->update($data);
    }

    public function delete(Empresa $empresa): bool
    {
        return $empresa->delete();
    }

    public function ativas(): Collection
    {
        return $this->model->ativa()->get();
    }

    public function emValidacao(): Collection
    {
        return $this->model->validacao()->get();
    }

    public function teste(): Collection
    {
        return $this->model->teste()->get();
    }

    public function cnpjExiste(string $cnpj, ?int $excludeId = null): bool
    {
        $cnpjLimpo = preg_replace('/\D/', '', $cnpj);
        $query = $this->model->where('cnpj', $cnpjLimpo);
        
        if ($excludeId) {
            $query->where('id_empresa', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    public function cpfExiste(string $cpf, ?int $excludeId = null): bool
    {
        $cpfLimpo = preg_replace('/\D/', '', $cpf);
        $query = $this->model->where('cpf', $cpfLimpo);
        
        if ($excludeId) {
            $query->where('id_empresa', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    public function emailExiste(string $email, ?int $excludeId = null): bool
    {
        $query = $this->model->where('email', $email);
        
        if ($excludeId) {
            $query->where('id_empresa', '!=', $excludeId);
        }
        
        return $query->exists();
    }

    public function buscarPorFiltros(array $filtros): Collection
    {
        $query = $this->model->newQuery();

        if (isset($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (isset($filtros['nome_empresa'])) {
            $query->where('nome_empresa', 'like', '%' . $filtros['nome_empresa'] . '%');
        }

        if (isset($filtros['razao_social'])) {
            $query->where('razao_social', 'like', '%' . $filtros['razao_social'] . '%');
        }

        if (isset($filtros['cnpj'])) {
            $cnpjLimpo = preg_replace('/\D/', '', $filtros['cnpj']);
            $query->where('cnpj', 'like', '%' . $cnpjLimpo . '%');
        }

        if (isset($filtros['uf'])) {
            $query->where('uf', $filtros['uf']);
        }

        return $query->get();
    }

    public function getProximoCodigo(): int
    {
        $ultimoCodigo = $this->model->max('codigo') ?? 0;
        return $ultimoCodigo + 1;
    }
}