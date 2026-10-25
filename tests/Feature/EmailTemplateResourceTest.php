<?php

use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Filament\Actions\RestoreAction;
use Filament\Tables\Filters\TrashedFilter;

use function Pest\Livewire\livewire;

it('lists templates', function () {
    EmailTemplate::factory()->count(5)->create();

    livewire(ListEmailTemplates::class)
        ->assertCountTableRecords(5);
});

it('shows template name and key', function () {
    $template = EmailTemplate::factory()->create(['name' => 'Welcome Mail', 'key' => 'welcome-mail']);

    livewire(ListEmailTemplates::class)
        ->assertCanSeeTableRecords([$template])
        ->assertTableColumnStateSet('name', 'Welcome Mail', $template)
        ->assertTableColumnStateSet('key', 'welcome-mail', $template);
});

it('shows trashed templates under the trashed filter and restores them', function () {
    $active = EmailTemplate::factory()->create();
    $trashed = EmailTemplate::factory()->create();
    $trashed->delete();

    livewire(ListEmailTemplates::class)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$trashed])
        ->filterTable(TrashedFilter::class, false)
        ->assertCanSeeTableRecords([$trashed])
        ->assertCanNotSeeTableRecords([$active]);

    livewire(EditEmailTemplate::class, ['record' => $trashed->getRouteKey()])
        ->callAction(RestoreAction::class);

    expect($trashed->fresh()->trashed())->toBeFalse();
});
