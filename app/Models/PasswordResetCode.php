<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PasswordResetCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'code',
        'expires_at',
        'used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean'
    ];

    /**
     * Gera um código de 6 dígitos
     */
    public static function generateCode(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica se o código é válido
     */
    public function isValid(): bool
    {
        return !$this->used && $this->expires_at > Carbon::now();
    }

    /**
     * Marca o código como usado
     */
    public function markAsUsed(): void
    {
        $this->update(['used' => true]);
    }

    /**
     * Escopo para códigos válidos
     */
    public function scopeValid($query)
    {
        return $query->where('used', false)
                    ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Remove códigos antigos/expirados
     */
    public static function clearExpired(): void
    {
        static::where('expires_at', '<', Carbon::now()->subDays(1))->delete();
    }
}