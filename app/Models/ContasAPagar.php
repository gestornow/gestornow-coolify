<?php

namespace App\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContasAPagar extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contas_a_pagar';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_contas';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_empresa',
        'id_clientes',
        'id_fornecedores',
        'id_venda',
        'id_locacao',
        'id_origem',
        'id_bancos',
        'id_categoria_contas',
        'id_usuario',
        'descricao',
        'documento',
        'boleto',
        'valor_total',
        'valor_pago',
        'juros',
        'multa',
        'desconto',
        'data_emissao',
        'data_vencimento',
        'data_pagamento',
        'status',
        'origem',
        'id_forma_pagamento',
        'observacoes',
        // Parcelamento
        'numero_parcela',
        'total_parcelas',
        'id_parcelamento',
        // Recorrência
        'tipo_recorrencia',
        'quantidade_recorrencias',
        'id_recorrencia',
        'is_recorrente',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valor_total' => 'decimal:2',
        'valor_pago' => 'decimal:2',
        'juros' => 'decimal:2',
        'multa' => 'decimal:2',
        'desconto' => 'decimal:2',
        'data_emissao' => 'date',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the empresa that owns the conta.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Get the cliente associated with the conta.
     */
    public function cliente()
    {
        return $this->belongsTo(\App\Domain\Cliente\Models\Cliente::class, 'id_clientes', 'id_clientes');
    }

    /**
     * Get the fornecedor associated with the conta.
     */
    public function fornecedor()
    {
        return $this->belongsTo(\App\Models\Fornecedor::class, 'id_fornecedores', 'id_fornecedores');
    }

    /**
     * Get the banco associated with the conta.
     */
    public function banco()
    {
        return $this->belongsTo(\App\Models\Banco::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Get the categoria associated with the conta.
     */
    public function categoria()
    {
        return $this->belongsTo(\App\Models\CategoriaContas::class, 'id_categoria_contas', 'id_categoria_contas');
    }

    /**
     * Get the forma de pagamento associated with the conta.
     */
    public function formaPagamento()
    {
        return $this->belongsTo(\App\Models\FormaPagamento::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Get the pagamentos (histórico de pagamentos parciais)
     */
    public function pagamentos()
    {
        return $this->hasMany(\App\Models\PagamentoContaPagar::class, 'id_conta_pagar', 'id_contas')
            ->orderBy('data_pagamento', 'desc');
    }

    /**
     * Scope a query to only include contas from a specific empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    /**
     * Scope a query to only include contas with a specific status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include contas vencidas.
     */
    public function scopeVencidas($query)
    {
        return $query->where('status', '!=', 'pago')
                    ->where('data_vencimento', '<', now()->toDateString());
    }

    /**
     * Scope a query to only include contas a vencer.
     */
    public function scopeAVencer($query, $dias = 7)
    {
        return $query->where('status', '!=', 'pago')
                    ->whereBetween('data_vencimento', [
                        now()->toDateString(),
                        now()->addDays($dias)->toDateString()
                    ]);
    }

    /**
     * Calculate the total amount with fees and discounts.
     */
    public function getValorFinalAttribute()
    {
        return $this->valor_total + $this->juros + $this->multa - $this->desconto;
    }

    /**
     * Calculate the remaining amount to be paid.
     */
    public function getValorRestanteAttribute()
    {
        return $this->getValorFinalAttribute() - $this->valor_pago;
    }

    /**
     * Check if the conta is overdue.
     */
    public function isVencida()
    {
        return $this->status !== 'pago' 
            && $this->status !== 'cancelado' 
            && $this->data_vencimento < now()->toDateString();
    }

    /**
     * Check if the conta is parcialmente paga.
     */
    public function isParcialmentePago()
    {
        return $this->valor_pago > 0 && $this->valor_pago < $this->valor_total;
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeClass()
    {
        // Verificar pagamento parcial primeiro
        if ($this->isParcialmentePago()) {
            return 'bg-primary';
        }
        
        return match($this->status) {
            'pago' => 'bg-success',
            'pendente' => 'bg-warning',
            'vencido' => 'bg-danger',
            'parcelado' => 'bg-info',
            'cancelado' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute()
    {
        // Verificar pagamento parcial primeiro
        if ($this->isParcialmentePago()) {
            return 'Parcialmente Pago';
        }
        
        return match($this->status) {
            'pago' => 'Pago',
            'pendente' => 'Pendente',
            'vencido' => 'Vencido',
            'parcelado' => 'Parcelado',
            'cancelado' => 'Cancelado',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if the conta is parcelada.
     */
    public function isParcelada()
    {
        return !is_null($this->id_parcelamento) && !is_null($this->total_parcelas);
    }

    /**
     * Get parcelas relacionadas (mesmo id_parcelamento).
     */
    public function parcelas()
    {
        if (!$this->id_parcelamento) {
            return collect([]);
        }
        
        return self::where('id_parcelamento', $this->id_parcelamento)
                   ->orderBy('numero_parcela')
                   ->get();
    }

    /**
     * Get contas recorrentes relacionadas.
     */
    public function recorrencias()
    {
        if (!$this->id_recorrencia) {
            return collect([]);
        }
        
        return self::where('id_recorrencia', $this->id_recorrencia)
                   ->orderBy('data_vencimento')
                   ->get();
    }

    /**
     * Get descrição da parcela.
     */
    public function getDescricaoParcelaAttribute()
    {
        if ($this->isParcelada()) {
            return $this->descricao . " ({$this->numero_parcela}/{$this->total_parcelas})";
        }
        
        return $this->descricao;
    }
}
