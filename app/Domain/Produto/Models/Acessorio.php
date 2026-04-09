<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Acessorio extends Model
{
    use SoftDeletes;

    protected $table = 'acessorios';
    protected $primaryKey = 'id_acessorio';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'nome',
        'descricao',
        'quantidade',
        'preco_custo',
        'valor',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'preco_custo' => 'decimal:2',
        'valor' => 'decimal:2',
        'quantidade' => 'integer',
    ];

    /**
     * Accessor para retornar a inicial do nome
     */
    public function getInicialAttribute()
    {
        return strtoupper(substr($this->nome, 0, 1));
    }

    /**
     * Accessor para retornar preço formatado em BRL
     */
    public function getPrecoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_venda ?? 0, 2, ',', '.');
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para buscar por nome ou código
     */
    public function scopeBuscar($query, $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('codigo', 'like', "%{$termo}%")
              ->orWhere('numero_serie', 'like', "%{$termo}%");
        });
    }

    /**
     * Relacionamento com produtos
     */
    public function produtos()
    {
        return $this->belongsToMany(
            Produto::class, 
            'produto_acessorios', 
            'id_acessorio', 
            'id_produto'
        )->withPivot('quantidade', 'obrigatorio')->withTimestamps();
    }
}
