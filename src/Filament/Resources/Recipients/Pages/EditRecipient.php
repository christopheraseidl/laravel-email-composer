<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients\Pages;

use CSeidl\EmailComposer\Filament\Resources\Recipients\RecipientResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecipient extends EditRecord
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
