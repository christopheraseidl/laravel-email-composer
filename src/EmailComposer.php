<?php

namespace CSeidl\EmailComposer;

use Closure;
use CSeidl\EmailComposer\Actions\Drafts\SendDraft;
use CSeidl\EmailComposer\Actions\Drafts\SummarizeSend;
use CSeidl\EmailComposer\Data\SendSummary;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\ConfigService;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @final
 */
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

    /** @internal */
    public static function flushResolvers(): void
    {
        static::$abilityResolver = null;
    }

    /** @internal */
    public static function config(): ConfigService
    {
        return app(ConfigService::class);
    }

    /** @internal */
    public static function templates(): TemplateRegistry
    {
        return app(TemplateRegistry::class);
    }

    /** @throws \DomainException see SendDraft::__invoke() */
    public static function send(EmailDraft $draft): void
    {
        app(SendDraft::class)($draft);
    }

    /** @throws \DomainException see SummarizeSend::__invoke() */
    public static function dryRun(EmailDraft $draft): SendSummary
    {
        return app(SummarizeSend::class)($draft);
    }
}
