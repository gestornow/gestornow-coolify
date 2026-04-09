<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Produto\Models\Produto;
use App\Domain\Produto\Models\Patrimonio;
use App\Domain\Produto\Models\TabelaPreco;
use App\Models\Fornecedor;

class LocacaoProduto extends Model
{
    use SoftDeletes, RegistraAtividade;

    protected $table = 'produto_locacao';
    protected $primaryKey = 'id_produto_locacao';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'id_produto',
        'id_patrimonio',
        'id_sala',
        'id_tabela_preco',
        // Valores
        'preco_unitario',
        'preco_total',
        'quantidade',
        // Período específico do produto
        'data_inicio',
        'hora_inicio',
        'data_fim',
        'hora_fim',
        'data_contrato',
        'data_contrato_fim',
        'hora_contrato',
        'hora_contrato_fim',
        // Tipo de cobrança e configurações
        'tipo_cobranca',      // diaria, fechado
        'tipo_movimentacao',  // entrega, retirada
        'status_retorno',     // pendente, devolvido, avariado, extraviado
        'estoque_status',     // 0 sem mov., 1 saída registrada, 2 retorno registrado
        'valor_fechado',      // boolean - se é valor fechado (não multiplica por dias)
        'voltou_com_defeito',
        'quantidade_com_defeito',
        'observacao_defeito',
        // Observações
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'data_inicio' => 'date',
        'data_fim' => 'date',
        'data_contrato' => 'date',
        'data_contrato_fim' => 'date',
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2',
        'preco_total' => 'decimal:2',
        'valor_fechado' => 'boolean',
        'estoque_status' => 'integer',
        'voltou_com_defeito' => 'boolean',
        'quantidade_com_defeito' => 'integer',
    ];

    /**
     * Status disponíveis
     */
    public static function statusList()
    {
        return [
            'reservado' => 'Reservado',
            'entregue' => 'Entregue',
            'devolvido' => 'Devolvido',
            'avariado' => 'Avariado',
            'perdido' => 'Perdido',
        ];
    }

    /**
     * Accessor para valor formatado
     */
    public function getValorTotalFormatadoAttribute()
    {
        $valor = $this->preco_total ?? ($this->preco_unitario * ($this->quantidade ?? 1));
        return 'R$ ' . number_format($valor ?? 0, 2, ',', '.');
    }

    /**
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com produto
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com patrimônio
     */
    public function patrimonio()
    {
        return $this->belongsTo(Patrimonio::class, 'id_patrimonio', 'id_patrimonio');
    }

    /**
     * Relacionamento com tabela de preço
     */
    public function tabelaPreco()
    {
        return $this->belongsTo(TabelaPreco::class, 'id_tabela_preco', 'id_tabela');
    }

    /**
     * Relacionamento com sala
     */
    public function sala()
    {
        return $this->belongsTo(LocacaoSala::class, 'id_sala', 'id_sala');
    }

    /**
     * Relacionamento com fornecedor (quando aplicavel)
     */
    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'id_fornecedor', 'id_fornecedores');
    }

    /**
     * Status de retorno disponíveis
     */
    public static function statusRetornoList()
    {
        return [
            'pendente' => 'Pendente',
            'devolvido' => 'Devolvido',
            'avariado' => 'Avariado',
            'extraviado' => 'Extraviado',
        ];
    }

    /**
     * Tipos de movimentação
     */
    public static function tiposMovimentacao()
    {
        return [
            'entrega' => 'Entrega',
            'retirada' => 'Retirada',
        ];
    }

    /**
     * Tipos de cobrança disponíveis
     */
    public static function tiposCobranca()
    {
        return [
            'diaria' => 'Valor da Diária (multiplica pelos dias)',
            'fechado' => 'Valor Fechado pelo Período',
        ];
    }

    /**
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Garantir compatibilidade: preencher preco_total
            $unit = $model->preco_unitario ?? $model->valor_unitario ?? 0;
            $model->preco_unitario = $unit;
            $model->preco_total = ($model->quantidade ?? 1) * $unit;
        });
    }
}
