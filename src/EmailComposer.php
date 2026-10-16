<?php

namespace CSeidl\EmailComposer;

use Closure;
use CSeidl\EmailComposer\Models\EmailDraft;
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

    /** @return class-string<Model> */
    public static function userModel(): string
    {
        $model = config('auth.providers.users.model');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new \UnexpectedValueException('auth.providers.users.model must name an Eloquent model class.');
        }

        return $model;
    }

    public static function templates(): TemplateRegistry
    {
        return app(TemplateRegistry::class);
    }

    public static function send(EmailDraft $draft): Batch
    {
        return app(DraftDispatcher::class)->dispatch($draft);
    }
}
