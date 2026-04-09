<?php

namespace App\Domain\Produto\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patrimonio extends Model
{
    use SoftDeletes, RegistraAtividade;

    protected $table = 'patrimonios';
    protected $primaryKey = 'id_patrimonio';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_produto',
        'numero_serie',
        'data_aquisicao',
        'valor_aquisicao',
        'status',
        'status_locacao',
        'ultima_manutencao',
        'proxima_manutencao',
        'observacoes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'data_aquisicao' => 'date',
        'ultima_manutencao' => 'date',
        'proxima_manutencao' => 'date',
        'valor_aquisicao' => 'decimal:2',
    ];

    /**
     * Status disponíveis
     */
    public static function statusList()
    {
        return [
            'Ativo' => 'Ativo',
            'Inativo' => 'Inativo',
            'Descarte' => 'Descarte',
        ];
    }
    
    /**
     * Status de locação disponíveis
     */
    public static function statusLocacaoList()
    {
        return [
            'Disponivel' => 'Disponível',
            'Locado' => 'Locado',
            'Em Manutencao' => 'Em Manutenção',
        ];
    }

    /**
     * Accessor para valor formatado
     */
    public function getValorAquisicaoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor_aquisicao ?? 0, 2, ',', '.');
    }

    /**
     * Accessor para valor atual formatado
     */
    public function getValorAtualFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor_atual ?? 0, 2, ',', '.');
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para buscar por código ou série
     */
    public function scopeBuscar($query, $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('codigo_patrimonio', 'like', "%{$termo}%")
              ->orWhere('numero_serie', 'like', "%{$termo}%")
              ->orWhere('descricao', 'like', "%{$termo}%");
        });
    }

    /**
     * Relacionamento com produto
     */
    public function produto()
    {
        return $this->belongsTo(Produto::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com manutenções
     */
    public function manutencoes()
    {
        return $this->hasMany(Manutencao::class, 'id_patrimonio', 'id_patrimonio');
    }

    /**
     * Relacionamento com histórico de movimentações
     */
    public function historico()
    {
        return $this->hasMany(PatrimonioHistorico::class, 'id_patrimonio', 'id_patrimonio')
            ->orderBy('data_movimentacao', 'desc');
    }

    /**
     * Relacionamento com locação atual
     */
    public function locacaoAtual()
    {
        return $this->belongsTo(\App\Domain\Locacao\Models\Locacao::class, 'id_locacao_atual', 'id_locacao');
    }

    /**
     * Verificar se está disponível para locação
     */
    public function estaDisponivel(): bool
    {
        return $this->status === 'Ativo' && 
               ($this->status_locacao === 'Disponivel' || !$this->status_locacao);
    }
}
