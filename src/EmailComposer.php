<?php

namespace CSeidl\EmailComposer;

use Closure;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\ConfigService;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class EmailComposer
{
    protected static ?Closure $abilityResolver = null;

    public static function resolveAbilityUsing(Closure $callback): void
    {
        static::$abilityResolver = $callback;
    }

    public static function userCan(?Authenticatable $user, string $ability, ?Model $model = null): bool
    {
        if ($user === null || static::$abilityResolver === null) {
            return false;
        }

        return (bool) (static::$abilityResolver)($user, $ability, $model);
    }

    /** Internal: used by tests to reset resolver state. */
    public static function flushResolvers(): void
    {
        static::$abilityResolver = null;
    }

    public static function config(): ConfigService
    {
        return app(ConfigService::class);
    }

    public static function templates(): TemplateRegistry
    {
        return app(TemplateRegistry::class);
    }

    /** @throws \DomainException see DraftDispatcher::dispatch() */
    public static function send(EmailDraft $draft): Batch
    {
        return app(DraftDispatcher::class)->dispatch($draft);
    }
}
