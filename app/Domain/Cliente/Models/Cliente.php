<?php

namespace App\Domain\Cliente\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\ActivityLog\Traits\RegistraAtividade;
use App\Domain\Auth\Models\Empresa;

class Cliente extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;

    protected ?string $fotoUrlExterna = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'clientes';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_clientes';

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
        'id_filial',
        'nome',
        'cep',
        'endereco',
        'numero',
        'complemento',
        'rg_ie',
        'cpf_cnpj',
        'razao_social',
        'bairro',
        'cidade',
        'uf',
        'email',
        'endereco_entrega',
        'numero_entrega',
        'complemento_entrega',
        'cep_entrega',
        'telefone',
        'data_nascimento',
        'status',
        'id_tipo_pessoa',
        'foto',
        'nomeImagemCliente',
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
        'data_nascimento' => 'date',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'foto_url',
    ];

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'id_clientes';
    }

    /**
     * Relacionamento: Um cliente pertence a uma empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    /**
     * Relacionamento: Um cliente pode pertencer a uma filial
     */
    public function filial()
    {
        return $this->belongsTo(Empresa::class, 'id_filial', 'id_empresa');
    }

    /**
     * Scope para filtrar apenas clientes ativos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'ativo');
    }

    /**
     * Scope para filtrar clientes por empresa
     */
    public function scopeByEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    /**
     * Scope para filtrar clientes por tipo de pessoa
     */
    public function scopeByTipoPessoa($query, $tipoPessoa)
    {
        return $query->where('id_tipo_pessoa', $tipoPessoa);
    }

    /**
     * Accessor para retornar a inicial do nome do cliente
     */
    public function getInicialAttribute()
    {
        return strtoupper(substr($this->nome, 0, 1));
    }

    /**
     * Accessor para retornar o tipo de pessoa formatado
     */
    public function getTipoPessoaAttribute()
    {
        return $this->id_tipo_pessoa == 1 ? 'Física' : 'Jurídica';
    }

    /**
     * Accessor para CPF/CNPJ formatado
     */
    public function getCpfCnpjFormatadoAttribute()
    {
        if (!$this->cpf_cnpj) {
            return '-';
        }

        // Remove caracteres não numéricos
        $doc = preg_replace('/[^0-9]/', '', $this->cpf_cnpj);

        if (strlen($doc) == 11) {
            // CPF: 000.000.000-00
            return substr($doc, 0, 3) . '.' . substr($doc, 3, 3) . '.' . substr($doc, 6, 3) . '-' . substr($doc, 9, 2);
        } elseif (strlen($doc) == 14) {
            // CNPJ: 00.000.000/0000-00
            return substr($doc, 0, 2) . '.' . substr($doc, 2, 3) . '.' . substr($doc, 5, 3) . '/' . substr($doc, 8, 4) . '-' . substr($doc, 12, 2);
        }

        return $this->cpf_cnpj;
    }

    /**
     * Accessor para telefone formatado
     */
    public function getTelefoneFormatadoAttribute()
    {
        if (!$this->telefone) {
            return '-';
        }

        // Remove caracteres não numéricos
        $tel = preg_replace('/[^0-9]/', '', $this->telefone);

        if (strlen($tel) == 11) {
            // Celular: (00) 00000-0000
            return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 5) . '-' . substr($tel, 7, 4);
        } elseif (strlen($tel) == 10) {
            // Fixo: (00) 0000-0000
            return '(' . substr($tel, 0, 2) . ') ' . substr($tel, 2, 4) . '-' . substr($tel, 6, 4);
        }

        return $this->telefone;
    }

    /**
     * Accessor para CEP formatado
     */
    public function getCepFormatadoAttribute()
    {
        if (!$this->cep) {
            return '-';
        }

        // Remove caracteres não numéricos
        $cep = preg_replace('/[^0-9]/', '', $this->cep);

        if (strlen($cep) == 8) {
            // CEP: 00000-000
            return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
        }

        return $this->cep;
    }

    /**
     * Accessor para endereço completo
     */
    public function getEnderecoCompletoAttribute()
    {
        $endereco = $this->endereco;
        
        if ($this->numero) {
            $endereco .= ', ' . $this->numero;
        }
        
        if ($this->complemento) {
            $endereco .= ' - ' . $this->complemento;
        }
        
        if ($this->bairro) {
            $endereco .= ' - ' . $this->bairro;
        }
        
        if ($this->cep) {
            $endereco .= ' - CEP: ' . $this->cep_formatado;
        }

        return $endereco ?: '-';
    }

    /**
     * Accessor para URL da foto do cliente
     */
    public function getFotoUrlAttribute($value = null)
    {
        if (!empty($this->fotoUrlExterna)) {
            return $this->fotoUrlExterna;
        }

        // Se o serviço já preencheu `foto_url` (ex.: vindo da API de arquivos), respeitar.
        if (!empty($value)) {
            return $value;
        }

        $foto = trim((string) $this->foto);
        
        // Validar: se foto estiver vazia ou for um valor inválido (ex: "N", "null", etc.)
        if (empty($foto) || strlen($foto) < 5 || in_array(strtolower($foto), ['n', 'null', 'undefined', 'false', '0'])) {
            return null;
        }

        // Se já for uma URL completa, retornar como está
        if (str_starts_with($foto, 'http')) {
            return $foto;
        }

        // Construir URL da API de arquivos
        $baseUrl = (string) config('custom.api_files_url', env('API_FILES_URL', 'https://api.gestornow.com'));
        $baseUrl = rtrim(trim($baseUrl), '/');
        $baseUrl = str_replace(['api.gestornow.comn', 'api.gestornow.comN'], 'api.gestornow.com', $baseUrl);

        // Se já começa com /uploads/, usar diretamente
        if (str_starts_with($foto, '/uploads/')) {
            return $baseUrl . $foto;
        }

        // Se é um caminho relativo com uploads, normalizar
        if (str_starts_with($foto, 'uploads/')) {
            return $baseUrl . '/' . $foto;
        }

        // Se é apenas nome de arquivo, construir caminho completo para clientes
        $idEmpresa = $this->id_empresa;
        if ($idEmpresa) {
            return $baseUrl . "/uploads/clientes/imagens/{$idEmpresa}/" . ltrim($foto, '/');
        }

        // Fallback: retornar null se não conseguir construir URL válida
        return null;
    }

    public function definirFotoUrlExterna(?string $url): void
    {
        $this->fotoUrlExterna = $url;
    }
}
