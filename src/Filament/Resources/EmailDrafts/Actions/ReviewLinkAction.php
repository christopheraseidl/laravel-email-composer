<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Review\ReviewLink;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

class ReviewLinkAction
{
    public static function make(): Action
    {
        return Action::make('reviewLink')
            ->label(__('email-composer::drafts.actions.review_link'))
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->authorize(fn (EmailDraft $record) => Gate::allows('share', $record))
            ->fillForm(fn (EmailDraft $record) => ['url' => ReviewLink::for($record)])
            ->schema([
                TextInput::make('url')
                    ->label(__('email-composer::drafts.fields.review_link'))
                    ->readOnly()
                    ->copyable(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('email-composer::drafts.actions.close'));
    }
}
