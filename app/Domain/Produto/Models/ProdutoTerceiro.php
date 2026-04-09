<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoTerceiro extends Model
{
    use SoftDeletes;

    protected $table = 'produtos_terceiros';
    protected $primaryKey = 'id_produto_terceiro';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_fornecedor',
        'nome',
        'descricao',
        'codigo',
        'custo_diaria',
        'preco_locacao',
        'foto_url',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'custo_diaria' => 'decimal:2',
        'preco_locacao' => 'decimal:2',
    ];

    /**
     * Relacionamento com fornecedor
     */
    public function fornecedor()
    {
        return $this->belongsTo(\App\Models\Fornecedor::class, 'id_fornecedor', 'id_fornecedores');
    }

    /**
     * Scope para empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para status ativo
     */
    public function scopeAtivo($query)
    {
        return $query->where('status', 'ativo');
    }

    /**
     * Accessor para preço formatado
     */
    public function getPrecoLocacaoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_locacao ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para custo formatado
     */
    public function getCustoDiariaFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->custo_diaria ?? 0, 2, ',', '.');
    }
}
