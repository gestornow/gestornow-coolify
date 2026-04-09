<?php

namespace App\ActivityLog\Contracts;

use Closure;

interface ActivityMap
{
    public static function entidadeTipo(): string;

    public static function tags(): array;

    public static function label(): Closure;

    public static function valor(): ?Closure;

    public static function camposSensiveis(): array;

    public static function eventos(): array;
}
