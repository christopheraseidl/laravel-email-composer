<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\CancelSend;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * @internal
 */
class CancelSendAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label(__('email-composer::drafts.actions.cancel_send'))
            ->icon(Heroicon::OutlinedStopCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (EmailDraft $record) => $record->isRetryingSent()
                ? __('email-composer::drafts.confirmations.cancel_retry')
                : __('email-composer::drafts.confirmations.cancel_send'))
            ->action(function (EmailDraft $record) {
                $retrying = $record->isRetryingSent();

                NotifiesDomainErrors::run(
                    fn () => app(CancelSend::class)($record),
                    __('email-composer::drafts.notifications.not_cancelled'),
                    fn () => Notification::make()
                        ->success()
                        ->title($retrying
                            ? __('email-composer::drafts.notifications.retry_cancelled')
                            : __('email-composer::drafts.notifications.send_cancelled'))
                        ->send(),
                );
            })
            ->authorize(fn ($record) => Gate::allows('cancel', $record));
    }
}
