<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('email-composer::templates.columns.name'))
                    ->searchable(),
                TextColumn::make('key')
                    ->label(__('email-composer::templates.columns.key'))
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('updated_at')
                    ->label(__('email-composer::templates.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->visibleFrom('md'),
            ])
            ->filters([
                TrashedFilter::make(),
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
