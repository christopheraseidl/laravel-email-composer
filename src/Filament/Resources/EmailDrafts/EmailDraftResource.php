<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts;

use BackedEnum;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Concerns\HasEmailComposerNavigation;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\DeliveriesRelationManager;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\FeedbackRelationManager;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Schemas\EmailDraftForm;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Tables\EmailDraftsTable;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EmailDraftResource extends Resource
{
    use HasEmailComposerNavigation;
    use Translatable;

    protected static ?string $model = EmailDraft::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'localized_subject';

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
            FeedbackRelationManager::class,
            DeliveriesRelationManager::class,
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

    /** @return array<int, string> */
    public static function getTranslatableLocales(): array
    {
        return EmailComposer::config()->locales();
    }

    // Open the form in the panel's active language (set by LanguageSwitch).
    public static function getDefaultTranslatableLocale(): string
    {
        $locale = app()->getLocale();

        return in_array($locale, static::getTranslatableLocales(), true)
            ? $locale
            : static::getTranslatableLocales()[0];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
