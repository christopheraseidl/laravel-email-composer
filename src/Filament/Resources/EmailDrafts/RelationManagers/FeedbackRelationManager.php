<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers;

use CSeidl\EmailComposer\Models\DraftFeedback;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class FeedbackRelationManager extends RelationManager
{
    protected static string $relationship = 'feedback';

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
                Action::make('toggleResolved')
                    ->label(fn (DraftFeedback $record) => __($record->resolved
                        ? 'email-composer::drafts.feedback.actions.reopen'
                        : 'email-composer::drafts.feedback.actions.resolve'))
                    ->icon(fn (DraftFeedback $record) => $record->resolved
                        ? Heroicon::OutlinedArrowUturnLeft
                        : Heroicon::OutlinedCheckCircle)
                    ->action(fn (DraftFeedback $record) => $record->update(['resolved' => ! $record->resolved]))
                    ->authorize(fn (DraftFeedback $record) => Gate::allows('update', $record)),
            ])
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user'));
    }

    /** The in-app user's name and email, or the external reviewer's. */
    protected static function reviewerLabel(DraftFeedback $record): string
    {
        $name = $record->user->name ?? $record->reviewer_name ?? '';
        $email = $record->user->email ?? $record->reviewer_email;
        $label = trim($name.($email ? " <{$email}>" : ''));

        return $label !== '' ? $label : __('email-composer::drafts.feedback.unknown_reviewer');
    }
}
