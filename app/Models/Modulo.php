<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
{
    use HasFactory;

    protected $table = 'modulos';
    protected $primaryKey = 'id_modulo';
    public $timestamps = false; // A tabela não possui created_at e updated_at

    protected $fillable = [
        'nome',
        'id_modulo_pai',
        'descricao',
        'icone',
        'rota',
        'ordem',
        'categoria',
        'ativo',
        'tem_submodulos',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
        'tem_submodulos' => 'boolean',
    ];

    protected $appends = ['rota_url'];

    // Definir a chave para route model binding
    public function getRouteKeyName()
    {
        return 'id_modulo';
    }

    // Relacionamentos
    public function planosModulos(): HasMany
    {
        return $this->hasMany(PlanoModulo::class, 'id_modulo', 'id_modulo');
    }

    public function planosContratadosModulos(): HasMany
    {
        return $this->hasMany(PlanoContratadoModulo::class, 'id_modulo', 'id_modulo');
    }

    // Relacionamento com módulo pai
    public function moduloPai()
    {
        return $this->belongsTo(Modulo::class, 'id_modulo_pai', 'id_modulo');
    }

    // Relacionamento com submódulos
    public function submodulos()
    {
        return $this->hasMany(Modulo::class, 'id_modulo_pai', 'id_modulo')->orderBy('ordem');
    }

    // Scopes
    public function scopeAtivos($query)
    {
        return $query->where('ativo', 1);
    }

    /**
     * Compatibilidade retroativa: alguns controllers usam Modulo::ativo()
     * (singular). Mantemos o scope original `scopeAtivos` e adicionamos
     * `scopeAtivo` como alias para evitar BadMethodCallException.
     */
    public function scopeAtivo($query)
    {
        return $this->scopeAtivos($query);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem', 'asc')->orderBy('nome', 'asc');
    }

    // Scope para módulos principais (sem pai)
    public function scopePrincipais($query)
    {
        return $query->whereNull('id_modulo_pai');
    }

    // Scope para submódulos de um módulo específico
    public function scopeSubmodulosDe($query, $idModuloPai)
    {
        return $query->where('id_modulo_pai', $idModuloPai);
    }

    // Helpers
    public function isAtivo(): bool
    {
        return (bool) $this->ativo;
    }

    // Verificar se é módulo principal (sem pai)
    public function isPrincipal(): bool
    {
        return is_null($this->id_modulo_pai);
    }

    // Verificar se é submódulo
    public function isSubmodulo(): bool
    {
        return !is_null($this->id_modulo_pai);
    }

    // Verificar se tem submódulos
    public function temSubmodulos(): bool
    {
        return $this->submodulos()->count() > 0;
    }

    // Pegar caminho completo (Pai > Filho)
    public function getCaminhoCompleto(): string
    {
        if ($this->isPrincipal()) {
            return $this->nome;
        }
        
        $caminho = [$this->nome];
        $moduloAtual = $this;
        
        while ($moduloAtual->moduloPai) {
            $moduloAtual = $moduloAtual->moduloPai;
            array_unshift($caminho, $moduloAtual->nome);
        }
        
        return implode(' > ', $caminho);
    }

    // Pegar nível de profundidade (0 = principal, 1 = submódulo)
    public function getNivel(): int
    {
        $nivel = 0;
        $moduloAtual = $this;
        
        while ($moduloAtual->moduloPai) {
            $nivel++;
            $moduloAtual = $moduloAtual->moduloPai;
        }
        
        return $nivel;
    }

    /**
     * Obter a URL da rota
     * Retorna a rota como está, pois deve ser sempre uma URL válida
     */
    public function getRotaUrlAttribute(): ?string
    {
        return $this->rota;
    }

    // Módulos padrão do sistema
    public static function getModulosPadrao(): array
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
}