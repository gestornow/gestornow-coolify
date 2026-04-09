<?php

namespace App\Domain\Produto\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TabelaPreco extends Model
{
    use SoftDeletes, RegistraAtividade;

    protected $table = 'tabela_precos';
    protected $primaryKey = 'id_tabela';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_produto',
        'nome',
        'd1', 'd2', 'd3', 'd4', 'd5', 'd6', 'd7',
        'd8', 'd9', 'd10', 'd11', 'd12', 'd13', 'd14', 'd15',
        'd16', 'd17', 'd18', 'd19', 'd20', 'd21', 'd22', 'd23', 'd24', 'd25',
        'd26', 'd27', 'd28', 'd29', 'd30',
        'd60', 'd120', 'd360',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'd1' => 'decimal:2', 'd2' => 'decimal:2', 'd3' => 'decimal:2', 'd4' => 'decimal:2', 'd5' => 'decimal:2',
        'd6' => 'decimal:2', 'd7' => 'decimal:2', 'd8' => 'decimal:2', 'd9' => 'decimal:2', 'd10' => 'decimal:2',
        'd11' => 'decimal:2', 'd12' => 'decimal:2', 'd13' => 'decimal:2', 'd14' => 'decimal:2', 'd15' => 'decimal:2',
        'd16' => 'decimal:2', 'd17' => 'decimal:2', 'd18' => 'decimal:2', 'd19' => 'decimal:2', 'd20' => 'decimal:2',
        'd21' => 'decimal:2', 'd22' => 'decimal:2', 'd23' => 'decimal:2', 'd24' => 'decimal:2', 'd25' => 'decimal:2',
        'd26' => 'decimal:2', 'd27' => 'decimal:2', 'd28' => 'decimal:2', 'd29' => 'decimal:2', 'd30' => 'decimal:2',
        'd60' => 'decimal:2', 'd120' => 'decimal:2', 'd360' => 'decimal:2',
    ];

    /**
     * Tipos de período
     */
    public static function tiposPeriodo()
    {
        return [
            'diario' => 'Diário',
            'hora' => 'Por Hora',
            'periodo_fixo' => 'Período Fixo',
        ];
    }

    /**
     * Retorna o preço para um número específico de dias
     */
    public function getPrecoPorDias($dias)
    {
        $campo = 'd' . $dias;
        if (isset($this->$campo) && $this->$campo > 0) {
            return $this->$campo;
        }

        // Buscar o preço do período mais próximo
        $periodos = [1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,45,60,90,120,150,180,210,240,270,300,330,360];
        
        foreach ($periodos as $periodo) {
            if ($periodo >= $dias) {
                $campo = 'd' . $periodo;
                if (isset($this->$campo) && $this->$campo > 0) {
                    return $this->$campo;
                }
            }
        }

        return 0;
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
}
