<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanoContratadoModulo extends Model
{
    use HasFactory;

    protected $table = 'planos_contratados_modulos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_plano_contratado',
        'id_modulo',
        'limite',
        'ativo',
    ];

    protected $casts = [
        'limite' => 'integer',
        'ativo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relacionamentos
    public function planoContratado(): BelongsTo
    {
        return $this->belongsTo(PlanoContratado::class, 'id_plano_contratado', 'id');
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'id_modulo', 'id_modulo');
    }

    // Scopes
    public function scopeAtivos($query)
    {
        return $query->where('ativo', 1);
    }

    public function scopeInativos($query)
    {
        return $query->where('ativo', 0);
    }

    public function scopePorNomeModulo($query, string $nomeModulo)
    {
        return $query->where('nome_modulo', $nomeModulo);
    }

    // Helpers
    public function temLimite(): bool
    {
        return !is_null($this->limite) && $this->limite > 0;
    }

    public function getLimiteFormatado(): string
    {
        if (!$this->temLimite()) {
            return 'Ilimitado';
        }
        
        return number_format($this->limite, 0, ',', '.');
    }

    public function isAtivo(): bool
    {
        return $this->ativo === 1;
    }

    public function ativar(): bool
    {
        return $this->update(['ativo' => 1]);
    }

    public function desativar(): bool
    {
        return $this->update(['ativo' => 0]);
    }

    public function atualizarLimite(?int $novoLimite): bool
    {
        return $this->update(['limite' => $novoLimite]);
    }

    // Helpers estáticos
    public static function getModulosDisponiveis(): array
    {
        return [
            'produtos' => 'Produtos',
            'clientes' => 'Clientes',
            'fornecedores' => 'Fornecedores',
            'vendas' => 'Vendas',
            'compras' => 'Compras',
            'estoque' => 'Estoque',
            'financeiro' => 'Financeiro',
            'relatorios' => 'Relatórios',
            'bancos' => 'Bancos',
            'assinatura_digital' => 'Assinatura Digital',
            'contratos' => 'Contratos',
            'faturas' => 'Faturas',
            'nfe' => 'NFe',
            'nfce' => 'NFCe',
            'usuarios' => 'Usuários',
        ];
    }

    public function getNomeModuloFormatado(): string
    {
        $modulos = self::getModulosDisponiveis();
        return $modulos[$this->nome_modulo] ?? $this->nome_modulo;
    }
}