<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Usuario;
use App\Mail\CodigoRedefinicaoSenha as CodigoRedefinicaoSenhaMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Exception;

class PasswordResetService
{
    private const MAX_CODIGO_TENTATIVAS = 5;
    private const BLOQUEIO_CODIGO_SEGUNDOS = 900;

    private function chaveTentativasCodigo(string $login): string
    {
        return 'password_reset_codigo_tentativas:' . sha1(strtolower(trim($login)));
    }

    /**
     * Envia código de redefinição de senha
     */
    public function enviarCodigoRedefinicao(string $login): array
    {
        try {
            // Busca o usuário
            $usuario = Usuario::where('login', $login)->ativo()->first();
            
            if (!$usuario) {
                return [
                    'success' => true,
                    'message' => 'Se o email estiver cadastrado, enviaremos um código de redefinição.',
                    'data' => [
                        'login' => $login,
                        'nome' => null,
                        'reset_link' => null,
                    ]
                ];
            }


            // Gera código de 6 dígitos
            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            // Salva código no usuário (expira em 15 minutos)
            $expiraEm = now()->addMinutes(15)->timestamp;
            $usuario->update([
                'codigo_reset' => 'v2|' . Hash::make($codigo) . '|' . $expiraEm,
            ]);

            // Gerar link de reset para deep link do app
            $resetLink = 'https://gestornow.com/verify?token=' . urlencode($codigo);

            // Envia email (passando também o link)
            Mail::to($login)->send(new CodigoRedefinicaoSenhaMail($usuario->nome, $codigo, $resetLink));

            \Log::info('Email de redefinicao enviado com sucesso.', [
                'login' => $login,
                'usuario_id' => $usuario->id_usuario ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'Se o email estiver cadastrado, enviaremos um código de redefinição.',
                'data' => [
                    'login' => $login,
                    'nome' => $usuario->nome,
                ]
            ];

        } catch (Exception $e) {
            \Log::error('Falha ao enviar email de redefinicao.', [
                'login' => $login,
                'erro' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return [
                'success' => true,
                'message' => 'Se o email estiver cadastrado, enviaremos um código de redefinição.',
                'data' => [
                    'login' => $login,
                    'nome' => null,
                ]
            ];
        }
    }

    /**
     * Valida código de redefinição
     */
    public function validarCodigo(string $login, string $codigo): array
    {
        \Log::info('=== INICIANDO VALIDAÇÃO DE CÓDIGO ===', [
            'login' => $login,
            'codigo_informado' => $codigo !== ''
        ]);
        
        try {
            $chaveTentativas = $this->chaveTentativasCodigo($login);
            $tentativas = (int) Cache::get($chaveTentativas, 0);

            if ($tentativas >= self::MAX_CODIGO_TENTATIVAS) {
                return [
                    'success' => false,
                    'message' => 'Muitas tentativas inválidas. Aguarde alguns minutos e tente novamente.'
                ];
            }

            $usuario = Usuario::where('login', $login)->first();
            
            \Log::info('Usuario encontrado:', [
                'usuario_id' => $usuario ? $usuario->id_usuario : null,
                'tem_codigo_reset' => !empty($usuario?->codigo_reset)
            ]);
            
            if (!$usuario || !$usuario->codigo_reset) {
                Cache::put($chaveTentativas, $tentativas + 1, self::BLOQUEIO_CODIGO_SEGUNDOS);
                return [
                    'success' => false,
                    'message' => 'Código inválido ou expirado.'
                ];
            }

            // Decodifica código e timestamp
            $parts = explode('|', $usuario->codigo_reset);
            $codigoConfere = false;
            $timestamp = 0;

            // Formato novo: v2|hash|timestamp
            if (count($parts) === 3 && ($parts[0] ?? '') === 'v2') {
                $codigoHash = (string) ($parts[1] ?? '');
                $timestamp = (int) ($parts[2] ?? 0);
                $codigoConfere = $codigoHash !== '' && Hash::check($codigo, $codigoHash);
            }

            // Formato legado: codigo|timestamp (compatibilidade)
            if (!$codigoConfere && count($parts) === 2) {
                $codigoSalvo = (string) ($parts[0] ?? '');
                $timestamp = (int) ($parts[1] ?? 0);
                $codigoConfere = hash_equals($codigoSalvo, $codigo);
            }

            if ($timestamp <= 0) {
                Cache::put($chaveTentativas, $tentativas + 1, self::BLOQUEIO_CODIGO_SEGUNDOS);
                return [
                    'success' => false,
                    'message' => 'Código inválido.'
                ];
            }
            
            // Converte para inteiro para comparação correta
            $timestamp = (int) $timestamp;

            // Debug detalhado
            $agora = now()->timestamp;
            $dataExpiracao = date('Y-m-d H:i:s', $timestamp);
            $dataAgora = date('Y-m-d H:i:s', $agora);
            
            \Log::info('=== VALIDAÇÃO DETALHADA ===', [
                'codigos_iguais' => $codigoConfere,
                'timestamp_expiracao' => $timestamp,
                'timestamp_agora' => $agora,
                'data_expiracao_legivel' => $dataExpiracao,
                'data_agora_legivel' => $dataAgora,
                'diferenca_segundos' => $timestamp - $agora,
                'esta_expirado' => $timestamp < $agora,
                'formato_codigo_reset' => count($parts) === 3 ? 'v2' : 'legacy',
            ]);

            // Verifica se código confere
            if (!$codigoConfere) {
                Cache::put($chaveTentativas, $tentativas + 1, self::BLOQUEIO_CODIGO_SEGUNDOS);
                return [
                    'success' => false,
                    'message' => 'Código inválido.'
                ];
            }
            
            // Verifica se não expirou
            if ($timestamp < $agora) {
                Cache::put($chaveTentativas, $tentativas + 1, self::BLOQUEIO_CODIGO_SEGUNDOS);
                return [
                    'success' => false,
                    'message' => 'Código expirado. Solicite um novo código.'
                ];
            }

            // Gera token temporário para reset
            $tokenReset = bin2hex(random_bytes(32));
            $usuario->update([
                'codigo_reset' => $tokenReset . '|reset|' . now()->addMinutes(10)->timestamp
            ]);

            Cache::forget($chaveTentativas);
            
            \Log::info('=== CÓDIGO VALIDADO COM SUCESSO ===', [
                'login' => $login,
                'token_gerado' => true
            ]);

            return [
                'success' => true,
                'message' => 'Código validado com sucesso!',
                'data' => [
                    'login' => $login,
                    'token_reset' => $tokenReset
                ]
            ];

        } catch (Exception $e) {
            \Log::error('=== ERRO NA VALIDAÇÃO ===', [
                'login' => $login,
                'codigo' => $codigo,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Erro ao validar código.'
            ];
        }
    }

    /**
     * Atualiza senha do usuário
     */
    public function atualizarSenha(string $login, string $novaSenha, string $tokenReset): array
    {
        try {
            $usuario = Usuario::where('login', $login)->first();
            
            if (!$usuario || !$usuario->codigo_reset) {
                return [
                    'success' => false,
                    'message' => 'Token de reset inválido.'
                ];
            }

            // Verifica token de reset
            $parts = explode('|', $usuario->codigo_reset);
            if (count($parts) !== 3 || $parts[0] !== $tokenReset || $parts[1] !== 'reset') {
                return [
                    'success' => false,
                    'message' => 'Token de reset inválido.'
                ];
            }

            // Verifica se não expirou
            if ($parts[2] < now()->timestamp) {
                return [
                    'success' => false,
                    'message' => 'Token de reset expirado.'
                ];
            }

            // Atualiza senha e limpa código reset
            $usuario->update([
                'senha' => $novaSenha, // O mutator já faz o hash
                'codigo_reset' => null,
                'session_token' => null,
                'remember_token' => Str::random(60),
            ]);

            // Revogar tokens da API para evitar sessão comprometida ativa após troca de senha
            if (method_exists($usuario, 'tokens')) {
                $usuario->tokens()->delete();
            }

            return [
                'success' => true,
                'message' => 'Senha atualizada com sucesso!'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro ao atualizar senha.'
            ];
        }
    }

    /**
     * Reenvia código de redefinição
     */
    public function reenviarCodigo(string $login): array
    {
        return $this->enviarCodigoRedefinicao($login);
    }

    /**
     * Limpa códigos expirados (método estático para comando)
     */
    public static function limparCodigosExpirados(): void
    {
        // Limpa códigos reset expirados dos usuários
        Usuario::whereNotNull('codigo_reset')
            ->get()
            ->each(function ($usuario) {
                $parts = explode('|', $usuario->codigo_reset);
                if (count($parts) >= 2) {
                    $timestamp = end($parts);
                    if ($timestamp < now()->subDay()->timestamp) {
                        $usuario->update(['codigo_reset' => null]);
                    }
                }
            });
    }
}