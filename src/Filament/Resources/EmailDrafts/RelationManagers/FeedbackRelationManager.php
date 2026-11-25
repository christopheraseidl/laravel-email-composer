<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers;

use CSeidl\EmailComposer\Actions\Feedback\ToggleFeedbackResolved;
use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Support\NameWithEmail;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

class FeedbackRelationManager extends RelationManager
{
    public const REFRESH_EVENT = 'email-composer-feedback-added';

    protected static string $relationship = 'feedback';

    // Re-renders the table when the page's Add feedback action saves a comment.
    #[On(self::REFRESH_EVENT)]
    public function refreshFeedback(): void {}

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('email-composer::drafts.feedback.title');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('email-composer::drafts.feedback.label'))
            ->pluralModelLabel(__('email-composer::drafts.feedback.plural_label'))
            ->columns([
                TextColumn::make('comment')
                    ->label(__('email-composer::drafts.feedback.columns.comment'))
                    ->limit(150)
                    ->wrap(),
                TextColumn::make('reviewer')
                    ->label(__('email-composer::drafts.feedback.columns.reviewer'))
                    ->state(fn (DraftFeedback $record): string => static::reviewerLabel($record))
                    ->visibleFrom('md'),
                IconColumn::make('resolved')
                    ->label(__('email-composer::drafts.feedback.columns.resolved'))
                    ->boolean()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label(__('email-composer::drafts.feedback.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                Action::make('viewFeedback')
                    ->label(__('email-composer::drafts.feedback.actions.view'))
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (DraftFeedback $record) => static::reviewerLabel($record))
                    ->schema([
                        TextEntry::make('comment')
                            ->hiddenLabel()
                            ->formatStateUsing(fn (string $state) => new HtmlString(nl2br(e($state)))),
                    ])
                    // Long comments scroll between a fixed heading and Close button.
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('email-composer::drafts.actions.close')),
                Action::make('toggleResolved')
                    ->label(fn (DraftFeedback $record) => __($record->resolved
                        ? 'email-composer::drafts.feedback.actions.reopen'
                        : 'email-composer::drafts.feedback.actions.resolve'))
                    ->icon(fn (DraftFeedback $record) => $record->resolved
                        ? Heroicon::OutlinedArrowUturnLeft
                        : Heroicon::OutlinedCheckCircle)
                    ->action(fn (DraftFeedback $record) => app(ToggleFeedbackResolved::class)($record))
                    ->authorize(fn (DraftFeedback $record) => Gate::allows('update', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user'));
    }

    /** The in-app user's name and email, or the external reviewer's. */
    protected static function reviewerLabel(DraftFeedback $record): string
    {
        $label = NameWithEmail::format(
            $record->user->name ?? $record->reviewer_name,
            $record->user->email ?? $record->reviewer_email,
        );

        return $label !== '' ? $label : __('email-composer::drafts.feedback.unknown_reviewer');
    }
}
