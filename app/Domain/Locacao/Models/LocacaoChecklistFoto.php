<?php

namespace App\Domain\Locacao\Models;

use Illuminate\Database\Eloquent\Model;

class LocacaoChecklistFoto extends Model
{
    protected $table = 'locacao_checklist_foto';
    protected $primaryKey = 'id_locacao_checklist_foto';

    protected $fillable = [
        'id_locacao_checklist',
        'id_empresa',
        'id_locacao',
        'id_produto_locacao',
        'tipo',
        'url_foto',
        'texto_watermark',
        'voltou_com_defeito',
        'alerta_avaria',
        'observacao',
        'capturado_em',
    ];

    protected $casts = [
        'voltou_com_defeito' => 'boolean',
        'alerta_avaria' => 'boolean',
        'capturado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function checklist()
    {
        return $this->belongsTo(LocacaoChecklist::class, 'id_locacao_checklist', 'id_locacao_checklist');
    }

    public function produtoLocacao()
    {
        return $this->belongsTo(LocacaoProduto::class, 'id_produto_locacao', 'id_produto_locacao');
    }
}
