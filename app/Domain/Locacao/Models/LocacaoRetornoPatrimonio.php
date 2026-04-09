<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Produto\Models\Patrimonio;

class LocacaoRetornoPatrimonio extends Model
{
    use RegistraAtividade;

    protected $table = 'locacao_retorno_patrimonios';
    protected $primaryKey = 'id_retorno';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'id_produto_locacao',
        'id_patrimonio',
        'data_retorno',
        'status_retorno',
        'observacoes_retorno',
        'foto_retorno',
        'id_usuario',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'data_retorno' => 'datetime',
    ];

    /**
     * Status de retorno disponíveis
     */
    public static function statusList()
    {
        return [
            'normal' => 'Normal',
            'avariado' => 'Avariado',
            'extraviado' => 'Extraviado',
        ];
    }

    /**
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com produto da locação
     */
    public function produtoLocacao()
    {
        return $this->belongsTo(LocacaoProduto::class, 'id_produto_locacao', 'id_produto_locacao');
    }

    /**
     * Relacionamento com patrimônio
     */
    public function patrimonio()
    {
        return $this->belongsTo(Patrimonio::class, 'id_patrimonio', 'id_patrimonio');
    }

    /**
     * Relacionamento com usuário
     */
    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario', 'id_usuario');
    }
}
