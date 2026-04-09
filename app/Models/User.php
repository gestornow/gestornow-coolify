<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\ActivityLog\Traits\RegistraAtividade;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, RegistraAtividade;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id_usuario';

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
        'login',
        'nome',
        'senha',
        'id_permissoes',
        'is_suporte',
        'telefone',
        'status',
        'cpf',
        'rg',
        'comissao',
        'endereco',
        'cep',
        'bairro',
        'finalidade',
        'observacoes',
        'codigo_reset',
        'google_calendar_token',
        'tema',
        'remember_token',
        'session_token',
        'data_ultimo_acesso',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'senha',
        'remember_token',
        'session_token',
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
        'data_ultimo_acesso' => 'datetime',
        'is_suporte' => 'boolean',
        'comissao' => 'decimal:2',
    ];

    /**
     * Get the password for authentication.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->senha;
    }

    /**
     * Accessor para manter compatibilidade com o campo 'name'.
     *
     * @return string
     */
    public function getNameAttribute()
    {
        return $this->nome;
    }

    /**
     * Accessor para manter compatibilidade com o campo 'email'.
     *
     * @return string
     */
    public function getEmailAttribute()
    {
        return $this->login;
    }

    /**
     * Accessor para manter compatibilidade com o campo 'password'.
     *
     * @return string
     */
    public function getPasswordAttribute()
    {
        return $this->senha;
    }

    /**
     * Get the user's profile photo URL.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        return asset('assets/img/avatars/1.png');
    }
}
