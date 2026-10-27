<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ReturnToDraftAction
{
    public static function make(): Action
    {
        return Action::make('returnToDraft')
            ->label(__('email-composer::drafts.actions.return_to_draft'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (EmailDraft $record) {
                try {
                    $record->returnToDraft();
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('email-composer::drafts.notifications.not_returned'))
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->authorize(fn (EmailDraft $record) => Gate::allows('returnToDraft', $record));
    }
}
