<?php

namespace CSeidl\EmailComposer\Filament\Concerns;

use CSeidl\EmailComposer\EmailComposer;
use Illuminate\Support\Str;

/**
 * Filament title-cases these labels for headings, navigation and breadcrumbs.
 * Locales outside email-composer.title_case_locales get only the first
 * letter capitalized.
 */
trait HasLocaleCasedLabels
{
    public static function getTitleCaseModelLabel(): string
    {
        return static::caseForLocale(static::getModelLabel());
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return static::caseForLocale(static::getPluralModelLabel());
    }

    protected static function caseForLocale(string $label): string
    {
        return in_array(app()->getLocale(), EmailComposer::config()->titleCaseLocales(), true)
            ? Str::ucwords($label)
            : Str::ucfirst($label);
    }
}
