<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class PreviewDraftAction
{
    public static function make(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize(fn (EmailDraft $record) => Gate::allows('view', $record))
            ->schema([
                Select::make('locale')
                    ->options(array_combine(
                        EmailComposer::config()->locales(),
                        EmailComposer::config()->locales(),
                    ))
                    ->default(fn (Component $livewire) => static::initialLocale($livewire))
                    ->selectablePlaceholder(false)
                    ->live(),
                View::make('email-composer::filament.preview')
                    ->viewData(fn (EmailDraft $record, Get $get) => static::preview($record, $get('locale'))),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    /** Pages using the Translatable concern carry the locale the user switched to. */
    protected static function initialLocale(Component $livewire): string
    {
        $locale = property_exists($livewire, 'activeLocale') ? $livewire->activeLocale : null;

        return EmailComposer::config()->localeOrDefault($locale);
    }

    /** @return array{subject: string, html: ?string, error: ?string} */
    protected static function preview(EmailDraft $record, mixed $locale): array
    {
        $locale = EmailComposer::config()->localeOrDefault($locale);

        try {
            $html = $record->renderFor($locale);
            $error = null;
        } catch (\DomainException $e) {
            $html = null;
            $error = $e->getMessage();
        }

        return ['subject' => $record->subjectFor($locale), 'html' => $html, 'error' => $error];
    }
}
