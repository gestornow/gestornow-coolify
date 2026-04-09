<?php

namespace App\Domain\Auth\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\Auth\Services\PasswordResetService;

class AuthDomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PasswordResetService::class, function ($app) {
            return new PasswordResetService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}