<?php

namespace CSeidl\EmailComposer\Filament\Resources\RecipientLists;

use BackedEnum;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages\CreateRecipientList;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages\EditRecipientList;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages\ListRecipientLists;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Schemas\RecipientListForm;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Tables\RecipientListsTable;
use CSeidl\EmailComposer\Models\RecipientList;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecipientListResource extends Resource
{
    protected static ?string $model = RecipientList::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RecipientListForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipientListsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecipientLists::route('/'),
            'create' => CreateRecipientList::route('/create'),
            'edit' => EditRecipientList::route('/{record}/edit'),
        ];
    }

    public static function getNavigationGroup(): ?string
    {
        return EmailComposer::config()->navigationGroup();
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
