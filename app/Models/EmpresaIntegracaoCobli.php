<?php

namespace App\Models;

use App\Domain\Auth\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class EmpresaIntegracaoCobli extends Model
{
    use HasFactory;

    protected $table = 'empresa_integracoes_cobli';

    protected $fillable = [
        'id_empresa',
        'api_key',
        'base_url',
        'ativo',
        'ultimo_teste_em',
        'ultimo_erro',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ultimo_teste_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function scopeEmpresa($query, int $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    public function scopeAtiva($query)
    {
        return $query->where('ativo', true);
    }

    public function isConfigurada(): bool
    {
        return $this->ativo && !empty($this->api_key_descriptografada);
    }

    public function getApiKeyDescriptografadaAttribute(): ?string
    {
        $valor = $this->attributes['api_key'] ?? null;

        if (!is_string($valor) || trim($valor) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($valor);
        } catch (DecryptException $exception) {
            return $valor;
        }
    }

    public function setApiKeyAttribute($value): void
    {
        if (!is_string($value) || trim($value) === '') {
            $this->attributes['api_key'] = null;
            return;
        }

        $value = trim($value);

        try {
            Crypt::decryptString($value);
            $this->attributes['api_key'] = $value;
        } catch (DecryptException $exception) {
            $this->attributes['api_key'] = Crypt::encryptString($value);
        }
    }
}