<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;

class LocacaoServico extends Model
{
    use RegistraAtividade;

    protected $table = 'locacao_servicos';
    protected $primaryKey = 'id_locacao_servico';
    public $incrementing = true;

    protected $fillable = [
        'id_locacao',
        'descricao',
        'tipo_item',
        'quantidade',
        'preco_unitario',
        'valor_total',
        'id_sala',
        'id_fornecedor',
        'fornecedor_nome',
        'custo_fornecedor',
        'gerar_conta_pagar',
        'conta_vencimento',
        'conta_valor',
        'conta_parcelas',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'id_sala' => 'integer',
        'id_fornecedor' => 'integer',
        'custo_fornecedor' => 'decimal:2',
        'gerar_conta_pagar' => 'boolean',
        'conta_vencimento' => 'date',
        'conta_valor' => 'decimal:2',
        'conta_parcelas' => 'integer',
    ];

    /**
     * Status disponíveis
     */
    public static function statusList()
    {
        return [
            'pendente' => 'Pendente',
            'executado' => 'Executado',
            'cancelado' => 'Cancelado',
        ];
    }

    /**
     * Accessor para valor formatado
     */
    public function getValorFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor_total ?? 0, 2, ',', '.');
    }

    /**
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }
}
