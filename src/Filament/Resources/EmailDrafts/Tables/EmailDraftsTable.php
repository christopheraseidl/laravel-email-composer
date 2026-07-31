<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Tables;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class EmailDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')
                    ->sortable(),
                TextColumn::make('author_id')
                    ->label('Author')
                    ->formatStateUsing(function ($record): string {
                        if (! $record->author) {
                            return 'N/A';
                        }

                        $name = $record->author->name ?? '';
                        $email = $record->author->email ? " <{$record->author->email}>" : '';
                        $label = $name.$email;

                        return $label !== '' ? $label : "Author #{$record->author_id}";
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn ($record) => EmailDraftStatus::from($record->status)->label())
                    ->badge()
                    ->color(fn ($record) => EmailDraftStatus::from($record->status)->color()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options(collect(EmailDraftStatus::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('submit')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('warning')
                    ->action(fn ($record) => $record->submit())
                    ->authorize(fn ($record) => Gate::allows('submit', $record)),
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('info')
                    ->action(fn ($record) => $record->approve(auth()->user()))
                    ->authorize(fn ($record) => Gate::allows('approve', $record)),
                Action::make('send')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->action(fn ($record) => $record->send())
                    ->authorize(fn ($record) => Gate::allows('send', $record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('author'));
    }
}
