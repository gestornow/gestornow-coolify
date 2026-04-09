<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Web login
        RateLimiter::for('auth-login', function (Request $request) {
            $login = Str::lower(trim((string) $request->input('login', '')));

            return [
                Limit::perMinute(10)->by('login-ip:' . $request->ip()),
                Limit::perMinute(5)->by('login-user:' . $login . '|' . $request->ip()),
            ];
        });

        // Web registro
        RateLimiter::for('auth-register', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(5)->by('register-ip:' . $request->ip()),
                Limit::perHour(10)->by('register-email:' . $email),
            ];
        });

        // Web reset de senha
        RateLimiter::for('auth-reset', function (Request $request) {
            $email = Str::lower(trim((string) ($request->input('email')
                ?: $request->input('login')
                ?: ($request->session()->get('email') ?? ''))));

            return [
                Limit::perMinute(6)->by('reset-ip:' . $request->ip()),
                Limit::perHour(12)->by('reset-account:' . $email),
            ];
        });

        // API login
        RateLimiter::for('auth-login-api', function (Request $request) {
            $login = Str::lower(trim((string) $request->input('login', '')));

            return [
                Limit::perMinute(15)->by('api-login-ip:' . $request->ip()),
                Limit::perMinute(8)->by('api-login-user:' . $login . '|' . $request->ip()),
            ];
        });

        // API registro
        RateLimiter::for('auth-register-api', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', '')));

            return [
                Limit::perMinute(8)->by('api-register-ip:' . $request->ip()),
                Limit::perHour(15)->by('api-register-email:' . $email),
            ];
        });

        // API reset de senha
        RateLimiter::for('auth-reset-api', function (Request $request) {
            $login = Str::lower(trim((string) $request->input('login', '')));

            return [
                Limit::perMinute(10)->by('api-reset-ip:' . $request->ip()),
                Limit::perHour(20)->by('api-reset-login:' . $login),
            ];
        });
    }
}
