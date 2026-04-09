<?php

namespace App\Domain\Auth\Models;

use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, RegistraAtividade;

    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';

    protected $fillable = [
        'id_empresa',
        'login',
        'nome',
        'senha',
        'id_permissoes',
        'is_suporte',
        'telefone',
        'status',
        'data_ultimo_acesso',
        'cpf',
        'rg',
        'comissao',
        'endereco',
        'cep',
        'bairro',
        'observacoes',
        'codigo_reset',
        'google_calendar_token',
        'tema',
        'remember_token',
        'session_token',
        'finalidade'
    ];

    protected $hidden = [
        'senha',
        'remember_token',
        'session_token',
        'codigo_reset',
        'google_calendar_token',
    ];

    protected $casts = [
        'data_ultimo_acesso' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_suporte' => 'boolean',
        'comissao' => 'decimal:2',
    ];

    // Override para usar o campo personalizado de senha
    public function getAuthPassword()
    {
        return $this->senha;
    }

    // Override para usar login ao invés de email
    public function getAuthIdentifierName()
    {
        return 'login';
    }

    public function getAuthIdentifier()
    {
        return $this->getKey();
    }

    // Relacionamentos
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'id_empresa', 'id_empresa');
    }

    public function permissoes(): HasMany
    {
        return $this->hasMany(UsuarioPermissao::class, 'id_usuario', 'id_usuario');
    }

    // Scopes
    public function scopeAtivo($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopeBloqueado($query)
    {
        return $query->where('status', 'bloqueado');
    }

    public function scopeSuporte($query)
    {
        return $query->where('is_suporte', true);
    }

    public function scopeEmpresa($query, $idEmpresa)
    {
        return $query->where('id_empresa', $idEmpresa);
    }

    // Accessors
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

    public function getNomeCompletoAttribute()
    {
        return $this->nome ?: $this->login;
    }

    public function getInicialAttribute()
    {
        $nome = $this->nome ?: $this->login;
        $palavras = explode(' ', $nome);
        
        if (count($palavras) >= 2) {
            return strtoupper(substr($palavras[0], 0, 1) . substr($palavras[1], 0, 1));
        }
        
        return strtoupper(substr($nome, 0, 2));
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $apiBaseUrl = rtrim((string) config('custom.api_files_url', 'https://api.gestornow.com'), '/');

        $fotoUrl = trim((string) ($this->attributes['foto_url'] ?? ''));
        if ($fotoUrl !== '') {
            if (str_starts_with($fotoUrl, 'http://') || str_starts_with($fotoUrl, 'https://')) {
                return $fotoUrl;
            }

            return $apiBaseUrl . '/' . ltrim($fotoUrl, '/');
        }

        $fotoFilename = trim((string) ($this->attributes['foto_filename'] ?? ''));
        $idEmpresa = (int) ($this->attributes['id_empresa'] ?? 0);

        if ($fotoFilename === '' || $idEmpresa <= 0) {
            return null;
        }

        return $apiBaseUrl . '/uploads/usuarios/imagens/' . $idEmpresa . '/' . ltrim($fotoFilename, '/');
    }

    // Mutators
    public function setSenhaAttribute($value)
    {
        if ($value && is_string($value) && trim($value) !== '') {
            // Se já é um hash bcrypt, não criptografar novamente
            if (preg_match('/^\$2[axy]\$\d{2}\$/', $value)) {
                $this->attributes['senha'] = $value;
            } else {
                $this->attributes['senha'] = bcrypt($value);
            }
        }
    }

    public function setCpfAttribute($value)
    {
        $this->attributes['cpf'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    public function setTelefoneAttribute($value)
    {
        $this->attributes['telefone'] = $value ? preg_replace('/\D/', '', $value) : null;
    }

    public function setLoginAttribute($value)
    {
        $this->attributes['login'] = strtolower(trim($value));
    }

    // Métodos auxiliares
    public function isAtivo(): bool
    {
        return $this->status === 'ativo';
    }

    public function isBloqueado(): bool
    {
        return $this->status === 'bloqueado';
    }

    public function isSuporte(): bool
    {
        return $this->is_suporte;
    }

    public function empresaAtiva(): bool
    {
        return $this->empresa && $this->empresa->isAtiva();
    }

    public function podeAcessarSistema(): bool
    {
        return $this->isAtivo() && $this->empresaAtiva();
    }

    public function atualizarUltimoAcesso(): void
    {
        $this->update(['data_ultimo_acesso' => now()]);
    }

    public function gerarCodigoReset(): string
    {
        $codigo = bin2hex(random_bytes(32));
        $this->update(['codigo_reset' => $codigo]);
        return $codigo;
    }

    public function validarCodigoReset(string $codigo): bool
    {
        return $this->codigo_reset === $codigo;
    }

    public function limparCodigoReset(): void
    {
        $this->update(['codigo_reset' => null]);
    }

    public function gerarSessionToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->update(['session_token' => $token]);
        return $token;
    }

    public function validarSessionToken(string $token): bool
    {
        return $this->session_token === $token;
    }

    public function limparSessionToken(): void
    {
        \Log::info('Usuario::limparSessionToken - Limpando token', [
            'user_id' => $this->id_usuario,
            'tem_token_antes' => !empty($this->session_token),
        ]);
        
        $result = $this->update(['session_token' => null]);
        
        \Log::info('Usuario::limparSessionToken - Token limpo', [
            'user_id' => $this->id_usuario,
            'update_result' => $result,
            'tem_token_depois' => !empty($this->session_token),
        ]);
    }

    /**
     * Verifica se o usuário tem permissão específica para um módulo
     */
    public function podeAcessar(int $idModulo, string $acao = 'ler'): bool
    {
        $permissao = $this->permissoes()
            ->where('id_modulo', $idModulo)
            ->first();
        
        if (!$permissao) {
            return false;
        }
        
        $campoPermissao = "pode_{$acao}";
        return $permissao->$campoPermissao ?? false;
    }

    /**
     * Verifica se o usuário pode ler um módulo
     */
    public function podeVerLeitura(int $idModulo): bool
    {
        return $this->podeAcessar($idModulo, 'ler');
    }

    /**
     * Verifica se o usuário pode criar em um módulo
     */
    public function podeCriar(int $idModulo): bool
    {
        return $this->podeAcessar($idModulo, 'criar') && $this->podeAcessar($idModulo, 'ler');
    }

    /**
     * Verifica se o usuário pode editar em um módulo
     */
    public function podeEditar(int $idModulo): bool
    {
        return $this->podeAcessar($idModulo, 'editar') && $this->podeAcessar($idModulo, 'ler');
    }

    /**
     * Verifica se o usuário pode deletar em um módulo
     */
    public function podeDeletar(int $idModulo): bool
    {
        return $this->podeAcessar($idModulo, 'deletar') && $this->podeAcessar($idModulo, 'ler');
    }

    /**
     * Pega todas as permissões do usuário
     */
    public function getPermissoes(): array
    {
        return $this->permissoes()
            ->with('modulo')
            ->get()
            ->keyBy('id_modulo')
            ->toArray();
    }
}