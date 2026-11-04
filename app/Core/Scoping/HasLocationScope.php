<?php

namespace App\Core\Scoping;

trait HasLocationScope
{
    public static function bootHasLocationScope(): void
    {
        static::addGlobalScope(new LocationScope);
    }
}
