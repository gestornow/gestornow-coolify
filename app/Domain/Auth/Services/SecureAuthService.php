<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class SecureAuthService
{
    // Rate limiting
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 900; // 15 minutos
    
    // Session security
    const SESSION_TIMEOUT = 3600; // 1 hora
    const SESSION_REFRESH_TIME = 900; // 15 minutos
    
    // Password attempts
    const MAX_PASSWORD_ATTEMPTS = 3;
    const PASSWORD_LOCKOUT_TIME = 300; // 5 minutos

    /**
     * Tentativa de login com segurança avançada
     */
    public function tentarLogin(string $login, string $senha, bool $lembrar = false, string $userAgent = '', string $ip = ''): array
    {
        $login = $this->sanitizeInput($login);
        
        // Verificar rate limiting
        $this->verificarRateLimit($login, $ip);
        
        // Buscar usuário
        $usuario = Usuario::where('login', $login)->ativo()->first();
        
        if (!$usuario) {
            $this->registrarTentativaFalha($login, $ip, 'usuario_nao_encontrado');
            throw ValidationException::withMessages([
                'login' => ['Credenciais inválidas.']
            ]);
        }
        
        // Verificar tentativas de senha
        $this->verificarTentativasSenha($usuario->id_usuario);
        
        // Fallback: se a senha no banco não parecer um hash bcrypt válido e tiver tamanho curto, re-hashear
        if ($usuario->senha && !preg_match('/^\$2[axy]\$\d{2}\$/', $usuario->senha)) {
            // Evita re-hashear algo que já passou pela correção
            if (strlen($usuario->senha) < 60) {
                $usuario->senha = Hash::make($usuario->senha);
                $usuario->save();
            }
        }

        if (!Hash::check($senha, $usuario->senha)) {
            $this->registrarTentativaSenhaIncorreta($usuario->id_usuario);
            $this->registrarTentativaFalha($login, $ip, 'senha_incorreta');
            throw ValidationException::withMessages([
                'login' => ['Credenciais inválidas.']
            ]);
        }
        
        // Verificar status do usuário e empresa
        $this->verificarStatusUsuario($usuario);
        
        // Realizar login
        Auth::login($usuario, $lembrar);
        
        // Gerar tokens de segurança
        $sessionData = $this->criarSessaoSegura($usuario, $userAgent, $ip);
        
        // Atualizar dados do usuário (apenas campos existentes)
        $usuario->atualizarUltimoAcesso();
        
        // Limpar tentativas de falha
        $this->limparTentativasFalha($login);
        $this->limparTentativasSenha($usuario->id_usuario);
        
        // Log de sucesso
        $this->logLoginSucesso($usuario, $ip, $userAgent);
        
        return [
            'usuario' => $usuario->load('empresa'),
            'session_token' => $sessionData['session_token'],
            'csrf_token' => $sessionData['csrf_token'],
            'expires_at' => $sessionData['expires_at'],
            'success' => true,
            'message' => 'Login realizado com sucesso!'
        ];
    }
    
    /**
     * Validar sessão ativa
     */
    public function validarSessao(string $sessionToken, int $userId): bool
    {
        $usuario = Usuario::where('id_usuario', $userId)->first();
        if (!$usuario) {
            return false;
        }

        if (empty($usuario->session_token)) {
            return false;
        }

        return hash_equals((string) $usuario->session_token, (string) $sessionToken);
    }
    
    /**
     * Renovar sessão
     */
    public function renovarSessao(string $sessionToken, int $userId): array
    {
        // Regra simples: token NÃO rotaciona automaticamente.
        // Só muda em novo login; só zera em logout.
        return [
            'session_token' => $sessionToken,
            'expires_at' => null
        ];
    }
    
    /**
     * Logout seguro
     */
    public function logout(string $sessionToken = null, int $userId = null): void
    {
        // Limpar session token do usuário se estiver autenticado
        if (Auth::check()) {
            $usuario = Auth::user();
            $usuario->limparSessionToken();
        }
        
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }
    
    /**
     * Verificar rate limiting
     */
    protected function verificarRateLimit(string $login, string $ip): void
    {
        $keyLogin = "login_attempts_{$login}";
        $keyIp = "login_attempts_ip_{$ip}";
        
        if (RateLimiter::tooManyAttempts($keyLogin, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($keyLogin);
            throw ValidationException::withMessages([
                'login' => ["Muitas tentativas de login. Tente novamente em " . ceil($seconds/60) . " minutos."]
            ]);
        }
        
        if (RateLimiter::tooManyAttempts($keyIp, self::MAX_LOGIN_ATTEMPTS * 2)) {
            $seconds = RateLimiter::availableIn($keyIp);
            throw ValidationException::withMessages([
                'login' => ["IP bloqueado temporariamente. Tente novamente em " . ceil($seconds/60) . " minutos."]
            ]);
        }
    }
    
    /**
     * Verificar tentativas de senha
     */
    protected function verificarTentativasSenha(int $userId): void
    {
        $key = "password_attempts_{$userId}";
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= self::MAX_PASSWORD_ATTEMPTS) {
            throw ValidationException::withMessages([
                'login' => ['Conta temporariamente bloqueada por excesso de tentativas. Tente novamente em 5 minutos.']
            ]);
        }
    }
    
    /**
     * Registrar tentativa de senha incorreta
     */
    protected function registrarTentativaSenhaIncorreta(int $userId): void
    {
        $key = "password_attempts_{$userId}";
        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, self::PASSWORD_LOCKOUT_TIME);
    }
    
    /**
     * Criar sessão segura
     */
    protected function criarSessaoSegura(Usuario $usuario, string $userAgent, string $ip): array
    {
        $sessionToken = $usuario->gerarSessionToken(); // Usa método existente do modelo
        $csrfToken = Str::random(40);
        $expiresAt = now()->timestamp + self::SESSION_TIMEOUT;
        
        $sessionData = [
            'user_id' => $usuario->id_usuario,
            'expires_at' => $expiresAt,
            'created_at' => now()->timestamp,
            'last_activity' => now()->timestamp,
            'user_agent' => $userAgent,
            'ip_address' => $ip,
            'csrf_token' => $csrfToken
        ];
        
        return [
            'session_token' => $sessionToken,
            'csrf_token' => $csrfToken,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Verificar status do usuário
     */
    protected function verificarStatusUsuario(Usuario $usuario): void
    {
        // Verificar se o usuário está ativo
        if ($usuario->status === 'bloqueado') {
            throw ValidationException::withMessages([
                'login' => ['Usuário bloqueado. Entre em contato com o suporte.']
            ]);
        }

        if ($usuario->status === 'inativo') {
            throw ValidationException::withMessages([
                'login' => ['Usuário inativo. Entre em contato com o suporte.']
            ]);
        }

        if (!$usuario->isAtivo()) {
            throw ValidationException::withMessages([
                'login' => ['Usuário não autorizado. Entre em contato com o suporte.']
            ]);
        }
        
        if (!$usuario->empresa) {
            throw ValidationException::withMessages([
                'login' => ['Empresa não encontrada. Entre em contato com o suporte.']
            ]);
        }

        // DEBUG: Log do status da empresa
        \Log::info('DEBUG SecureAuthService - Status da empresa', [
            'empresa_id' => $usuario->empresa->id_empresa,
            'status' => $usuario->empresa->status,
            'isBloqueada' => $usuario->empresa->isBloqueada(),
            'isAtiva' => $usuario->empresa->isAtiva()
        ]);

        // Verificar status específico da empresa
        $status = $usuario->empresa->status;
        
        // Empresas bloqueadas entram no sistema somente para visualizar/contratar plano.
        if (in_array($status, ['teste bloqueado', 'bloqueado'])) {
            return;
        }

        if (!in_array($status, ['ativo', 'teste'])) {
            $mensagem = match($status) {
                'validacao' => 'Empresa em processo de validação. Aguarde aprovação.',
                'cancelado' => 'Empresa cancelada. Entre em contato com o suporte.',
                'inativo' => 'Empresa inativa. Entre em contato com o suporte.',
                default => 'Empresa indisponível. Entre em contato com o suporte.'
            };

            \Log::info('DEBUG SecureAuthService - Empresa com status não permitido', [
                'status' => $status,
                'mensagem' => $mensagem
            ]);

            throw ValidationException::withMessages([
                'login' => [$mensagem]
            ]);
        }
    }
    
    /**
     * Registrar tentativa de falha
     */
    protected function registrarTentativaFalha(string $login, string $ip, string $motivo): void
    {
        RateLimiter::hit("login_attempts_{$login}", self::LOCKOUT_TIME);
        RateLimiter::hit("login_attempts_ip_{$ip}", self::LOCKOUT_TIME);
        
        \Log::warning('Tentativa de login falhada', [
            'login' => $login,
            'ip' => $ip,
            'motivo' => $motivo,
            'timestamp' => now()
        ]);
    }
    
    /**
     * Limpar tentativas de falha
     */
    protected function limparTentativasFalha(string $login): void
    {
        RateLimiter::clear("login_attempts_{$login}");
    }
    
    /**
     * Limpar tentativas de senha
     */
    protected function limparTentativasSenha(int $userId): void
    {
        Cache::forget("password_attempts_{$userId}");
    }
    
    /**
     * Log de login com sucesso
     */
    protected function logLoginSucesso(Usuario $usuario, string $ip, string $userAgent): void
    {
        \Log::info('Login realizado com sucesso', [
            'user_id' => $usuario->id_usuario,
            'login' => $usuario->login,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'timestamp' => now()
        ]);
    }
    
    /**
     * Sanitizar input
     */
    protected function sanitizeInput(string $input): string
    {
        return trim(strtolower($input));
    }
}