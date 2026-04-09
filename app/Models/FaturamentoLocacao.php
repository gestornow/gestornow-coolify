<?php

namespace App\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FaturamentoLocacao extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;

    protected $table = 'faturamento_locacoes';
    protected $primaryKey = 'id_faturamento_locacao';

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'id_usuario',
        'numero_fatura',
        'id_cliente',
        'id_conta_receber',
        'id_grupo_faturamento',
        'descricao',
        'valor_total',
        'data_faturamento',
        'data_vencimento',
        'status',
        'origem',
        'observacoes',
    ];

    protected $casts = [
        'valor_total' => 'decimal:2',
        'data_faturamento' => 'date',
        'data_vencimento' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function locacao()
    {
        return $this->belongsTo(\App\Domain\Locacao\Models\Locacao::class, 'id_locacao', 'id_locacao');
    }

    public function contaReceber()
    {
        return $this->belongsTo(\App\Models\ContasAReceber::class, 'id_conta_receber', 'id_contas');
    }

    public function cliente()
    {
        return $this->belongsTo(\App\Domain\Cliente\Models\Cliente::class, 'id_cliente', 'id_clientes');
    }

    /**
     * Gera o próximo número de fatura sequencial para a empresa
     * Cada empresa tem sua própria sequência independente começando do 1
     */
    public static function gerarProximoNumeroFatura(int $idEmpresa): int
    {
        $ultimaFatura = self::withTrashed()
            ->where('id_empresa', $idEmpresa)
            ->whereNotNull('numero_fatura')
            ->orderByRaw('CAST(numero_fatura AS UNSIGNED) DESC')
            ->lockForUpdate()
            ->first();

        if ($ultimaFatura && !empty($ultimaFatura->numero_fatura)) {
            return (int) $ultimaFatura->numero_fatura + 1;
        }

        return 1;
    }
}
