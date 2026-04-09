<?php

namespace App\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FluxoCaixa extends Model
{
    use HasFactory, RegistraAtividade;

    protected $table = 'fluxo_caixa';
    protected $primaryKey = 'id_fluxo';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'tipo',
        'descricao',
        'valor',
        'data_movimentacao',
        'id_conta_pagar',
        'id_conta_receber',
        'id_bancos',
        'id_categoria_fluxo',
        'id_forma_pagamento',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_movimentacao' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'id_bancos', 'id_bancos');
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaContas::class, 'id_categoria_fluxo', 'id_categoria_contas');
    }

    public function formaPagamento()
    {
        return $this->belongsTo(FormaPagamento::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    public function contaPagar()
    {
        return $this->belongsTo(ContasAPagar::class, 'id_conta_pagar', 'id_contas');
    }

    public function contaReceber()
    {
        return $this->belongsTo(ContasAReceber::class, 'id_conta_receber', 'id_contas');
    }
}
