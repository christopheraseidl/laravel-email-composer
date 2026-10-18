<?php

use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\EditRecipient;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\ListRecipients;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;

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

it('filters recipients by list', function () {
    $list = RecipientList::factory()->create();
    $member = Recipient::factory()->create();
    $outsider = Recipient::factory()->create();
    $list->recipients()->attach($member);

    livewire(ListRecipients::class)
        ->filterTable('lists', $list->id)
        ->assertCanSeeTableRecords([$member])
        ->assertCanNotSeeTableRecords([$outsider]);
});

it('assigns lists from the recipient form', function () {
    $list = RecipientList::factory()->create();
    $recipient = Recipient::factory()->create(['locale' => null]);

    livewire(EditRecipient::class, ['record' => $recipient->getRouteKey()])
        ->fillForm(['lists' => [$list->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($recipient->lists->modelKeys())->toBe([$list->id]);
});

it('deletes and restores a recipient', function () {
    $recipient = Recipient::factory()->create();

    livewire(EditRecipient::class, ['record' => $recipient->getRouteKey()])
        ->callAction(DeleteAction::class);

    expect($recipient->fresh()->trashed())->toBeTrue();

    livewire(EditRecipient::class, ['record' => $recipient->getRouteKey()])
        ->callAction(RestoreAction::class);

    expect($recipient->fresh()->trashed())->toBeFalse();
});
