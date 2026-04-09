<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fornecedor extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'fornecedores';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_fornecedores';

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
        'nome',
        'cep',
        'endereco',
        'numero',
        'complemento',
        'rg_ie',
        'cpf_cnpj',
        'razao_social',
        'nome_empresa',
        'data_abertura',
        'bairro',
        'uf',
        'data_nascimento',
        'contato_nome',
        'contato_cargo',
        'telefone',
        'email',
        'prazo_medio_entrega_dias',
        'id_categoria_fornecedor',
        'banco_agencia',
        'banco_conta',
        'observacoes',
        'id_tipo_pessoa',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data_abertura' => 'date',
        'data_nascimento' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the empresa that owns the fornecedor.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Get the contas a pagar associated with the fornecedor.
     */
    public function contasAPagar()
    {
        return $this->hasMany(ContasAPagar::class, 'id_fornecedores', 'id_fornecedores');
    }

    /**
     * Scope a query to only include fornecedores from a specific empresa.
     */
    public function scopeEmpresa($query, $id_empresa)
    {
        return $query->where('id_empresa', $id_empresa);
    }

    /**
     * Scope a query to only include active fornecedores.
     */
    public function scopeAtivo($query)
    {
        return $query->where('status', 'ativo');
    }

    /**
     * Get the nome completo (nome_empresa, razao_social ou nome).
     */
    public function getNomeCompletoAttribute()
    {
        return $this->nome_empresa ?: ($this->razao_social ?: $this->nome);
    }

    /**
     * Get the status badge color.
     */
    public function getStatusBadgeClass()
    {
        return match($this->status) {
            'ativo' => 'bg-success',
            'inativo' => 'bg-secondary',
            'bloqueado' => 'bg-danger',
            default => 'bg-secondary',
        };
    }
}
