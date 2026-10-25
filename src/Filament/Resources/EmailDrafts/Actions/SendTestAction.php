<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\SendTestEmail;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions\Concerns\HasLocaleSelect;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class SendTestAction
{
    use HasLocaleSelect;

    /** Shared by the drafts table and the edit page header. */
    public static function make(): Action
    {
        return Action::make('sendTest')
            ->label('Send test')
            ->icon(Heroicon::OutlinedBeaker)
            ->color('gray')
            ->authorize(fn (EmailDraft $record) => Gate::allows('sendTest', $record))
            ->schema([
                TagsInput::make('emails')
                    ->label('Send to')
                    ->placeholder('Add an email address')
                    ->default(fn () => array_filter([auth()->user()?->email])),
                static::localeSelect(),
            ])
            ->modalSubmitActionLabel('Send')
            ->action(function (EmailDraft $record, array $data) {
                $emails = $data['emails'] ?? [];

                try {
                    app(SendTestEmail::class)($record, $emails, EmailComposer::config()->localeOrDefault($data['locale'] ?? null));
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title('Test email not sent')
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Test email sent')
                    ->body('Sent to '.implode(', ', $emails).'.')
                    ->send();
            });
    }
}
