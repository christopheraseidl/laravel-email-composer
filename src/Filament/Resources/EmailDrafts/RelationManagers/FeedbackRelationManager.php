<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers;

use CSeidl\EmailComposer\Models\DraftFeedback;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class FeedbackRelationManager extends RelationManager
{
    protected static string $relationship = 'feedback';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('comment')
                    ->wrap(),
                TextColumn::make('reviewer')
                    ->state(fn (DraftFeedback $record): string => static::reviewerLabel($record)),
                IconColumn::make('resolved')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('toggleResolved')
                    ->label(fn (DraftFeedback $record) => $record->resolved ? 'Reopen' : 'Resolve')
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

        return $label !== '' ? $label : 'Unknown reviewer';
    }
}
