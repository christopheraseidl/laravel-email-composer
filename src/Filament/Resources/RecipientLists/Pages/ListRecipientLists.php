<?php

namespace CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages;

use CSeidl\EmailComposer\Filament\Resources\RecipientLists\RecipientListResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * @internal
 */
class ListRecipientLists extends ListRecords
{
    protected static string $resource = RecipientListResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
