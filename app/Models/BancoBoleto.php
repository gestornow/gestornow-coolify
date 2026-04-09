<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BancoBoleto extends Model
{
    use HasFactory;

    protected $table = 'bancos_boleto';
    protected $primaryKey = 'id_banco_boleto';
    public $incrementing = true;

    protected $fillable = [
        'nome',
        'codigo_banco',
        'ativo',
        'requer_certificado',
        'requer_chave',
        'requer_client_id',
        'requer_client_secret',
        'requer_api_key',
        'requer_token',
        'requer_convenio',
        'requer_carteira',
        'instrucoes',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'requer_certificado' => 'boolean',
        'requer_chave' => 'boolean',
        'requer_client_id' => 'boolean',
        'requer_client_secret' => 'boolean',
        'requer_api_key' => 'boolean',
        'requer_token' => 'boolean',
        'requer_convenio' => 'boolean',
        'requer_carteira' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the configurações associated with this banco de boleto.
     */
    public function configuracoes()
    {
        return $this->hasMany(BancoBoletoConfig::class, 'id_banco_boleto', 'id_banco_boleto');
    }

    /**
     * Scope para bancos ativos.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Retorna array com os campos requeridos por esse banco.
     */
    public function getCamposRequeridosAttribute()
    {
        $campos = [];
        
        if ($this->requer_certificado) $campos[] = 'certificado';
        if ($this->requer_chave) $campos[] = 'chave';
        if ($this->requer_client_id) $campos[] = 'client_id';
        if ($this->requer_client_secret) $campos[] = 'client_secret';
        if ($this->requer_api_key) $campos[] = 'api_key';
        if ($this->requer_token) $campos[] = 'token';
        if ($this->requer_convenio) $campos[] = 'convenio';
        if ($this->requer_carteira) $campos[] = 'carteira';
        
        return $campos;
    }
}
