<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages;

use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListEmailDrafts extends ListRecords
{
    use Translatable;

    protected static string $resource = EmailDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            LocaleSwitcher::make(),
        ];
    }
}
