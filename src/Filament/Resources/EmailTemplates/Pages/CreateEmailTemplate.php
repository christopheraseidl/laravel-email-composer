<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages;

use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmailTemplate extends CreateRecord
{
    protected static string $resource = EmailTemplateResource::class;
}
