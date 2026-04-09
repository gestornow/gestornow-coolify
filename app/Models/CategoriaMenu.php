<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoriaMenu extends Model
{
    protected $table = 'categorias_menu';
    protected $primaryKey = 'id_categoria';
    
    protected $fillable = [
        'nome',
        'cor',
        'icone',
        'ordem',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer'
    ];

    /**
     * Scope para buscar apenas categorias ativas
     */
    public function scopeAtivas($query)
    {
        return $query->where('ativo', 1);
    }

    /**
     * Scope para ordenar por ordem
     */
    public function scopeOrdenadas($query)
    {
        return $query->orderBy('ordem', 'asc');
    }
}
