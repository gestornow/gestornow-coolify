<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plano extends Model
{
    use HasFactory;

    protected $table = 'planos';
    protected $primaryKey = 'id_plano';

    protected $fillable = [
        'nome',
        'descricao',
        'valor',
        'adesao',
        'relatorios',
        'bancos',
        'assinatura_digital',
        'contratos',
        'faturas',
        'ativo',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'adesao' => 'decimal:2',
        'relatorios' => 'string',
        'bancos' => 'string',
        'assinatura_digital' => 'string',
        'contratos' => 'string',
        'faturas' => 'string',
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    private static function normalizarFlagSN($valor): string
    {
        if (is_bool($valor)) {
            return $valor ? 'S' : 'N';
        }

        $normalizado = strtoupper(trim((string) $valor));

        return in_array($normalizado, ['S', '1', 'TRUE', 'SIM', 'Y', 'YES'], true)
            ? 'S'
            : 'N';
    }

    public function getRelatoriosAttribute($valor): string
    {
        return self::normalizarFlagSN($valor);
    }

    public function getBancosAttribute($valor): string
    {
        return self::normalizarFlagSN($valor);
    }

    public function getAssinaturaDigitalAttribute($valor): string
    {
        return self::normalizarFlagSN($valor);
    }

    public function getContratosAttribute($valor): string
    {
        return self::normalizarFlagSN($valor);
    }

    public function getFaturasAttribute($valor): string
    {
        return self::normalizarFlagSN($valor);
    }

    // Relacionamentos
    public function modulos(): HasMany
    {
        return $this->hasMany(PlanoModulo::class, 'id_plano', 'id_plano')
                    ->where('planos_modulos.ativo', 1);
    }

    public function todosModulos(): HasMany
    {
        return $this->hasMany(PlanoModulo::class, 'id_plano', 'id_plano');
    }

    public function promocoes(): HasMany
    {
        return $this->hasMany(PlanoPromocao::class, 'id_plano', 'id_plano');
    }

    public function promocoesAtivas(): HasMany
    {
        return $this->promocoes()
            ->where('ativo', 1)
            ->orderBy('id', 'desc');
    }

    /**
     * Retorna a promoção vigente (dentro do período de datas)
     */
    public function obterPromocaoVigente(): ?PlanoPromocao
    {
        return $this->promocoes()
            ->vigentes()
            ->first();
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

    // Acessores
    public function getValorFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor, 2, ',', '.');
    }

    public function getAdesaoFormatadaAttribute()
    {
        return 'R$ ' . number_format($this->adesao, 2, ',', '.');
    }

    // Helpers
    public function temRelatorios(): bool
    {
        return $this->relatorios === 'S';
    }

    public function temBancos(): bool
    {
        return $this->bancos === 'S';
    }

    public function temAssinaturaDigital(): bool
    {
        return $this->assinatura_digital === 'S';
    }

    public function temContratos(): bool
    {
        return $this->contratos === 'S';
    }

    public function temFaturas(): bool
    {
        return $this->faturas === 'S';
    }

    public function getRecursosAtivos(): array
    {
        $recursos = [];
        
        if ($this->temRelatorios()) $recursos[] = 'Relatórios';
        if ($this->temBancos()) $recursos[] = 'Bancos';
        if ($this->temAssinaturaDigital()) $recursos[] = 'Assinatura Digital';
        if ($this->temContratos()) $recursos[] = 'Contratos';
        if ($this->temFaturas()) $recursos[] = 'Faturas';

        return $recursos;
    }

    public function contarRecursosAtivos(): int
    {
        return count($this->getRecursosAtivos());
    }

    public function contarContratosAtivos(): int
    {
        return PlanoContratado::where('nome', $this->nome)->count();
    }

    public function temContratosAtivos(): bool
    {
        return $this->contarContratosAtivos() > 0;
    }

    public function isAtivo(): bool
    {
        return (bool) $this->ativo;
    }

    public function ativar(): bool
    {
        return $this->update(['ativo' => 1]);
    }

    public function inativar(): bool
    {
        return $this->update(['ativo' => 0]);
    }
}