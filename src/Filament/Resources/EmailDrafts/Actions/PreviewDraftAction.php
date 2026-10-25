<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions\Concerns\HasLocaleSelect;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class PreviewDraftAction
{
    use HasLocaleSelect;

    public static function make(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->authorize(fn (EmailDraft $record) => Gate::allows('view', $record))
            ->schema([
                static::localeSelect()->live(),
                View::make('email-composer::filament.preview')
                    ->viewData(fn (EmailDraft $record, Get $get) => static::preview($record, $get('locale'))),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
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
