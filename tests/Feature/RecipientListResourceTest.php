<?php

use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages\CreateRecipientList;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages\EditRecipientList;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\Pages\ListRecipientLists;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

it('lists recipient lists with their recipient counts', function () {
    $list = RecipientList::factory()->hasRecipients(3)->create();

    livewire(ListRecipientLists::class)
        ->assertCanSeeTableRecords([$list])
        ->assertTableColumnStateSet('recipients_count', 3, $list);
});

it('creates a list with recipients', function () {
    $recipients = Recipient::factory()->count(2)->create();

    livewire(CreateRecipientList::class)
        ->fillForm([
            'name' => 'Staff',
            'recipients' => $recipients->modelKeys(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RecipientList::firstWhere('name', 'Staff')->recipients->modelKeys())
        ->toEqualCanonicalizing($recipients->modelKeys());
});

it('rejects a duplicate name', function () {
    RecipientList::factory()->create(['name' => 'Staff']);

    livewire(CreateRecipientList::class)
        ->fillForm(['name' => 'Staff'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

it('updates the recipients of a list', function () {
    $list = RecipientList::factory()->hasRecipients(1)->create();
    $replacement = Recipient::factory()->create();

    livewire(EditRecipientList::class, ['record' => $list->getRouteKey()])
        ->fillForm(['recipients' => [$replacement->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($list->recipients()->pluck('email_composer_recipients.id')->all())->toBe([$replacement->id]);
});

it('shows trashed lists through the trashed filter', function () {
    $active = RecipientList::factory()->create();
    $trashed = RecipientList::factory()->trashed()->create();

    livewire(ListRecipientLists::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$trashed])
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$active]);
});

it('restores and force deletes a trashed list from the table', function () {
    $restored = RecipientList::factory()->trashed()->create();
    $removed = RecipientList::factory()->trashed()->create();

    livewire(ListRecipientLists::class)
        ->filterTable('trashed', false)
        ->callAction(TestAction::make('restore')->table($restored))
        ->callAction(TestAction::make('forceDelete')->table($removed));

    expect($restored->fresh()->trashed())->toBeFalse()
        ->and(RecipientList::withTrashed()->find($removed->id))->toBeNull();
});

it('restores and force deletes trashed lists in bulk', function () {
    $restored = RecipientList::factory()->trashed()->count(2)->create();
    $removed = RecipientList::factory()->trashed()->count(2)->create();

    livewire(ListRecipientLists::class)
        ->filterTable('trashed', false)
        ->selectTableRecords($restored)
        ->callAction(TestAction::make('restore')->table()->bulk());

    livewire(ListRecipientLists::class)
        ->filterTable('trashed', false)
        ->selectTableRecords($removed)
        ->callAction(TestAction::make('forceDelete')->table()->bulk());

    expect(RecipientList::count())->toBe(2)
        ->and(RecipientList::withTrashed()->count())->toBe(2);
});
