<?php

namespace App\Http\Middleware;

use App\Facades\Perm;
use Closure;
use Illuminate\Http\Request;

class CheckPermissao
{
    public function handle(Request $request, Closure $next, ...$chaves)
    {
        $usuario = auth()->user();

        if (!$usuario) {
            abort(403);
        }

        foreach ($chaves as $chave) {
            $chave = trim((string) $chave);

            if ($chave !== '' && Perm::pode($usuario, $chave)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
