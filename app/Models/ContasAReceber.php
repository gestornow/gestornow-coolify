<?php

namespace App\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContasAReceber extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;

    protected $table = 'contas_a_receber';
    protected $primaryKey = 'id_contas';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_clientes',
        'id_fornecedores',
        'id_venda',
        'id_locacao',
        'id_bancos',
        'id_categoria_contas',
        'id_usuario',
        'descricao',
        'documento',
        'valor_total',
        'valor_pago',
        'juros',
        'multa',
        'desconto',
        'data_emissao',
        'data_vencimento',
        'data_pagamento',
        'status',
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

    // Relationships
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function cliente()
    {
        return $this->belongsTo(\App\Domain\Cliente\Models\Cliente::class, 'id_clientes', 'id_clientes');
    }

    public function fornecedor()
    {
        return $this->belongsTo(\App\Models\Fornecedor::class, 'id_fornecedores', 'id_fornecedores');
    }

    public function banco()
    {
        return $this->belongsTo(\App\Models\Banco::class, 'id_bancos', 'id_bancos');
    }

    public function categoria()
    {
        return $this->belongsTo(\App\Models\CategoriaContas::class, 'id_categoria_contas', 'id_categoria_contas');
    }

    public function formaPagamento()
    {
        return $this->belongsTo(\App\Models\FormaPagamento::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Get the pagamentos (histórico de recebimentos parciais)
     */
    public function pagamentos()
    {
        return $this->hasMany(\App\Models\PagamentoContaReceber::class, 'id_conta_receber', 'id_contas')
            ->orderBy('data_pagamento', 'desc');
    }

    // Scopes
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeVencidas($query)
    {
        return $query->where('status', '!=', 'pago')
                    ->where('data_vencimento', '<', now()->toDateString());
    }

    public function scopeAVencer($query, $dias = 7)
    {
        return $query->where('status', '!=', 'pago')
                    ->whereBetween('data_vencimento', [
                        now()->toDateString(),
                        now()->addDays($dias)->toDateString()
                    ]);
    }

    // Accessors & Helpers
    public function getValorFinalAttribute()
    {
        return $this->valor_total + $this->juros + $this->multa - $this->desconto;
    }

    public function getValorRestanteAttribute()
    {
        return $this->getValorFinalAttribute() - $this->valor_pago;
    }

    public function isVencida()
    {
        return $this->status !== 'pago' 
            && $this->status !== 'cancelado' 
            && $this->data_vencimento < now()->toDateString();
    }

    public function getStatusBadgeClass()
    {
        return match($this->status) {
            'pago' => 'bg-success',
            'pendente' => 'bg-warning',
            'parcelado' => 'bg-info',
            'cancelado' => 'bg-secondary',
            default => 'bg-secondary',
        };
    }

    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'pago' => 'Pago',
            'pendente' => 'Pendente',
            'vencido' => 'Vencido',
            'parcelado' => 'Parcelado',
            'cancelado' => 'Cancelado',
            default => ucfirst($this->status),
        };
    }

    public function isParcelada()
    {
        return !is_null($this->id_parcelamento) && !is_null($this->total_parcelas);
    }

    public function parcelas()
    {
        if (!$this->id_parcelamento) {
            return collect([]);
        }
        
        return self::where('id_parcelamento', $this->id_parcelamento)
                   ->orderBy('numero_parcela')
                   ->get();
    }

    public function recorrencias()
    {
        if (!$this->id_recorrencia) {
            return collect([]);
        }
        
        return self::where('id_recorrencia', $this->id_recorrencia)
                   ->orderBy('data_vencimento')
                   ->get();
    }

    public function getDescricaoParcelaAttribute()
    {
        if ($this->isParcelada()) {
            return $this->descricao . " ({$this->numero_parcela}/{$this->total_parcelas})";
        }
        
        return $this->descricao;
    }
}
