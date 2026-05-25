<?php

use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use CSeidl\EmailComposer\Models\EmailTemplate;

use function Pest\Livewire\livewire;

it('lists templates', function () {
    EmailTemplate::factory()->count(5)->create();

    livewire(ListEmailTemplates::class)
        ->assertCountTableRecords(5);
})->skip('panel registered in feat/language-switch');
