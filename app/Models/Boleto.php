<?php

namespace App\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Boleto extends Model
{
    use HasFactory, RegistraAtividade;

    protected $table = 'boletos';
    protected $primaryKey = 'id_boleto';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_conta_receber',
        'id_bancos',
        'id_banco_boleto',
        'codigo_solicitacao',
        'nosso_numero',
        'linha_digitavel',
        'codigo_barras',
        'valor_nominal',
        'valor_pago',
        'data_emissao',
        'data_vencimento',
        'data_pagamento',
        'status',
        'situacao_banco',
        'url_pdf',
        'json_resposta',
        'json_webhook',
    ];

    protected $casts = [
        'valor_nominal' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
        'data_pagamento' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    const STATUS_GERADO = 'gerado';
    const STATUS_PENDENTE = 'pendente';
    const STATUS_PAGO = 'pago';
    const STATUS_VENCIDO = 'vencido';
    const STATUS_CANCELADO = 'cancelado';
    const STATUS_PROTESTADO = 'protestado';

    /**
     * Get the empresa associada.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Get the conta a receber associada.
     */
    public function contaAReceber()
    {
        return $this->belongsTo(ContasAReceber::class, 'id_conta_receber', 'id_contas');
    }

    /**
     * Get the banco associado.
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
     * Get the histórico do boleto.
     */
    public function historicos()
    {
        return $this->hasMany(BoletoHistorico::class, 'id_boleto', 'id_boleto');
    }

    /**
     * Scope por empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    /**
     * Scope por status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para boletos pendentes.
     */
    public function scopePendentes($query)
    {
        return $query->whereIn('status', [self::STATUS_GERADO, self::STATUS_PENDENTE]);
    }

    /**
     * Scope para boletos vencidos (não pagos e vencimento passou).
     */
    public function scopeVencidos($query)
    {
        return $query->whereNotIn('status', [self::STATUS_PAGO, self::STATUS_CANCELADO])
                     ->where('data_vencimento', '<', now()->toDateString());
    }

    /**
     * Verifica se o boleto está vencido.
     */
    public function isVencido()
    {
        return !in_array($this->status, [self::STATUS_PAGO, self::STATUS_CANCELADO])
            && $this->data_vencimento < now()->toDateString();
    }

    /**
     * Verifica se o boleto foi pago.
     */
    public function isPago()
    {
        return $this->status === self::STATUS_PAGO;
    }

    /**
     * Retorna classe CSS do badge de status.
     */
    public function getStatusBadgeClass()
    {
        return match($this->status) {
            self::STATUS_PAGO => 'bg-success',
            self::STATUS_PENDENTE, self::STATUS_GERADO => 'bg-warning',
            self::STATUS_VENCIDO => 'bg-danger',
            self::STATUS_CANCELADO => 'bg-secondary',
            self::STATUS_PROTESTADO => 'bg-dark',
            default => 'bg-secondary',
        };
    }

    /**
     * Retorna label do status.
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_GERADO => 'Gerado',
            self::STATUS_PENDENTE => 'Pendente',
            self::STATUS_PAGO => 'Pago',
            self::STATUS_VENCIDO => 'Vencido',
            self::STATUS_CANCELADO => 'Cancelado',
            self::STATUS_PROTESTADO => 'Protestado',
            default => ucfirst($this->status),
        };
    }

    /**
     * Decodifica o JSON de resposta.
     */
    public function getRespostaDecodificadaAttribute()
    {
        return $this->json_resposta ? json_decode($this->json_resposta, true) : null;
    }

    /**
     * Decodifica o JSON do webhook.
     */
    public function getWebhookDecodificadoAttribute()
    {
        return $this->json_webhook ? json_decode($this->json_webhook, true) : null;
    }
}
