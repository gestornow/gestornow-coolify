<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;

class ProdutoHistorico extends Model
{
    protected $table = 'produto_historico';
    protected $primaryKey = 'id_historico';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_produto',
        'id_locacao',
        'id_cliente',
        'tipo_movimentacao',
        'quantidade',
        'estoque_anterior',
        'estoque_novo',
        'data_movimentacao',
        'motivo',
        'observacoes',
        'id_usuario',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'data_movimentacao' => 'datetime',
        'quantidade' => 'integer',
        'estoque_anterior' => 'integer',
        'estoque_novo' => 'integer',
    ];

    /**
     * Tipos de movimentação disponíveis
     */
    public static function tiposMovimentacao()
    {
        return [
            'entrada' => 'Entrada',
            'saida' => 'Saída',
            'reserva' => 'Reserva',
            'retorno' => 'Retorno',
            'ajuste' => 'Ajuste',
            'transferencia' => 'Transferência',
        ];
    }

    /**
     * Cores para badges
     */
    public static function tiposCores()
    {
        return [
            'entrada' => 'success',
            'saida' => 'danger',
            'reserva' => 'warning',
            'retorno' => 'info',
            'ajuste' => 'secondary',
            'transferencia' => 'primary',
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
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(\App\Domain\Locacao\Models\Locacao::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com cliente
     */
    public function cliente()
    {
        return $this->belongsTo(\App\Domain\Cliente\Models\Cliente::class, 'id_cliente', 'id_clientes');
    }

    /**
     * Relacionamento com usuário
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Scope para empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Registrar movimentação
     */
    public static function registrar(array $dados)
    {
        $dados['data_movimentacao'] = $dados['data_movimentacao'] ?? now();
        return self::create($dados);
    }
}
