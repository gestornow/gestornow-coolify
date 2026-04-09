<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoletoHistorico extends Model
{
    use HasFactory;

    protected $table = 'boletos_historico';
    protected $primaryKey = 'id_historico';
    public $incrementing = true;
    public $timestamps = false;

    protected $fillable = [
        'id_boleto',
        'id_empresa',
        'tipo',
        'conteudo',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    const TIPO_WEBHOOK = 'webhook';
    const TIPO_CONSULTA = 'consulta';
    const TIPO_ERRO = 'erro';
    const TIPO_GERACAO = 'geracao';

    /**
     * Get the boleto associado.
     */
    public function boleto()
    {
        return $this->belongsTo(Boleto::class, 'id_boleto', 'id_boleto');
    }

    /**
     * Get the empresa associada.
     */
    public function empresa()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Scope por tipo.
     */
    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Decodifica o conteúdo se for JSON.
     */
    public function getConteudoDecodificadoAttribute()
    {
        $decoded = json_decode($this->conteudo, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->conteudo;
    }

    /**
     * Registra um histórico.
     */
    public static function registrar($idBoleto, $idEmpresa, $tipo, $conteudo)
    {
        return self::create([
            'id_boleto' => $idBoleto,
            'id_empresa' => $idEmpresa,
            'tipo' => $tipo,
            'conteudo' => is_array($conteudo) ? json_encode($conteudo) : $conteudo,
            'created_at' => now(),
        ]);
    }
}
