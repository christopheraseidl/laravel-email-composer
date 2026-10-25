<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions\Concerns;

use CSeidl\EmailComposer\EmailComposer;
use Filament\Forms\Components\Select;
use Livewire\Component;

trait HasLocaleSelect
{
    protected static function localeSelect(): Select
    {
        return Select::make('locale')
            ->options(array_combine(
                EmailComposer::config()->locales(),
                EmailComposer::config()->locales(),
            ))
            ->default(fn (Component $livewire) => static::initialLocale($livewire))
            ->selectablePlaceholder(false);
    }

    /** Pages using the Translatable concern carry the locale the user switched to. */
    protected static function initialLocale(Component $livewire): string
    {
        $locale = property_exists($livewire, 'activeLocale') ? $livewire->activeLocale : null;

        return EmailComposer::config()->localeOrDefault($locale);
    }
}
