<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Auth\Models\Empresa;

class PlanoContratado extends Model
{
    use HasFactory;

    protected $table = 'planos_contratados';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_empresa',
        'nome',
        'valor',
        'adesao',
        'data_contratacao',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'adesao' => 'decimal:2',
        'data_contratacao' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function modulos(): HasMany
    {
        return $this->hasMany(PlanoContratadoModulo::class, 'id_plano_contratado', 'id')
                    ->where('ativo', 1);
    }

    public function todosModulos(): HasMany
    {
        return $this->hasMany(PlanoContratadoModulo::class, 'id_plano_contratado', 'id');
    }

    public function modulosContratados(): HasMany
    {
        return $this->hasMany(PlanoContratadoModulo::class, 'id_plano_contratado', 'id')
                    ->where('ativo', 1);
    }

    // Scopes
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopePorEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Buscar o plano ativo de uma empresa
     */
    public static function planoAtivoDaEmpresa($idEmpresa)
    {
        return static::where('id_empresa', $idEmpresa)
                     ->where('status', 'ativo')
                     ->orderBy('created_at', 'desc')
                     ->first();
    }

    // Acessores
    public function getValorFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor, 2, ',', '.');
    }

    public function getAdesaoFormatadaAttribute()
    {
        if (!$this->adesao) {
            return 'R$ 0,00';
        }
        return 'R$ ' . number_format($this->adesao, 2, ',', '.');
    }

    public function getDataContratacaoFormatadaAttribute()
    {
        return $this->data_contratacao ? $this->data_contratacao->format('d/m/Y H:i') : '-';
    }

    // Helpers
    public function possuiModulo(string $nomeModulo): bool
    {
        return $this->modulos()
                    ->where('nome_modulo', $nomeModulo)
                    ->where('ativo', 1)
                    ->exists();
    }

    public function getModulo(string $nomeModulo): ?PlanoContratadoModulo
    {
        return $this->modulos()
                    ->where('nome_modulo', $nomeModulo)
                    ->where('ativo', 1)
                    ->first();
    }

    public function getModulosAtivos(): array
    {
        return $this->modulos()
                    ->pluck('nome_modulo')
                    ->toArray();
    }

    public function getTotalModulos(): int
    {
        return $this->modulos()->count();
    }
}