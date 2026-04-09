<?php

namespace App\Domain\Locacao\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LocacaoSala extends Model
{
    use SoftDeletes;

    protected $table = 'locacao_salas';
    protected $primaryKey = 'id_sala';
    public $incrementing = true;

    protected $fillable = [
        'id_empresa',
        'id_locacao',
        'nome',
        'descricao',
        'ordem',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'ordem' => 'integer',
    ];

    /**
     * Relacionamento com locação
     */
    public function locacao()
    {
        return $this->belongsTo(Locacao::class, 'id_locacao', 'id_locacao');
    }

    /**
     * Relacionamento com produtos da sala
     */
    public function produtos()
    {
        return $this->hasMany(LocacaoProduto::class, 'id_sala', 'id_sala');
    }
}
