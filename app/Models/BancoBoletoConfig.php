<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BancoBoletoConfig extends Model
{
    use HasFactory;

    protected $table = 'banco_boleto_config';
    protected $primaryKey = 'id_config';
    public $incrementing = true;

    protected $fillable = [
        'id_bancos',
        'id_banco_boleto',
        'id_empresa',
        'client_id',
        'client_secret',
        'api_key',
        'token',
        'convenio',
        'carteira',
        'arquivo_certificado',
        'arquivo_chave',
        'juros_mora',
        'multa_atraso',
        'dias_protesto',
        'instrucao_1',
        'instrucao_2',
        'webhook_ativo',
        'ativo',
    ];

    protected $casts = [
        'juros_mora' => 'decimal:2',
        'multa_atraso' => 'decimal:2',
        'dias_protesto' => 'integer',
        'webhook_ativo' => 'boolean',
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Campos sensíveis que devem ser ocultados em retornos.
     */
    protected $hidden = [
        'client_secret',
        'api_key',
        'token',
    ];

    /**
     * Get the banco (conta bancária) associado.
     */
    public function banco()
    {
        return $this->belongsTo(Banco::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Get the banco de boleto (integração) associado.
     */
    public function bancoBoleto()
    {
        return $this->belongsTo(BancoBoleto::class, 'id_banco_boleto', 'id_banco_boleto');
    }

    /**
     * Get the empresa associada.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Scope por empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    /**
     * Scope para configurações ativas.
     */
    public function scopeAtivo($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Verifica se a configuração está completa para gerar boletos.
     */
    public function isConfiguracaoCompleta()
    {
        $bancoBoleto = $this->bancoBoleto;
        
        if (!$bancoBoleto) {
            return false;
        }

        if ($bancoBoleto->requer_certificado && !$this->arquivo_certificado) {
            return false;
        }
        if ($bancoBoleto->requer_chave && !$this->arquivo_chave) {
            return false;
        }
        if ($bancoBoleto->requer_client_id && !$this->client_id) {
            return false;
        }
        if ($bancoBoleto->requer_client_secret && !$this->client_secret) {
            return false;
        }
        if ($bancoBoleto->requer_api_key && !$this->api_key) {
            return false;
        }
        if ($bancoBoleto->requer_token && !$this->token) {
            return false;
        }
        if ($bancoBoleto->requer_convenio && !$this->convenio) {
            return false;
        }
        if ($bancoBoleto->requer_carteira && !$this->carteira) {
            return false;
        }

        return true;
    }

    /**
     * Retorna o caminho completo do arquivo de certificado.
     */
    public function getCaminhoCompletoCertificadoAttribute()
    {
        if (!$this->arquivo_certificado) {
            return null;
        }
        return storage_path('app/boletos/certificados/' . $this->arquivo_certificado);
    }

    /**
     * Retorna o caminho completo do arquivo de chave.
     */
    public function getCaminhoCompletoChaveAttribute()
    {
        if (!$this->arquivo_chave) {
            return null;
        }
        return storage_path('app/boletos/certificados/' . $this->arquivo_chave);
    }
}
