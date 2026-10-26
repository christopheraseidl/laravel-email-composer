<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\DeliveriesRelationManager;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;

use function Pest\Livewire\livewire;

function deliveriesFor(EmailDraft $draft): mixed
{
    return livewire(DeliveriesRelationManager::class, [
        'ownerRecord' => $draft,
        'pageClass' => EditEmailDraft::class,
    ]);
}

it('shows each recipient\'s delivery values', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $sent = Recipient::factory()->create(['email' => 'sent@example.com']);
    $failed = Recipient::factory()->create(['email' => 'failed@example.com']);
    $draft->recipients()->attach($sent, [
        'delivery_status' => EmailDeliveryStatus::Sent->value,
        'locale' => 'es',
        'sent_at' => now(),
        'attempts' => 1,
    ]);
    $draft->recipients()->attach($failed, [
        'delivery_status' => EmailDeliveryStatus::Failed->value,
        'error' => 'Mailbox unavailable',
        'attempts' => 3,
    ]);

    deliveriesFor($draft)
        ->assertCanSeeTableRecords([$sent, $failed])
        ->assertTableColumnStateSet('email', 'sent@example.com', $sent)
        ->assertTableColumnStateSet('pivot.locale', 'es', $sent)
        ->assertTableColumnFormattedStateSet('pivot.delivery_status', 'Sent', $sent)
        ->assertTableColumnStateSet('pivot.attempts', 1, $sent)
        ->assertTableColumnFormattedStateSet('pivot.delivery_status', 'Failed', $failed)
        ->assertTableColumnStateSet('pivot.attempts', 3, $failed)
        ->assertTableColumnStateSet('pivot.error', 'Mailbox unavailable', $failed);
});

it('shows recipients deleted after the send', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $recipient = Recipient::factory()->create();
    $draft->recipients()->attach($recipient, ['delivery_status' => EmailDeliveryStatus::Sent->value]);
    $recipient->delete();

    deliveriesFor($draft)->assertCanSeeTableRecords([$recipient]);
});

it('filters deliveries by status', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $sent = Recipient::factory()->create();
    $failed = Recipient::factory()->create();
    $draft->recipients()->attach($sent, ['delivery_status' => EmailDeliveryStatus::Sent->value]);
    $draft->recipients()->attach($failed, ['delivery_status' => EmailDeliveryStatus::Failed->value]);

    deliveriesFor($draft)
        ->filterTable('delivery_status', EmailDeliveryStatus::Failed->value)
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$sent]);
});

it('is hidden before the draft is sent', function (EmailDraftStatus $status) {
    $draft = EmailDraft::factory()->create(['status' => $status]);

    expect(DeliveriesRelationManager::canViewForRecord($draft, EditEmailDraft::class))->toBeFalse();
})->with([array_values(array_filter(EmailDraftStatus::cases(), fn ($case) => $case !== EmailDraftStatus::Sending && $case !== EmailDraftStatus::Sent))]);

it('is shown once the draft is sending or sent', function (EmailDraftStatus $status) {
    $draft = EmailDraft::factory()->create(['status' => $status]);

    expect(DeliveriesRelationManager::canViewForRecord($draft, EditEmailDraft::class))->toBeTrue();
})->with([EmailDraftStatus::Sending, EmailDraftStatus::Sent]);

it('is hidden from a user who cannot view the draft', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, string $ability) => ! in_array($ability, ['view-draft', 'view-any-draft'], true));

    $draft = EmailDraft::factory()->sent()->create();

    expect(DeliveriesRelationManager::canViewForRecord($draft, EditEmailDraft::class))->toBeFalse();
});
