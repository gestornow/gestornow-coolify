<?php

namespace App\Models;

use App\Domain\Auth\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssinaturaPlanoPagamento extends Model
{
    use HasFactory;

    protected $table = 'assinaturas_planos_pagamentos';

    protected $fillable = [
        'id_assinatura_plano',
        'id_empresa',
        'id_plano',
        'id_plano_contratado',
        'tipo_cobranca',
        'competencia',
        'metodo_pagamento',
        'asaas_payment_id',
        'asaas_invoice_url',
        'asaas_bank_slip_url',
        'asaas_pix_qr_code',
        'asaas_pix_copy_paste',
        'valor',
        'data_vencimento',
        'data_pagamento',
        'status',
        'json_resposta',
        'json_webhook',
        'tentativas',
        'observacoes',
    ];

    protected $casts = [
        'competencia' => 'date',
        'valor' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'datetime',
        'tentativas' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const TIPO_ADESAO = 'adesao';
    public const TIPO_MENSALIDADE = 'mensalidade';

    public const STATUS_GERADO = 'gerado';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_PAGO = 'pago';
    public const STATUS_VENCIDO = 'vencido';
    public const STATUS_CANCELADO = 'cancelado';
    public const STATUS_FALHOU = 'falhou';

    public function assinatura(): BelongsTo
    {
        return $this->belongsTo(AssinaturaPlano::class, 'id_assinatura_plano', 'id');
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'id_plano', 'id_plano');
    }

    public function planoContratado(): BelongsTo
    {
        return $this->belongsTo(PlanoContratado::class, 'id_plano_contratado', 'id');
    }

    public function getPaymentUrlAttribute(): ?string
    {
        return $this->asaas_invoice_url ?: $this->asaas_bank_slip_url;
    }
}
