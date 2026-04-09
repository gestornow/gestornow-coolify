<?php

namespace App\ActivityLog\Traits;

use App\ActivityLog\Observers\UniversalObserver;

trait RegistraAtividade
{
    public static function bootRegistraAtividade(): void
    {
        static::observe(UniversalObserver::class);
    }
}
