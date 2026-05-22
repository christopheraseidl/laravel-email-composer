<?php

namespace CSeidl\EmailComposer;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

class EmailComposer
{
    protected static ?Closure $abilityResolver = null;

    public static function resolveAbilityUsing(Closure $callback): void
    {
        static::$abilityResolver = $callback;
    }

    public static function userCan(?Authenticatable $user, string $ability): bool
    {
        if ($user === null || static::$abilityResolver === null) {
            return false;
        }

        return (bool) (static::$abilityResolver)($user, $ability);
    }

    /** Internal: used by tests to reset resolver state. */
    public static function flushResolvers(): void
    {
        static::$abilityResolver = null;
    }
}
