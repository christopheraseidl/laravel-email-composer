<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\ReturnDraftToDraft;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\Action;
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
            ->action(fn (EmailDraft $record) => NotifiesDomainErrors::run(
                fn () => app(ReturnDraftToDraft::class)($record),
                __('email-composer::drafts.notifications.not_returned'),
            ))
            ->authorize(fn (EmailDraft $record) => Gate::allows('returnToDraft', $record));
    }
}
