<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoriaContas extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'categoria_contas';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_categoria_contas';

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
        'tipo',
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
     * Get the empresa that owns the categoria.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Get the contas a pagar associated with the categoria.
     */
    public function contasAPagar()
    {
        return $this->hasMany(ContasAPagar::class, 'id_categoria_contas', 'id_categoria_contas');
    }

    /**
     * Get the contas a receber associated with the categoria.
     */
    public function contasAReceber()
    {
        return $this->hasMany(ContasAReceber::class, 'id_categoria_contas', 'id_categoria_contas');
    }

    /**
     * Scope a query to only include categorias from a specific empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    /**
     * Scope a query to only include categorias of a specific type.
     */
    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
