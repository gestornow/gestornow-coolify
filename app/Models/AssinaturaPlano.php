<?php

namespace App\Models;

use App\Domain\Auth\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssinaturaPlano extends Model
{
    use HasFactory;

    protected $table = 'assinaturas_planos';

    protected $fillable = [
        'id_empresa',
        'id_plano',
        'id_plano_contratado',
        'origem',
        'status',
        'metodo_adesao',
        'metodo_mensal',
        'asaas_customer_id',
        'asaas_subscription_id',
        'proxima_cobranca_em',
        'ultimo_pagamento_em',
        'inadimplente_desde',
        'bloqueada_por_inadimplencia',
        'observacoes',
        'cancelamento_solicitado_em',
        'cancelamento_efetivo_em',
        'motivo_cancelamento',
    ];

    protected $casts = [
        'proxima_cobranca_em' => 'date',
        'ultimo_pagamento_em' => 'datetime',
        'inadimplente_desde' => 'date',
        'bloqueada_por_inadimplencia' => 'boolean',
        'cancelamento_solicitado_em' => 'datetime',
        'cancelamento_efetivo_em' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_PENDENTE_PAGAMENTO = 'pendente_pagamento';
    public const STATUS_ONBOARDING_DADOS = 'onboarding_dados';
    public const STATUS_ONBOARDING_CONTRATO = 'onboarding_contrato';
    public const STATUS_ATIVA = 'ativa';
    public const STATUS_CANCELAMENTO_AGENDADO = 'cancelamento_agendado';
    public const STATUS_SUSPENSA = 'suspensa';
    public const STATUS_CANCELADA = 'cancelada';

    public const ORIGEM_DASHBOARD = 'dashboard';
    public const ORIGEM_COMERCIAL = 'comercial';

    public const METODO_BOLETO = 'BOLETO';
    public const METODO_PIX = 'PIX';
    public const METODO_CREDIT_CARD = 'CREDIT_CARD';
    public const METODO_DEBIT_CARD = 'DEBIT_CARD';

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

    public function pagamentos(): HasMany
    {
        return $this->hasMany(AssinaturaPlanoPagamento::class, 'id_assinatura_plano', 'id');
    }

    public function scopeAtivas($query)
    {
        return $query->whereIn('status', [
            self::STATUS_ATIVA,
            self::STATUS_ONBOARDING_DADOS,
            self::STATUS_ONBOARDING_CONTRATO,
            self::STATUS_CANCELAMENTO_AGENDADO,
            self::STATUS_SUSPENSA,
            self::STATUS_PENDENTE_PAGAMENTO,
        ]);
    }

    public function isAtiva(): bool
    {
        return in_array($this->status, [
            self::STATUS_ATIVA,
            self::STATUS_ONBOARDING_DADOS,
            self::STATUS_ONBOARDING_CONTRATO,
            self::STATUS_CANCELAMENTO_AGENDADO,
        ], true);
    }
}
