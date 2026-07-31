<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages;

use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateEmailDraft extends CreateRecord
{
    use Translatable;

    protected static string $resource = EmailDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
