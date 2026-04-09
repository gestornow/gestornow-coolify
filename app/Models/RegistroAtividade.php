<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistroAtividade extends Model
{
    use HasFactory;

    protected $table = 'registro_atividades';
    protected $primaryKey = 'id_registro';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'id_empresa',
        'id_usuario',
        'nome_responsavel',
        'email_responsavel',
        'acao',
        'descricao',
        'entidade_tipo',
        'entidade_id',
        'entidade_label',
        'valor',
        'contexto',
        'antes',
        'depois',
        'ip',
        'origem',
        'icone',
        'cor',
        'tags',
        'ocorrido_em',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'contexto' => 'array',
        'antes' => 'array',
        'depois' => 'array',
        'tags' => 'array',
        'ocorrido_em' => 'datetime',
    ];
}
