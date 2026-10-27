<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecipientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('email-composer::recipients.columns.name'))
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('email')
                    ->label(__('email-composer::recipients.columns.email'))
                    ->searchable(),
                TextColumn::make('locale')
                    ->label(__('email-composer::recipients.columns.locale'))
                    ->visibleFrom('md'),
                TextColumn::make('unsubscribed_at')
                    ->label(__('email-composer::recipients.columns.unsubscribed_at'))
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('md'),
                TextColumn::make('created_at')
                    ->label(__('email-composer::recipients.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
                TextColumn::make('updated_at')
                    ->label(__('email-composer::recipients.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->visibleFrom('md'),
            ])
            ->filters([
                TrashedFilter::make(),
                TernaryFilter::make('unsubscribed_at')
                    ->label(__('email-composer::recipients.filters.subscription.label'))
                    ->placeholder(__('email-composer::recipients.filters.subscription.all'))
                    ->trueLabel(__('email-composer::recipients.filters.subscription.unsubscribed'))
                    ->falseLabel(__('email-composer::recipients.filters.subscription.subscribed'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('unsubscribed_at'),
                        false: fn (Builder $query) => $query->whereNull('unsubscribed_at'),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('lists')
                    ->label(__('email-composer::recipients.filters.lists'))
                    ->relationship('lists', 'name')
                    ->preload(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
