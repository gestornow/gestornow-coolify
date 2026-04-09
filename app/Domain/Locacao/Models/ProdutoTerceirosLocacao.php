<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Domain\Produto\Models\ProdutoTerceiro;
use App\Models\Fornecedor;
use App\Models\ContasAPagar;

/**
 * Model para produtos de terceiros vinculados a uma locação
 * Tabela: produto_terceiros_locacao
 */
class ProdutoTerceirosLocacao extends Model
{
    use SoftDeletes, RegistraAtividade;

    protected $table = 'produto_terceiros_locacao';
    protected $primaryKey = 'id_produto_terceiros_locacao';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'id_produto_terceiro',    // Referência ao produto de terceiro cadastrado (opcional)
        'nome_produto_manual',     // Nome manual caso não tenha produto cadastrado
        'descricao_manual',        // Descrição manual
        'id_fornecedor',           // Fornecedor do produto
        'id_sala',                 // Sala/seção da locação
        'quantidade',
        'preco_unitario',          // Preço cobrado do cliente
        'valor_fechado',
        'custo_fornecedor',        // Custo de aquisição/aluguel do fornecedor
        'valor_total',
        'tipo_movimentacao',       // entrega, retirada
        'observacoes',
        // Campos para gerar conta a pagar
        'gerar_conta_pagar',       // Se deve gerar conta a pagar
        'conta_vencimento',        // Data de vencimento da conta
        'conta_valor',             // Valor da conta a pagar
        'conta_parcelas',          // Número de parcelas
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'quantidade' => 'integer',
        'preco_unitario' => 'decimal:2',
        'valor_fechado' => 'boolean',
        'custo_fornecedor' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'gerar_conta_pagar' => 'boolean',
        'conta_vencimento' => 'date',
        'conta_valor' => 'decimal:2',
        'conta_parcelas' => 'integer',
    ];

    /**
     * Accessor para valor formatado
     */
    public function getValorTotalFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor_total ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para nome do produto (usa produto cadastrado ou nome manual)
     */
    public function getNomeProdutoAttribute()
    {
        if ($this->produtoTerceiro) {
            return $this->produtoTerceiro->nome;
        }
        return $this->nome_produto_manual ?? 'Produto de Terceiro';
    }

    /**
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com produto de terceiro cadastrado
     */
    public function produtoTerceiro()
    {
        return $this->belongsTo(ProdutoTerceiro::class, 'id_produto_terceiro', 'id_produto_terceiro');
    }

    /**
     * Relacionamento com fornecedor
     */
    public function fornecedor()
    {
        return $this->belongsTo(Fornecedor::class, 'id_fornecedor', 'id_fornecedor');
    }

    /**
     * Relacionamento com sala da locação
     */
    public function sala()
    {
        return $this->belongsTo(LocacaoSala::class, 'id_sala', 'id_sala');
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
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Calcular valor total se não informado
            if (!$model->valor_total) {
                $model->valor_total = ($model->quantidade ?? 1) * ($model->preco_unitario ?? 0);
            }
        });
    }
}
