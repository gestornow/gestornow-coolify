<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MovimentacaoEstoque extends Model
{
    protected $table = 'movimentacoes_estoque';
    protected $primaryKey = 'id_movimentacao';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_produto',
        'id_usuario',
        'tipo',
        'quantidade',
        'estoque_anterior',
        'estoque_posterior',
        'motivo',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'quantidade' => 'integer',
        'estoque_anterior' => 'integer',
        'estoque_posterior' => 'integer',
    ];

    /**
     * Tipos de movimentação
     */
    public static function tipos()
    {
        return [
            'entrada' => 'Entrada',
            'saida' => 'Saída',
        ];
    }

    /**
     * Motivos comuns de movimentação
     */
    public static function motivosComuns()
    {
        return [
            'compra' => 'Compra',
            'devolucao' => 'Devolução',
            'ajuste_inventario' => 'Ajuste de Inventário',
            'transferencia' => 'Transferência',
            'perda' => 'Perda/Avaria',
            'venda' => 'Venda',
            'locacao' => 'Locação',
            'outro' => 'Outro',
        ];
    }

    /**
     * Relacionamento com produto
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com usuário
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Registrar uma movimentação de estoque
     */
    public static function registrar($idProduto, $tipo, $quantidade, $motivo = null, $observacoes = null)
    {
        $produto = Produto::findOrFail($idProduto);
        
        $estoqueAnterior = $produto->quantidade ?? 0;
        
        if ($tipo === 'entrada') {
            $estoquePosterior = $estoqueAnterior + $quantidade;
        } else {
            $estoquePosterior = $estoqueAnterior - $quantidade;
            if ($estoquePosterior < 0) {
                throw new \Exception('Estoque insuficiente para esta operação.');
            }
        }
        
        // Criar movimentação
        $movimentacao = self::create([
            'id_empresa' => $produto->id_empresa,
            'id_produto' => $idProduto,
            'id_usuario' => Auth::id(),
            'tipo' => $tipo,
            'quantidade' => $quantidade,
            'estoque_anterior' => $estoqueAnterior,
            'estoque_posterior' => $estoquePosterior,
            'motivo' => $motivo,
            'observacoes' => $observacoes,
        ]);
        
        // Atualizar estoque do produto
        $produto->quantidade = $estoquePosterior;
        
        // Atualizar estoque_total somente em entradas (representa o total já adquirido)
        if ($tipo === 'entrada') {
            $produto->estoque_total = ($produto->estoque_total ?? 0) + $quantidade;
        }
        
        $produto->save();
        
        return $movimentacao;
    }
}
