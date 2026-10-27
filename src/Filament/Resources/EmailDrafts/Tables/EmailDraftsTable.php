<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Tables;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmailDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject'),
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
                    ->formatStateUsing(fn ($record) => $record->status->label())
                    ->badge()
                    ->color(fn ($record) => $record->status->color()),
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
            ->recordUrl(fn (EmailDraft $record): ?string => EmailDraftResource::canView($record) ? EmailDraftResource::getUrl('edit', ['record' => $record]) : null)
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
