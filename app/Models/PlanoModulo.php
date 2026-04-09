<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoModulo extends Model
{
    use HasFactory;

    protected $table = 'planos_modulos';
    protected $primaryKey = 'id_plano_modulo';

    protected $fillable = [
        'id_plano',
        'id_modulo',
        'limite',
        'ativo',
    ];

    protected $casts = [
        'limite' => 'integer',
        'ativo' => 'boolean',
    ];

    public $timestamps = false;

    // Relacionamentos
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'id_plano', 'id_plano');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id_modulo');
    }

    // Scopes
    public function scopeAtivos($query)
    {
        return $query->where('ativo', 1);
    }

    public function scopeInativos($query)
    {
        return $query->where('ativo', 0);
    }

    // Helpers
    public function temLimite(): bool
    {
        return !is_null($this->limite) && $this->limite > 0;
    }

    public function getLimiteFormatado(): string
    {
        if (!$this->temLimite()) {
            return 'Ilimitado';
        }
        
        return number_format($this->limite, 0, ',', '.');
    }

    public function isAtivo(): bool
    {
        return $this->ativo === 1;
    }
}