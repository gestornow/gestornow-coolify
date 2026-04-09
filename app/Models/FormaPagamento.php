<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormaPagamento extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'forma_pagamento';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_forma_pagamento';

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
        'nome',
        'descricao',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the empresa that owns the forma de pagamento.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Get the contas a pagar associated with the forma de pagamento.
     */
    public function contasAPagar()
    {
        return $this->hasMany(ContasAPagar::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    /**
     * Get the contas a receber associated with the forma de pagamento.
     */
    public function contasAReceber()
    {
        return $this->hasMany(ContasAReceber::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    /**
     * Scope a query to only include formas de pagamento from a specific empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }
}
