<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\SubmitDraft;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * @internal
 */
class SubmitDraftAction
{
    public static function make(): Action
    {
        return Action::make('submit')
            ->label(__('email-composer::drafts.actions.submit'))
            ->icon(Heroicon::OutlinedPaperClip)
            ->color('warning')
            ->action(fn ($record) => app(SubmitDraft::class)($record))
            ->authorize(fn ($record) => Gate::allows('submit', $record));
    }
}
