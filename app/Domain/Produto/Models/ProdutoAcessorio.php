<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoAcessorio extends Model
{
    protected $table = 'produto_acessorios';
    protected $primaryKey = 'id_produto_acessorio';
    public $incrementing = true;

    protected $fillable = [
        'id_produto',
        'id_acessorio',
        'quantidade',
        'obrigatorio',
    ];

    protected $casts = [
        'quantidade' => 'integer',
        'obrigatorio' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamento com produto
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com acessório
     */
    public function acessorio()
    {
        return $this->belongsTo(Acessorio::class, 'id_acessorio', 'id_acessorio');
    }
}
