<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use App\Domain\Auth\Models\Usuario;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'financeiro/contas-a-pagar/*/pagamentos/*',
        'financeiro/contas-a-pagar/*/dar-baixa',
        'salvar-tema',
        'trocar-filial',
    ];

    /**
     * Handle an incoming request.
     * 
     * OVERRIDE: Para usuários autenticados, se o token CSRF não bater,
     * regenera automaticamente em vez de lançar erro 419.
     * Isso garante que usuários logados NUNCA vejam erro de "sessão expirada".
     */
    public function handle($request, Closure $next)
    {
        // Se está na lista de exceção, pular verificação
        if ($this->isReading($request) || $this->inExceptArray($request) || $this->runningUnitTests()) {
            return $this->addCookieToResponse($request, $next($request));
        }

        // Verificar se o token CSRF é válido
        $tokenValid = $this->tokensMatch($request);

        // Se o token é válido, prosseguir normalmente
        if ($tokenValid) {
            return $this->addCookieToResponse($request, $next($request));
        }

        // TOKEN INVÁLIDO - mas vamos verificar se o usuário está logado
        // Se tiver sessão ativa com usuário, apenas regenerar o token e prosseguir
        if ($this->userIsAuthenticated($request)) {
            // Regenerar token para próximas requisições
            $request->session()->regenerateToken();
            
            // Prosseguir com a requisição
            $response = $next($request);
            
            // Adicionar novo token no header da resposta para o JS atualizar
            $newToken = $request->session()->token();
            $response->headers->set('X-CSRF-TOKEN', $newToken);
            
            return $this->addCookieToResponse($request, $response);
        }

        // Usuário não está logado - comportamento padrão (lançar TokenMismatchException)
        return parent::handle($request, $next);
    }

    /**
     * Verifica se o usuário está autenticado na sessão.
     */
    protected function userIsAuthenticated(Request $request): bool
    {
        // Verificar múltiplas formas de autenticação
        
        // 1. Auth guard padrão
        if (auth()->check()) {
            return true;
        }
        
        // 2. Verificar se há sessão com dados de usuário
        if ($request->hasSession()) {
            $session = $request->session();
            
            // Verificar login_web (usado pelo sistema)
            if ($session->has('login_web_' . sha1(static::class))) {
                return true;
            }
            
            // Verificar session_token (autenticação customizada)
            if ($session->has('session_token')) {
                return true;
            }
            
            // Verificar user_id na sessão
            if ($session->has('user_id') || $session->has('usuario_id')) {
                return true;
            }
            
            // Verificar cliente na sessão
            if ($session->has('cliente') || $session->has('cliente_id')) {
                return true;
            }
        }
        
        // 3. Verificar cookie session_token (persistido por 30 dias)
        // Isso é importante porque a sessão PHP pode expirar, mas o cookie permanece
        $cookieToken = $request->cookie('session_token');
        if ($cookieToken) {
            // Verificar se o token é válido no banco de dados
            $usuario = Usuario::where('session_token', $cookieToken)->first();
            if ($usuario) {
                $isSuporte = (int) ($usuario->is_suporte ?? $usuario->isSuporte ?? 0) === 1;
                $idEmpresaSessao = (int) ($usuario->id_empresa ?? 0);

                if ($isSuporte) {
                    $idEmpresaCookie = (int) ($request->cookie('id_empresa_suporte') ?: 0);
                    if ($idEmpresaCookie > 0) {
                        $idEmpresaSessao = $idEmpresaCookie;
                    }
                }

                // Restaurar a sessão com os dados do usuário
                if ($request->hasSession()) {
                    $request->session()->put('session_token', $cookieToken);
                    $request->session()->put('user_id', $usuario->id_usuario);
                    $request->session()->put('id_empresa', $idEmpresaSessao);

                    if ($isSuporte && $idEmpresaSessao > 0) {
                        $request->session()->put('id_empresa_selecionada', $idEmpresaSessao);
                    }
                }
                // Fazer login no guard do Laravel
                auth()->login($usuario);
                return true;
            }
        }
        
        return false;
    }
}
