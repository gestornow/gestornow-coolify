<?php

namespace App\Domain\Venda\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VendaItem extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'venda_itens';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_venda_item';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_venda',
        'id_produto_venda',
        'nome_produto',
        'codigo_produto',
        'quantidade',
        'preco_unitario',
        'desconto',
        'subtotal',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2',
        'desconto' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    /**
     * Accessor para retornar subtotal formatado em BRL
     *
     * @return string
     */
    public function getSubtotalFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->subtotal ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para retornar preço unitário formatado em BRL
     *
     * @return string
     */
    public function getPrecoUnitarioFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_unitario ?? 0, 2, ',', '.');
    }

    /**
     * Relacionamento com a venda
     */
    public function venda()
    {
        return $this->belongsTo(Venda::class, 'id_venda', 'id_venda');
    }

    /**
     * Relacionamento com o produto
     */
    public function produto()
    {
        return $this->belongsTo(\App\Domain\Produto\Models\ProdutoVenda::class, 'id_produto_venda', 'id_produto_venda');
    }
}
