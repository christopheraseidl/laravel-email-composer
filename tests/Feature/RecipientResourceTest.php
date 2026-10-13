<?php

use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\ListRecipients;
use CSeidl\EmailComposer\Models\Recipient;

use function Pest\Livewire\livewire;

it('lists recipients with searchable table', function () {
    // Stays under the table's 10-per-page default so the target is on page one.
    Recipient::factory()->count(5)->create();
    $target = Recipient::factory()->create(['name' => 'Findable Person']);

    livewire(ListRecipients::class)
        ->assertCanSeeTableRecords([$target])
        ->searchTable('Findable')
        ->assertCanSeeTableRecords([$target])
        ->assertCountTableRecords(1);
});
