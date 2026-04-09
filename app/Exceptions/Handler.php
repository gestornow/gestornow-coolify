<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        TokenMismatchException::class,
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     * 
     * NOTA: Com o novo VerifyCsrfToken, erros CSRF só ocorrem para usuários
     * não autenticados. Usuários logados têm o token regenerado automaticamente.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // Tratar TokenMismatchException (CSRF) - só acontece para não logados
        $this->renderable(function (TokenMismatchException $e, $request) {
            return $this->handleCsrfError($request);
        });

        // Tratar erro 419 direto - só acontece para não logados
        $this->renderable(function (HttpExceptionInterface $e, $request) {
            if ((int) $e->getStatusCode() === 419) {
                return $this->handleCsrfError($request);
            }
            return null;
        });
    }

    /**
     * Tratar erro de CSRF de forma padronizada.
     * Como só usuários não logados chegam aqui, a melhor ação é fazer login.
     */
    protected function handleCsrfError($request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'csrf_error' => true,
                'message' => 'Faça login para continuar.',
                'redirect' => route('login.form'),
            ], 419);
        }

        return redirect()
            ->route('login.form')
            ->with('warning', 'Sua sessão não foi encontrada. Faça login para continuar.');
    }
}
