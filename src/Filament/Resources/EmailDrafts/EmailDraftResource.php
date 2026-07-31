<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts;

use BackedEnum;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Schemas\EmailDraftForm;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Tables\EmailDraftsTable;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmailDraftResource extends Resource
{
    protected static ?string $model = EmailDraft::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return EmailDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailDraftsTable::configure($table);
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
            'index' => ListEmailDrafts::route('/'),
            'create' => CreateEmailDraft::route('/create'),
            'edit' => EditEmailDraft::route('/{record}/edit'),
        ];
    }

    public static function getTranslatableLocales(): array
    {
        return config('email-composer.locales');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
