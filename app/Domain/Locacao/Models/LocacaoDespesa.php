<?php

namespace App\Domain\Locacao\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocacaoDespesa extends Model
{
    use SoftDeletes, RegistraAtividade;

    protected $table = 'locacao_despesas';
    protected $primaryKey = 'id_locacao_despesa';
    public $incrementing = true;

    protected $fillable = [
        'id_locacao',
        'descricao',
        'tipo',
        'valor',
        'data_despesa',
        'status',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'data_despesa' => 'datetime',
        'valor' => 'decimal:2',
    ];

    /**
     * Tipos de despesa
     */
    public static function tipos()
    {
        return [
            'transporte' => 'Transporte/Frete',
            'montagem' => 'Montagem',
            'desmontagem' => 'Desmontagem',
            'seguro' => 'Seguro',
            'taxa' => 'Taxa',
            'multa' => 'Multa',
            'outros' => 'Outros',
        ];
    }

    /**
     * Status disponíveis
     */
    public static function statusList()
    {
        return [
            'pendente' => 'Pendente',
            'pago' => 'Pago',
            'cancelado' => 'Cancelado',
        ];
    }

    /**
     * Accessor para valor formatado
     */
    public function getValorFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor ?? 0, 2, ',', '.');
    }

    /**
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }
}
