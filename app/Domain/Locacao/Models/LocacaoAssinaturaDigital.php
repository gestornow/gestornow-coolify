<?php

namespace App\Domain\Locacao\Models;

use Illuminate\Database\Eloquent\Model;

class LocacaoAssinaturaDigital extends Model
{
    protected $table = 'locacao_assinaturas_digitais';
    protected $primaryKey = 'id_assinatura';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'id_cliente',
        'id_modelo',
        'email_destinatario',
        'token',
        'status',
        'assinatura_tipo',
        'assinatura_cliente_url',
        'assinado_em',
        'solicitado_em',
        'ip_assinatura',
        'user_agent',
        'hash_documento',
        'corpo_contrato_assinado',
        'assinado_por_nome',
        'assinado_por_documento',
    ];

    protected $casts = [
        'assinado_em' => 'datetime',
        'solicitado_em' => 'datetime',
    ];

    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }
}
