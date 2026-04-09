<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class PlanoPromocao extends Model
{
    use HasFactory;

    protected $table = 'planos_promocoes';

    protected $fillable = [
        'id_plano',
        'nome',
        'data_inicio',
        'data_fim',
        'desconto_mensal',
        'desconto_adesao',
        'ativo',
        'observacoes',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'desconto_mensal' => 'decimal:2',
        'desconto_adesao' => 'decimal:2',
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'id_plano', 'id_plano');
    }

    /**
     * Verifica se a promoção está vigente na data informada
     */
    public function estaVigente(?Carbon $referencia = null): bool
    {
        if (!$this->ativo) {
            return false;
        }

        $referencia = $referencia ? $referencia->copy()->startOfDay() : now()->startOfDay();
        $inicio = $this->data_inicio ? Carbon::parse($this->data_inicio)->startOfDay() : null;
        $fim = $this->data_fim ? Carbon::parse($this->data_fim)->endOfDay() : null;

        if ($inicio && $referencia->lt($inicio)) {
            return false;
        }

        if ($fim && $referencia->gt($fim)) {
            return false;
        }

        return true;
    }

    /**
     * Scope para promoções ativas e vigentes
     */
    public function scopeVigentes($query, ?Carbon $referencia = null)
    {
        $referencia = $referencia ?? now();
        
        return $query->where('ativo', true)
            ->where(function ($q) use ($referencia) {
                $q->whereNull('data_inicio')
                    ->orWhere('data_inicio', '<=', $referencia->format('Y-m-d'));
            })
            ->where(function ($q) use ($referencia) {
                $q->whereNull('data_fim')
                    ->orWhere('data_fim', '>=', $referencia->format('Y-m-d'));
            });
    }
}
