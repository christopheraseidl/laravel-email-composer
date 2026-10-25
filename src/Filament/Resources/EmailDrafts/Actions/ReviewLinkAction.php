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
    /** Shared by the drafts table and the edit page header. */
    public static function make(): Action
    {
        return Action::make('reviewLink')
            ->label('Review link')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->authorize(fn (EmailDraft $record) => Gate::allows('share', $record))
            ->fillForm(fn (EmailDraft $record) => ['url' => ReviewLink::for($record)])
            ->schema([
                TextInput::make('url')
                    ->label('Review link')
                    ->readOnly()
                    ->copyable(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }
}
