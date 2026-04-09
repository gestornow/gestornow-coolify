<?php

namespace App\Domain\Auth\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Empresa extends Model
{
    use HasFactory, SoftDeletes, RegistraAtividade;

    protected static function booted(): void
    {
        static::updating(function (self $empresa): void {
            $camposEndereco = [
                'endereco',
                'numero',
                'bairro',
                'cidade',
                'uf',
                'cep',
                'complemento',
            ];

            $alterouEndereco = false;
            foreach ($camposEndereco as $campo) {
                if ($empresa->isDirty($campo)) {
                    $alterouEndereco = true;
                    break;
                }
            }

            if (!$alterouEndereco) {
                return;
            }

            if (!$empresa->isDirty('codigo')) {
                $empresa->codigo = $empresa->getOriginal('codigo');
            }

            if (!$empresa->isDirty('filial')) {
                $empresa->filial = $empresa->getOriginal('filial');
            }

            if (!$empresa->isDirty('id_empresa_matriz')) {
                $empresa->id_empresa_matriz = $empresa->getOriginal('id_empresa_matriz');
            }
        });
    }

    protected $table = 'empresa';
    protected $primaryKey = 'id_empresa';

    protected $fillable = [
        'razao_social',
        'cnpj',
        'cpf',
        'id_tipo_pessoa',
        'nome_empresa',
        'endereco',
        'numero',
        'bairro',
        'cidade',
        'complemento',
        'uf',
        'cep',
        'ie',
        'im',
        'email',
        'id_plano',
        'status',
        'data_bloqueio',
        'data_cancelamento',
        'data_fim_teste',
        'id_plano_teste',
        'telefone',
        'cnae',
        'id_regime_tributario',
        'configuracoes',
        'dados_cadastrais',
        'codigo',
        'filial',
        'c_produtos',
        'c_clientes',
        'c_fornecedores',
        'id_empresa_matriz',
        'orcamentos_contratos',
        'locacao_numero_manual',
    ];

    protected $casts = [
        'configuracoes' => 'array',
        'data_bloqueio' => 'datetime',
        'data_cancelamento' => 'datetime',
        'data_fim_teste' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'orcamentos_contratos' => 'integer',
        'locacao_numero_manual' => 'integer',
    ];

    protected $hidden = [
        'configuracoes',
    ];

    // Relacionamentos
    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_empresa', 'id_empresa');
    }

    public function usuarioAtivo(): HasMany
    {
        return $this->hasMany(Usuario::class, 'id_empresa', 'id_empresa')
                    ->where('status', 'ativo');
    }

    public function empresaMatriz()
    {
        return $this->belongsTo(self::class, 'id_empresa_matriz', 'id_empresa');
    }

    public function filiais(): HasMany
    {
        return $this->hasMany(self::class, 'id_empresa_matriz', 'id_empresa');
    }

    public function planosContratados(): HasMany
    {
        return $this->hasMany(\App\Models\PlanoContratado::class, 'id_empresa', 'id_empresa');
    }

    // Scopes
    public function scopeAtiva($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopeValidacao($query)
    {
        return $query->where('status', 'validacao');
    }

    public function scopeTeste($query)
    {
        return $query->where('status', 'teste');
    }

    // Accessors
    public function getCnpjFormatadoAttribute()
    {
        $cnpj = preg_replace('/\D/', '', $this->cnpj);
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }

    public function getCpfFormatadoAttribute()
    {
        if (!$this->cpf) return null;
        $cpf = preg_replace('/\D/', '', $this->cpf);
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
    }

    public function getTelefoneFormatadoAttribute()
    {
        if (!$this->telefone) return null;
        $telefone = preg_replace('/\D/', '', $this->telefone);
        
        if (strlen($telefone) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        } elseif (strlen($telefone) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        }
        
        return $this->telefone;
    }

    // Mutators
    public function setCnpjAttribute($value)
    {
        $this->attributes['cnpj'] = preg_replace('/\D/', '', $value);
    }

    public function setCpfAttribute($value)
    {
        $this->attributes['cpf'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    public function setTelefoneAttribute($value)
    {
        $this->attributes['telefone'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    public function setCepAttribute($value)
    {
        $this->attributes['cep'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    // Métodos auxiliares
    public function isAtiva(): bool
    {
        return in_array($this->status, ['ativo', 'teste']);
    }

    public function isBloqueada(): bool
    {
        return in_array($this->status, ['bloqueado', 'teste bloqueado']);
    }

    public function isValidacao(): bool
    {
        return $this->status === 'validacao';
    }

    public function isTeste(): bool
    {
        return in_array($this->status, ['teste', 'teste bloqueado']);
    }

    public function testeAtivo(): bool
    {
        return $this->status === 'teste' 
            && $this->data_fim_teste 
            && $this->data_fim_teste->isFuture();
    }

    public function testeExpirado(): bool
    {
        return $this->status === 'teste' 
            && $this->data_fim_teste 
            && $this->data_fim_teste->isPast();
    }

    public function diasRestantesTeste(): int
    {
        if (!$this->data_fim_teste) {
            return 0;
        }

        $segundos = now()->diffInSeconds($this->data_fim_teste, false);
        return max(0, (int) ceil($segundos / 86400));
    }

    public function semPlanoAtivo(): bool
    {
        return !$this->planosContratados()->where('status', 'ativo')->exists();
    }

    public function dadosCadastraisCompletos(): bool
    {
        $camposObrigatorios = [
            'razao_social',
            'nome_empresa',
            'email',
            'telefone',
            'endereco',
            'numero',
            'bairro',
            'cidade',
            'uf',
            'cep',
        ];

        foreach ($camposObrigatorios as $campo) {
            $valor = trim((string) ($this->{$campo} ?? ''));
            if ($valor === '') {
                return false;
            }
        }

        $cnpj = preg_replace('/\D/', '', (string) ($this->cnpj ?? ''));
        $cpf = preg_replace('/\D/', '', (string) ($this->cpf ?? ''));

        return $cnpj !== '' || $cpf !== '';
    }

    public function isMatriz(): bool
    {
        return $this->filial === 'Matriz';
    }

    public function isFilial(): bool
    {
        return $this->filial === 'Filial';
    }
}