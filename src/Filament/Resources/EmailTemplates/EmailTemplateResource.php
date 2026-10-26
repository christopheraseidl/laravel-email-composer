<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailTemplates;

use BackedEnum;
use CSeidl\EmailComposer\Filament\Concerns\HasEmailComposerNavigation;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Schemas\EmailTemplateForm;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Tables\EmailTemplatesTable;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmailTemplateResource extends Resource
{
    use HasEmailComposerNavigation;

    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EmailTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmailTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'create' => CreateEmailTemplate::route('/create'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }

    public static function getModelLabel(): string
    {
        return __('email-composer::templates.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('email-composer::templates.plural_label');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
