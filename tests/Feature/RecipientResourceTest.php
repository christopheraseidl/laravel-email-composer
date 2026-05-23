<?php

use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\ListRecipients;
use CSeidl\EmailComposer\Models\Recipient;

use function Pest\Livewire\livewire;

it('lists recipients with searchable table', function () {
    Recipient::factory()->count(30)->create();
    $target = Recipient::factory()->create(['name' => 'Findable Person']);

    livewire(ListRecipients::class)
        ->assertCanSeeTableRecords([$target])
        ->searchTable('Findable')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
})->skip('panel registered in feat/language-switch');
