<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AddFreshCsrfTokenToResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Adicionar token CSRF fresco em todas as respostas JSON
        if ($response->headers->get('content-type') === 'application/json' || 
            $request->expectsJson() || 
            $request->wantsJson()) {
            
            // Se a resposta é um array/collection, transformar em JSON e adicionar token
            if (is_array($response->getOriginalContent()) || 
                $response->getOriginalContent() instanceof \Illuminate\Support\Collection) {
                
                $content = $response->getOriginalContent();
                
                // Se já for um array, adicionar token
                if (is_array($content)) {
                    $content['_csrf_token'] = csrf_token();
                    return response()->json($content);
                }
            }
        }

        return $response;
    }
}
