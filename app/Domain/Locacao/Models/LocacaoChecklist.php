<?php

namespace App\Domain\Locacao\Models;

use Illuminate\Database\Eloquent\Model;

class LocacaoChecklist extends Model
{
    protected $table = 'locacao_checklist';
    protected $primaryKey = 'id_locacao_checklist';

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'tipo',
        'status',
        'assinatura_base64',
        'assinado_por',
        'assinado_em',
        'possui_avaria',
        'observacoes_gerais',
    ];

    protected $casts = [
        'possui_avaria' => 'boolean',
        'assinado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }

    public function fotos()
    {
        return $this->hasMany(LocacaoChecklistFoto::class, 'id_locacao_checklist', 'id_locacao_checklist');
    }
}
