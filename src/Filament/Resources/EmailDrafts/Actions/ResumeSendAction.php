<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\ResumeSend;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ResumeSendAction
{
    public static function make(): Action
    {
        return Action::make('resume')
            ->label(__('email-composer::drafts.actions.resume_send'))
            ->icon(Heroicon::OutlinedPlayCircle)
            ->requiresConfirmation()
            ->modalDescription(fn (EmailDraft $record) => trans_choice('email-composer::drafts.confirmations.resume_send', static::unreached($record)))
            ->visible(fn (EmailDraft $record) => $record->status === EmailDraftStatus::Cancelled && static::unreached($record) > 0)
            ->action(fn (EmailDraft $record) => NotifiesDomainErrors::run(
                fn () => app(ResumeSend::class)($record),
                __('email-composer::drafts.notifications.not_resumed'),
                fn (int $count) => Notification::make()
                    ->success()
                    ->title(__('email-composer::drafts.notifications.send_resumed'))
                    ->body(trans_choice('email-composer::drafts.notifications.resumed', $count))
                    ->send(),
            ))
            ->authorize(fn (EmailDraft $record) => Gate::allows('resume', $record));
    }

    /** The recipients the cancelled send did not reach. */
    protected static function unreached(EmailDraft $draft): int
    {
        return $draft->recipients()->newPivotQuery()
            ->whereIn('delivery_status', [EmailDeliveryStatus::Cancelled->value, EmailDeliveryStatus::Failed->value])
            ->count();
    }
}
