<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PagamentoContaPagar extends Model
{
    use HasFactory;

    protected $table = 'pagamentos_contas_pagar';
    protected $primaryKey = 'id_pagamento';

    protected $fillable = [
        'id_conta_pagar',
        'id_empresa',
        'data_pagamento',
        'valor_pago',
        'id_forma_pagamento',
        'id_bancos',
        'observacoes',
        'id_usuario',
        'id_fluxo_caixa',
    ];

    protected $casts = [
        'data_pagamento' => 'date',
        'valor_pago' => 'decimal:2',
    ];

    /**
     * Relacionamento com ContasAPagar
     */
    public function conta()
    {
        return $this->belongsTo(ContasAPagar::class, 'id_conta_pagar', 'id_contas');
    }

    /**
     * Relacionamento com FormaPagamento
     */
    public function formaPagamento()
    {
        return $this->belongsTo(\App\Models\FormaPagamento::class, 'id_forma_pagamento', 'id_forma_pagamento');
    }

    /**
     * Relacionamento com Banco
     */
    public function banco()
    {
        return $this->belongsTo(\App\Models\Banco::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Relacionamento com Usuario (quem registrou)
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Usuario::class, 'id_usuario', 'id_usuario');
    }
}
