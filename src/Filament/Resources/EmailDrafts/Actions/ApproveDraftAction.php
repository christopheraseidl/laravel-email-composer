<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ApproveDraftAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label(__('email-composer::drafts.actions.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('info')
            ->requiresConfirmation()
            ->action(function ($record) {
                try {
                    $record->approve(auth()->user());
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('email-composer::drafts.notifications.not_approved'))
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->authorize(fn ($record) => Gate::allows('approve', $record));
    }
}
