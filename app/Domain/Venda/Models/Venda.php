<?php

namespace App\Domain\Venda\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venda extends Model
{
    use SoftDeletes, RegistraAtividade;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'vendas';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_venda';

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
        'id_cliente',
        'id_usuario',
        'id_forma_pagamento',
        'numero_venda',
        'data_venda',
        'subtotal',
        'desconto',
        'acrescimo',
        'total',
        'valor_recebido',
        'troco',
        'observacoes',
        'status',
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
        'data_venda' => 'datetime',
        'subtotal' => 'decimal:2',
        'desconto' => 'decimal:2',
        'acrescimo' => 'decimal:2',
        'total' => 'decimal:2',
        'valor_recebido' => 'decimal:2',
        'troco' => 'decimal:2',
    ];

    /**
     * Accessor para retornar total formatado em BRL
     *
     * @return string
     */
    public function getTotalFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->total ?? 0, 2, ',', '.');
    }

    /**
     * Relacionamento com itens da venda
     */
    public function itens()
    {
        return $this->hasMany(VendaItem::class, 'id_venda', 'id_venda');
    }

    /**
     * Relacionamento com a empresa
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Relacionamento com o cliente
     */
    public function cliente()
    {
        return $this->belongsTo(\App\Domain\Cliente\Models\Cliente::class, 'id_cliente', 'id_clientes');
    }

    /**
     * Relacionamento com o usuário
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relacionamento com a forma de pagamento
     */
    public function formaPagamento()
    {
        return $this->belongsTo(\App\Models\FormaPagamento::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Gerar número único da venda
     */
    public static function gerarNumeroVenda($idEmpresa)
    {
        $ultimoNumero = self::where('id_empresa', $idEmpresa)
            ->max('numero_venda');

        return ($ultimoNumero ?? 0) + 1;
    }
}
