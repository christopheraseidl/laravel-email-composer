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

    public static function make(): Action
    {
        return Action::make('sendTest')
            ->label(__('email-composer::drafts.actions.send_test'))
            ->icon(Heroicon::OutlinedBeaker)
            ->color('gray')
            ->authorize(fn (EmailDraft $record) => Gate::allows('sendTest', $record))
            ->schema([
                TagsInput::make('emails')
                    ->label(__('email-composer::drafts.fields.send_to'))
                    ->placeholder(__('email-composer::drafts.placeholders.send_to'))
                    ->default(fn () => array_filter([auth()->user()?->email])),
                static::localeSelect(),
            ])
            ->modalSubmitActionLabel(__('email-composer::drafts.actions.send'))
            ->action(function (EmailDraft $record, array $data) {
                $emails = $data['emails'] ?? [];

                try {
                    app(SendTestEmail::class)($record, $emails, EmailComposer::config()->localeOrDefault($data['locale'] ?? null));
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('email-composer::drafts.notifications.test_not_sent'))
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('email-composer::drafts.notifications.test_sent'))
                    ->body(__('email-composer::drafts.notifications.test_sent_body', ['emails' => implode(', ', $emails)]))
                    ->send();
            });
    }
}
