<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients;

use BackedEnum;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\CreateRecipient;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\EditRecipient;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\ListRecipients;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Schemas\RecipientForm;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Tables\RecipientsTable;
use CSeidl\EmailComposer\Models\Recipient;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class RecipientResource extends Resource
{
    protected static ?string $model = Recipient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return RecipientForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecipientsTable::configure($table);
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
            'index' => ListRecipients::route('/'),
            'create' => CreateRecipient::route('/create'),
            'edit' => EditRecipient::route('/{record}/edit'),
        ];
    }
}
