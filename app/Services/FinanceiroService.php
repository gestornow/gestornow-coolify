<?php

namespace App\Services;

use App\Models\CategoriaContas;
use App\Models\Banco;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class FinanceiroService
{
    /**
     * Create a new categoria.
     *
     * @param array $data
     * @return \App\Models\CategoriaContas
     * @throws \Exception
     */
    public function criarCategoria(array $data): CategoriaContas
    {
        try {
            return CategoriaContas::create([
                'id_empresa' => $data['id_empresa'],
                'nome' => $data['nome'],
                'tipo' => $data['tipo'] ?? 'despesa',
                'descricao' => $data['descricao'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao criar categoria: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Create a new banco.
     *
     * @param array $data
     * @return \App\Models\Banco
     * @throws \Exception
     */
    public function criarBanco(array $data): Banco
    {
        try {
            return Banco::create([
                'id_empresa' => $data['id_empresa'],
                'nome_banco' => $data['nome_banco'],
                'agencia' => $data['agencia'] ?? null,
                'conta' => $data['conta'] ?? null,
                'saldo_inicial' => $data['saldo_inicial'] ?? 0,
                'observacoes' => $data['observacoes'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao criar banco: ' . $e->getMessage(), [
                'data' => $data,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Get all categorias for a specific empresa.
     *
     * @param int $id_empresa
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCategoriasByEmpresa(int $id_empresa): Collection
    {
        return CategoriaContas::where('id_empresa', $id_empresa)
            ->orderBy('nome')
            ->get();
    }

    /**
     * Get all bancos for a specific empresa.
     *
     * @param int $id_empresa
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getBancosByEmpresa(int $id_empresa): Collection
    {
        return Banco::where('id_empresa', $id_empresa)
            ->orderBy('nome_banco')
            ->get();
    }

    /**
     * Get categorias by type for a specific empresa.
     *
     * @param int $id_empresa
     * @param string $tipo
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getCategoriasByTipo(int $id_empresa, string $tipo): Collection
    {
        return CategoriaContas::where('id_empresa', $id_empresa)
            ->where('tipo', $tipo)
            ->orderBy('nome')
            ->get();
    }

    /**
     * Update categoria.
     *
     * @param int $id_categoria_contas
     * @param array $data
     * @return \App\Models\CategoriaContas
     * @throws \Exception
     */
    public function atualizarCategoria(int $id_categoria_contas, array $data): CategoriaContas
    {
        try {
            $categoria = CategoriaContas::findOrFail($id_categoria_contas);
            
            $categoria->update([
                'nome' => $data['nome'] ?? $categoria->nome,
                'tipo' => $data['tipo'] ?? $categoria->tipo,
                'descricao' => $data['descricao'] ?? $categoria->descricao,
            ]);

            return $categoria->fresh();
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar categoria: ' . $e->getMessage(), [
                'id' => $id_categoria_contas,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Update banco.
     *
     * @param int $id_bancos
     * @param array $data
     * @return \App\Models\Banco
     * @throws \Exception
     */
    public function atualizarBanco(int $id_bancos, array $data): Banco
    {
        try {
            $banco = Banco::findOrFail($id_bancos);
            
            $banco->update([
                'nome_banco' => $data['nome_banco'] ?? $banco->nome_banco,
                'agencia' => $data['agencia'] ?? $banco->agencia,
                'conta' => $data['conta'] ?? $banco->conta,
                'saldo_inicial' => $data['saldo_inicial'] ?? $banco->saldo_inicial,
                'observacoes' => $data['observacoes'] ?? $banco->observacoes,
            ]);

            return $banco->fresh();
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar banco: ' . $e->getMessage(), [
                'id' => $id_bancos,
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Delete categoria.
     *
     * @param int $id_categoria_contas
     * @return bool
     * @throws \Exception
     */
    public function deletarCategoria(int $id_categoria_contas): bool
    {
        try {
            $categoria = CategoriaContas::findOrFail($id_categoria_contas);
            return $categoria->delete();
        } catch (\Exception $e) {
            Log::error('Erro ao deletar categoria: ' . $e->getMessage(), [
                'id' => $id_categoria_contas,
            ]);
            throw $e;
        }
    }

    /**
     * Delete banco.
     *
     * @param int $id_bancos
     * @return bool
     * @throws \Exception
     */
    public function deletarBanco(int $id_bancos): bool
    {
        try {
            $banco = Banco::findOrFail($id_bancos);
            return $banco->delete();
        } catch (\Exception $e) {
            Log::error('Erro ao deletar banco: ' . $e->getMessage(), [
                'id' => $id_bancos,
            ]);
            throw $e;
        }
    }
}
