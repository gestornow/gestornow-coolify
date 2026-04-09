<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;

class PatrimonioHistorico extends Model
{
    protected $table = 'patrimonio_historico';
    protected $primaryKey = 'id_historico';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_patrimonio',
        'id_produto',
        'id_locacao',
        'id_cliente',
        'tipo_movimentacao',
        'status_anterior',
        'status_novo',
        'data_movimentacao',
        'local_origem',
        'local_destino',
        'observacoes',
        'id_usuario',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'data_movimentacao' => 'datetime',
    ];

    /**
     * Tipos de movimentação disponíveis
     */
    public static function tiposMovimentacao()
    {
        return [
            'entrada_estoque' => 'Entrada no Estoque',
            'saida_locacao' => 'Saída para Locação',
            'retorno_locacao' => 'Retorno de Locação',
            'manutencao' => 'Manutenção',
            'transferencia' => 'Transferência',
            'baixa' => 'Baixa',
            'avaria' => 'Avaria',
            'extravio' => 'Extravio',
        ];
    }

    /**
     * Cores para badges
     */
    public static function tiposCores()
    {
        return [
            'entrada_estoque' => 'success',
            'saida_locacao' => 'primary',
            'retorno_locacao' => 'info',
            'manutencao' => 'warning',
            'transferencia' => 'secondary',
            'baixa' => 'danger',
            'avaria' => 'danger',
            'extravio' => 'dark',
        ];
    }

    /**
     * Relacionamento com patrimônio
     */
    public function patrimonio()
    {
        return $this->belongsTo(Patrimonio::class, 'id_patrimonio', 'id_patrimonio');
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
