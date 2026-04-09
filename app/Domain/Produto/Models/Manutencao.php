<?php

namespace App\Domain\Produto\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manutencao extends Model
{
    use SoftDeletes;

    protected $table = 'manutencoes';
    protected $primaryKey = 'id_manutencao';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_produto',
        'id_patrimonio',
        'quantidade',
        'data_manutencao',
        'data_previsao',
        'hora_manutencao',
        'hora_previsao',
        'tipo',
        'descricao',
        'status',
        'estoque_status',
        'responsavel',
        'valor',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'data_manutencao' => 'datetime',
        'data_previsao' => 'datetime',
        'estoque_status' => 'integer',
        'quantidade' => 'integer',
        'valor' => 'decimal:2',
    ];

    /**
     * Tipos de manutenção disponíveis
     */
    public static function tipos()
    {
        return [
            'preventiva' => 'Preventiva',
            'corretiva' => 'Corretiva',
            'preditiva' => 'Preditiva',
            'emergencial' => 'Emergencial',
        ];
    }

    /**
     * Status disponíveis
     */
    public static function statusList()
    {
        return [
            'em_andamento' => 'Em Manutenção',
            'concluida' => 'Concluída',
        ];
    }

    /**
     * Accessor para custo formatado
     */
    public function getCustoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->custo ?? 0, 2, ',', '.');
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
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
}
