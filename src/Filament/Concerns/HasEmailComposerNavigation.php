<?php

namespace CSeidl\EmailComposer\Filament\Concerns;

use BackedEnum;
use CSeidl\EmailComposer\EmailComposer;
use Illuminate\Contracts\Support\Htmlable;

trait HasEmailComposerNavigation
{
    public static function getNavigationGroup(): ?string
    {
        return EmailComposer::config()->navigationGroup();
    }

    // Items inside the group go without icons; ungrouped, each one needs its own.
    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return EmailComposer::config()->navigationGroup() === null
            ? static::$navigationIcon
            : null;
    }
}
