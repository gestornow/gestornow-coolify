<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProdutoVenda extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'produtos_venda';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_produto_venda';

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
        'id_empresa',
        'id_marca',
        'id_grupo',
        'id_tipo',
        'unidade_medida_id',
        'id_modelo',
        'hex_color',
        'nome',
        'descricao',
        'detalhes',
        'preco',
        'preco_reposicao',
        'preco_custo',
        'preco_venda',
        'preco_locacao',
        'altura',
        'largura',
        'profundidade',
        'peso',
        'estoque_total',
        'quantidade',
        'codigo',
        'numero_serie',
        'status',
        'foto_url',
        'foto_filename',
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
        'preco' => 'decimal:2',
        'preco_reposicao' => 'decimal:2',
        'preco_custo' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'preco_locacao' => 'decimal:2',
        'altura' => 'decimal:2',
        'largura' => 'decimal:2',
        'profundidade' => 'decimal:2',
        'peso' => 'decimal:2',
        'estoque_total' => 'integer',
        'quantidade' => 'integer',
    ];

    /**
     * Accessor para retornar a inicial do nome do produto
     *
     * @return string
     */
    public function getInicialAttribute()
    {
        return strtoupper(substr($this->nome, 0, 1));
    }

    /**
     * Accessor para retornar preço formatado em BRL
     *
     * @return string
     */
    public function getPrecoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_venda ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para retornar preço de custo formatado em BRL
     *
     * @return string
     */
    public function getPrecoCustoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_custo ?? 0, 2, ',', '.');
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para filtrar apenas ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    /**
     * Scope para buscar por nome, código ou número de série
     */
    public function scopeBuscar($query, $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', '%' . $termo . '%')
              ->orWhere('codigo', 'like', '%' . $termo . '%')
              ->orWhere('numero_serie', 'like', '%' . $termo . '%');
        });
    }

    /**
     * Verifica se há estoque suficiente
     */
    public function temEstoque(int $quantidade = 1): bool
    {
        return ($this->quantidade ?? 0) >= $quantidade;
    }

    /**
     * Diminui o estoque
     */
    public function diminuirEstoque(int $quantidade): bool
    {
        if (!$this->temEstoque($quantidade)) {
            return false;
        }

        $this->quantidade = ($this->quantidade ?? 0) - $quantidade;
        return $this->save();
    }

    /**
     * Aumenta o estoque
     */
    public function aumentarEstoque(int $quantidade): bool
    {
        $this->quantidade = ($this->quantidade ?? 0) + $quantidade;
        return $this->save();
    }

    /**
     * Verifica se o produto está ativo
     */
    public function estaAtivo(): bool
    {
        return $this->status === 'ativo';
    }

    /**
     * Relacionamento com tabelas de preço
     */
    public function tabelasPreco()
    {
        return $this->hasMany(TabelaPrecoProdutoVenda::class, 'id_produto_venda', 'id_produto_venda');
    }

    /**
     * Relacionamento com itens de venda
     */
    public function vendaItens()
    {
        return $this->hasMany(\App\Domain\Venda\Models\VendaItem::class, 'id_produto_venda', 'id_produto_venda');
    }
}
