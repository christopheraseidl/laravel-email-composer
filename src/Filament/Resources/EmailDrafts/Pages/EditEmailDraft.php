<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages;

use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditEmailDraft extends EditRecord
{
    use Translatable;

    protected static string $resource = EmailDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            LocaleSwitcher::make(),
        ];
    }
}
