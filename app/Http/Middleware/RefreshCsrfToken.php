<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshCsrfToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Se for uma resposta JSON com erro 419 (CSRF), adicionar novo token
        if ($response->status() === 419 && $response->headers->get('Content-Type') === 'application/json') {
            $content = json_decode($response->content(), true);
            
            // Garantir que o novo token está na resposta
            if (!isset($content['new_token'])) {
                $content['new_token'] = csrf_token();
            }
            
            $response->setContent(json_encode($content));
        }

        return $response;
    }
}
