<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class SendDraftAction
{
    public static function make(): Action
    {
        return Action::make('send')
            ->label(__('email-composer::drafts.actions.send'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->requiresConfirmation()
            ->disabled(fn ($record) => static::lacksAudience($record))
            ->tooltip(fn ($record) => static::lacksAudience($record) ? __('email-composer::drafts.tooltips.lacks_audience') : null)
            ->action(function ($record) {
                try {
                    app(DraftDispatcher::class)->dispatch($record);
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('email-composer::drafts.notifications.not_sent'))
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->authorize(fn ($record) => Gate::allows('send', $record));
    }

    // Only approved drafts can be sent, so other rows skip the audience query.
    protected static function lacksAudience(EmailDraft $draft): bool
    {
        return $draft->status === EmailDraftStatus::Approved
            && ! $draft->hasAudience();
    }
}
