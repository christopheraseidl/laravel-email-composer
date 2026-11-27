<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\ApproveDraft;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * @internal
 */
class ApproveDraftAction
{
    public static function make(): Action
    {
        return Action::make('approve')
            ->label(__('email-composer::drafts.actions.approve'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('info')
            ->requiresConfirmation()
            ->action(fn ($record) => NotifiesDomainErrors::run(
                fn () => app(ApproveDraft::class)($record, auth()->authenticate()),
                __('email-composer::drafts.notifications.not_approved'),
            ))
            ->authorize(fn ($record) => Gate::allows('approve', $record));
    }
}
