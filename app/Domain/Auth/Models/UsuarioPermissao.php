<?php

namespace App\Domain\Auth\Models;

use App\Models\Modulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsuarioPermissao extends Model
{
    protected $table = 'usuario_permissoes';
    protected $primaryKey = 'id_usuario_permissao';
    
    protected $fillable = [
        'id_usuario',
        'id_modulo',
        'pode_ler',
        'pode_criar',
        'pode_editar',
        'pode_deletar'
    ];
    
    protected $casts = [
        'pode_ler' => 'boolean',
        'pode_criar' => 'boolean',
        'pode_editar' => 'boolean',
        'pode_deletar' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relacionamento com usuário
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Relacionamento com módulo
     */
    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id_modulo');
    }
}
