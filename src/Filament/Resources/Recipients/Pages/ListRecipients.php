<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients\Pages;

use CSeidl\EmailComposer\Filament\Resources\Recipients\RecipientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecipients extends ListRecords
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
