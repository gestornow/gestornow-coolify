<?php

namespace App\Domain\Produto\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produto extends Model
{
    use SoftDeletes, RegistraAtividade;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'produtos';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_produto';

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
        'id_marca',
        'id_grupo',
        'id_tipo',
        'unidade_medida_id',
        'id_modelo',
        'hex_color',
        'nome',
        'descricao',
        'detalhes',
        'preco',
        'preco_reposicao',
        'preco_custo',
        'preco_venda',
        'preco_locacao',
        'altura',
        'largura',
        'profundidade',
        'peso',
        'estoque_total',
        'quantidade',
        'codigo',
        'numero_serie',
        'status',
        'foto_url',
        'foto_filename',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'preco' => 'decimal:2',
        'preco_reposicao' => 'decimal:2',
        'preco_custo' => 'decimal:2',
        'preco_venda' => 'decimal:2',
        'preco_locacao' => 'decimal:2',
        'altura' => 'decimal:2',
        'largura' => 'decimal:2',
        'profundidade' => 'decimal:2',
        'peso' => 'decimal:2',
        'estoque_total' => 'integer',
        'quantidade' => 'integer',
    ];

    /**
     * Accessor para retornar a inicial do nome do produto
     *
     * @return string
     */
    public function getInicialAttribute()
    {
        return strtoupper(substr($this->nome, 0, 1));
    }

    /**
     * Accessor para URL da foto do produto
     */
    public function getFotoUrlAttribute($value = null)
    {
        if (empty($value)) {
            return null;
        }

        $baseUrl = $this->getApiFilesBaseUrl();

        $normalizedPath = $this->normalizeFotoPath((string) $value);
        if ($normalizedPath === null) {
            return null;
        }

        if ($this->startsWith($normalizedPath, 'http://') || $this->startsWith($normalizedPath, 'https://')) {
            return $normalizedPath;
        }

        return $baseUrl . '/' . ltrim($normalizedPath, '/');
    }

    /**
     * Accessor para retornar preço formatado em BRL
     *
     * @return string
     */
    public function getPrecoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_venda, 2, ',', '.');
    }

    /**
     * Accessor para retornar preço de custo formatado em BRL
     *
     * @return string
     */
    public function getPrecoCustoFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->preco_custo, 2, ',', '.');
    }

    /**
     * Scope para filtrar por empresa
     */
    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para buscar por nome ou código
     */
    public function scopeBuscar($query, $termo)
    {
        return $query->where(function ($q) use ($termo) {
            $q->where('nome', 'like', "%{$termo}%")
              ->orWhere('codigo', 'like', "%{$termo}%")
              ->orWhere('numero_serie', 'like', "%{$termo}%");
        });
    }

    /**
     * Relacionamento com acessórios
     */
    public function acessorios()
    {
        return $this->belongsToMany(
            Acessorio::class, 
            'produto_acessorios', 
            'id_produto', 
            'id_acessorio'
        )->withPivot('quantidade', 'obrigatorio')->withTimestamps();
    }

    /**
     * Relacionamento com patrimônios
     */
    public function patrimonios()
    {
        return $this->hasMany(Patrimonio::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com manutenções
     */
    public function manutencoes()
    {
        return $this->hasMany(Manutencao::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com tabelas de preço
     */
    public function tabelasPreco()
    {
        return $this->hasMany(TabelaPreco::class, 'id_produto', 'id_produto');
    }

    /**
     * Relacionamento com locações
     */
    public function locacoes()
    {
        return $this->hasMany(\App\Domain\Locacao\Models\LocacaoProduto::class, 'id_produto', 'id_produto');
    }

    /**
     * Retorna quantidade disponível para locação
     */
    public function getQuantidadeDisponivelAttribute()
    {
        $emLocacao = $this->locacoes()
            ->whereHas('locacao', function($q) {
                $q->whereIn('status', ['reserva', 'em_andamento']);
            })
            ->sum('quantidade');
        
        return max(0, ($this->estoque_total ?? 0) - $emLocacao);
    }

    /**
     * Verifica se o produto está disponível para locação
     */
    public function estaDisponivel($quantidade = 1)
    {
        return $this->quantidade_disponivel >= $quantidade && $this->status === 'ativo';
    }

    private function normalizeFotoPath(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($this->startsWith($value, 'http://') || $this->startsWith($value, 'https://')) {
            $parsedPath = parse_url($value, PHP_URL_PATH);
            if (is_string($parsedPath)) {
                $normalizedPath = $this->normalizeLegacyProductPath($parsedPath);
                if ($normalizedPath !== $parsedPath) {
                    return $this->getApiFilesBaseUrl() . '/' . ltrim($normalizedPath, '/');
                }
            }

            return $value;
        }

        return $this->normalizeLegacyProductPath($value);
    }

    private function normalizeLegacyProductPath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($this->startsWith($path, '/api/produtos/imagens/')) {
            return '/uploads/produtos/imagens/' . ltrim(substr($path, strlen('/api/produtos/imagens/')), '/');
        }

        if ($this->startsWith($path, '/produtos/imagens/')) {
            return '/uploads/produtos/imagens/' . ltrim(substr($path, strlen('/produtos/imagens/')), '/');
        }

        return $path;
    }

    private function startsWith(string $value, string $prefix): bool
    {
        return strpos($value, $prefix) === 0;
    }

    private function getApiFilesBaseUrl(): string
    {
        $baseUrl = (string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com'));
        $baseUrl = rtrim(trim($baseUrl), '/');
        return str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);
    }
}
