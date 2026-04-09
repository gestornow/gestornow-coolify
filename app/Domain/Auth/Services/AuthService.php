<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Usuario;
use App\Domain\Auth\Repositories\UsuarioRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected $usuarioRepository;

    public function __construct(UsuarioRepository $usuarioRepository)
    {
        $this->usuarioRepository = $usuarioRepository;
    }

    public function tentarLogin(string $login, string $senha, bool $lembrar = false): array
    {
        $usuario = $this->usuarioRepository->findByLogin($login);

        if (!$usuario) {
            throw ValidationException::withMessages([
                'login' => ['Credenciais inválidas.']
            ]);
        }

        if (!$this->verificarSenha($senha, $usuario->senha)) {
            throw ValidationException::withMessages([
                'login' => ['Credenciais inválidas.']
            ]);
        }

        if (!$usuario->podeAcessarSistema()) {
            $this->verificarStatusUsuarioEmpresa($usuario);
        }

        // Realizar login
        Auth::login($usuario, $lembrar);

        // Atualizar último acesso
        $usuario->atualizarUltimoAcesso();

        // Gerar session token para segurança adicional
        $sessionToken = $usuario->gerarSessionToken();

        return [
            'usuario' => $usuario->load('empresa'),
            'session_token' => $sessionToken,
            'success' => true,
            'message' => 'Login realizado com sucesso!'
        ];
    }

    public function verificarSenha(string $senhaInformada, string $senhaHash): bool
    {
        return Hash::check($senhaInformada, $senhaHash);
    }

    public function verificarStatusUsuarioEmpresa(Usuario $usuario): void
    {
        if (!$usuario->isAtivo()) {
            throw ValidationException::withMessages([
                'login' => ['Usuário inativo. Entre em contato com o suporte.']
            ]);
        }

        if ($usuario->isBloqueado()) {
            throw ValidationException::withMessages([
                'login' => ['Usuário bloqueado. Entre em contato com o suporte.']
            ]);
        }

        if (!$usuario->empresa) {
            throw ValidationException::withMessages([
                'login' => ['Empresa não encontrada. Entre em contato com o suporte.']
            ]);
        }

        // DEBUG: Log do status da empresa
        Log::info('DEBUG AuthService - Status da empresa', [
            'empresa_id' => $usuario->empresa->id_empresa,
            'status' => $usuario->empresa->status,
            'isBloqueada' => $usuario->empresa->isBloqueada(),
            'isAtiva' => $usuario->empresa->isAtiva()
        ]);

        if ($usuario->empresa->isBloqueada()) {
            Log::info('DEBUG AuthService - Empresa bloqueada, impedindo login');
            throw ValidationException::withMessages([
                'login' => ['Empresa bloqueada. Entre em contato com o suporte.']
            ]);
        }

        if (!$usuario->empresa->isAtiva()) {
            $status = $usuario->empresa->status;
            $mensagem = match($status) {
                'validacao' => 'Empresa em processo de validação. Aguarde aprovação.',
                'teste bloqueado' => 'Período de teste expirado. Entre em contato para ativação.',
                'bloqueado' => 'Empresa bloqueada. Entre em contato com o suporte.',
                'cancelado' => 'Empresa cancelada. Entre em contato com o suporte.',
                default => 'Empresa indisponível. Entre em contato com o suporte.'
            };

            throw ValidationException::withMessages([
                'login' => [$mensagem]
            ]);
        }
    }

    public function logout(): void
    {
        $usuario = Auth::user();
        
        if ($usuario instanceof Usuario) {
            $usuario->limparSessionToken();
        }

        Auth::logout();
    }

    public function alterarSenha(Usuario $usuario, string $senhaAtual, string $novaSenha): bool
    {
        if (!$this->verificarSenha($senhaAtual, $usuario->senha)) {
            throw ValidationException::withMessages([
                'senha_atual' => ['Senha atual incorreta.']
            ]);
        }

        return $this->usuarioRepository->update($usuario, [
            'senha' => $novaSenha // O mutator irá criptografar automaticamente
        ]);
    }

    public function iniciarRecuperacaoSenha(string $login): string
    {
        $usuario = $this->usuarioRepository->findByLogin($login);

        if (!$usuario) {
            throw ValidationException::withMessages([
                'login' => ['Usuário não encontrado.']
            ]);
        }

        if (!$usuario->podeAcessarSistema()) {
            throw ValidationException::withMessages([
                'login' => ['Usuário ou empresa inativo/bloqueado.']
            ]);
        }

        return $usuario->gerarCodigoReset();
    }

    public function finalizarRecuperacaoSenha(string $codigo, string $novaSenha): bool
    {
        $usuario = $this->usuarioRepository->findByCodigoReset($codigo);

        if (!$usuario) {
            throw ValidationException::withMessages([
                'codigo' => ['Código de recuperação inválido ou expirado.']
            ]);
        }

        $resultado = $this->usuarioRepository->update($usuario, [
            'senha' => $novaSenha // O mutator irá criptografar automaticamente
        ]);

        if ($resultado) {
            $usuario->limparCodigoReset();
        }

        return $resultado;
    }

    public function validarSessionToken(Usuario $usuario, string $token): bool
    {
        return $usuario->validarSessionToken($token);
    }

    public function renovarSessionToken(Usuario $usuario): string
    {
        return $usuario->gerarSessionToken();
    }
}