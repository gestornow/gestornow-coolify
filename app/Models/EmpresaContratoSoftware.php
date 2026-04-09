<?php

namespace App\Models;

use App\Domain\Auth\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpresaContratoSoftware extends Model
{
    use HasFactory;

    protected $table = 'empresa_contratos_software';

    protected $fillable = [
        'id_empresa',
        'versao_contrato',
        'titulo_contrato',
        'corpo_contrato',
        'hash_documento',
        'assinatura_base64',
        'assinado_por_nome',
        'assinado_por_documento',
        'assinatura_ip',
        'assinado_em',
        'status',
    ];

    protected $casts = [
        'assinado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public const STATUS_ASSINADO = 'assinado';
    public const STATUS_REVOGADO = 'revogado';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }
}
