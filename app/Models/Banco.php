<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banco extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bancos';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_bancos';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_empresa',
        'nome_banco',
        'agencia',
        'conta',
        'saldo_inicial',
        'observacoes',
        'gera_boleto',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'gera_boleto' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the empresa that owns the banco.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Get the contas a pagar associated with the banco.
     */
    public function contasAPagar()
    {
        return $this->hasMany(ContasAPagar::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Get the contas a receber associated with the banco.
     */
    public function contasAReceber()
    {
        return $this->hasMany(ContasAReceber::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Get the fluxo de caixa entries associated with the banco.
     */
    public function fluxoCaixa()
    {
        return $this->hasMany(FluxoCaixa::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Get the configuração de boleto do banco.
     */
    public function boletoConfig()
    {
        return $this->hasOne(BancoBoletoConfig::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Get the boletos gerados por este banco.
     */
    public function boletos()
    {
        return $this->hasMany(Boleto::class, 'id_bancos', 'id_bancos');
    }

    /**
     * Verifica se o banco pode gerar boletos (está configurado corretamente).
     */
    public function podeGerarBoleto()
    {
        if (!$this->gera_boleto) {
            return false;
        }

        $config = $this->boletoConfig;
        if (!$config || !$config->ativo) {
            return false;
        }

        return $config->isConfiguracaoCompleta();
    }

    /**
     * Scope a query to only include bancos from a specific empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    /**
     * Get the full description with bank details.
     */
    public function getDescricaoCompletaAttribute()
    {
        $descricao = $this->nome_banco;
        
        if ($this->agencia && $this->conta) {
            $descricao .= ' (Ag: ' . $this->agencia . ' / Cc: ' . $this->conta . ')';
        }
        
        return $descricao;
    }
}
