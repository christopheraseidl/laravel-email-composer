<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\DeliveriesRelationManager;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\SendFailure;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;

use function Pest\Livewire\livewire;

function deliveriesFor(EmailDraft $draft): mixed
{
    return livewire(DeliveriesRelationManager::class, [
        'ownerRecord' => $draft,
        'pageClass' => EditEmailDraft::class,
    ]);
}

function deliveryWithFailures(EmailDraft $draft, string $status, int $failures): Recipient
{
    $recipient = Recipient::factory()->create();
    $draft->recipients()->attach($recipient, ['delivery_status' => $status]);
    SendFailure::factory()->count($failures)->create(['email_draft_id' => $draft->id, 'recipient_id' => $recipient->id]);

    return $recipient;
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
})->with([EmailDraftStatus::Draft, EmailDraftStatus::UnderReview, EmailDraftStatus::Approved]);

it('is shown once the draft is sending, sent or cancelled', function (EmailDraftStatus $status) {
    $draft = EmailDraft::factory()->create(['status' => $status]);

    expect(DeliveriesRelationManager::canViewForRecord($draft, EditEmailDraft::class))->toBeTrue();
})->with([EmailDraftStatus::Sending, EmailDraftStatus::Sent, EmailDraftStatus::Cancelled]);

it('lists the deliveries of a cancelled draft', function () {
    $draft = EmailDraft::factory()->cancelled()->create();
    $recipient = Recipient::factory()->create();
    $draft->recipients()->attach($recipient, ['delivery_status' => EmailDeliveryStatus::Cancelled->value]);

    deliveriesFor($draft)
        ->assertCanSeeTableRecords([$recipient])
        ->assertTableColumnFormattedStateSet('pivot.delivery_status', 'Cancelled', $recipient);
});

it('counts each recipient\'s failed attempts for this draft', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $failed = deliveryWithFailures($draft, 'failed', 3);
    $sent = deliveryWithFailures($draft, 'sent', 1);
    $clean = deliveryWithFailures($draft, 'sent', 0);
    SendFailure::factory()->create(['recipient_id' => $failed->id]);

    deliveriesFor($draft)
        ->assertTableColumnStateSet('failed_attempts', 3, $failed)
        ->assertTableColumnStateSet('failed_attempts', 1, $sent)
        ->assertTableColumnStateSet('failed_attempts', 0, $clean);
});

it('lists a recipient\'s failures for this draft, newest first', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $recipient = deliveryWithFailures($draft, 'sent', 0);
    $clean = deliveryWithFailures($draft, 'sent', 0);
    SendFailure::factory()->create(['email_draft_id' => $draft->id, 'recipient_id' => $recipient->id, 'error' => 'First error', 'failed_at' => now()->subMinutes(2)]);
    SendFailure::factory()->create(['email_draft_id' => $draft->id, 'recipient_id' => $recipient->id, 'error' => 'Second error', 'failed_at' => now()->subMinute()]);
    SendFailure::factory()->create(['recipient_id' => $recipient->id, 'error' => 'Other draft error']);

    deliveriesFor($draft)
        ->assertActionHidden(TestAction::make('failures')->table($clean))
        ->mountAction(TestAction::make('failures')->table($recipient))
        ->assertMountedActionModalSee(['Second error', 'First error'])
        ->assertMountedActionModalDontSee('Other draft error')
        ->assertSeeInOrder(['Second error', 'First error']);
});

it('shows Retry on failed rows only, once the draft has been sent', function (string $state) {
    $draft = EmailDraft::factory()->{$state}()->create();
    $failed = deliveryWithFailures($draft, 'failed', 0);
    $sent = deliveryWithFailures($draft, 'sent', 0);

    deliveriesFor($draft)
        ->assertActionVisible(TestAction::make('retry')->table($failed))
        ->assertActionHidden(TestAction::make('retry')->table($sent))
        ->assertActionVisible(TestAction::make('retry_failed')->table());
})->with(['sending', 'sent', 'cancelled']);

it('hides Retry without send-draft', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, string $ability) => $ability !== 'send-draft');
    $draft = EmailDraft::factory()->sent()->create();
    $failed = deliveryWithFailures($draft, 'failed', 0);

    deliveriesFor($draft)
        ->assertActionHidden(TestAction::make('retry')->table($failed))
        ->assertActionHidden(TestAction::make('retry_selected')->table()->bulk())
        ->assertActionHidden(TestAction::make('retry_failed')->table());
});

it('retries a failed row and names it', function () {
    config()->set('queue.default', 'database');
    $draft = EmailDraft::factory()->sent()->create();
    $failed = deliveryWithFailures($draft, 'failed', 2);
    $other = deliveryWithFailures($draft, 'failed', 0);

    deliveriesFor($draft)
        ->mountAction(TestAction::make('retry')->table($failed))
        ->assertMountedActionModalSee([
            "Retry delivery to {$failed->email}?",
            "Only {$failed->email} will be sent this email again. Other failed recipients are not affected.",
        ])
        ->callMountedAction()
        ->assertNotified(Notification::make()
            ->success()
            ->title('Retry started')
            ->body("Sending to {$failed->email} again."));

    expect($draft->recipients()->findOrFail($failed->id)->pivot->delivery_status)->toBe('pending')
        ->and($draft->recipients()->findOrFail($other->id)->pivot->delivery_status)->toBe('failed')
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sending);
});

it('lets only failed rows be selected', function () {
    $draft = EmailDraft::factory()->sent()->create();
    deliveryWithFailures($draft, 'failed', 0);
    deliveryWithFailures($draft, 'failed', 0);
    deliveryWithFailures($draft, 'sent', 0);

    expect(deliveriesFor($draft)->instance()->getAllSelectableTableRecordsCount())->toBe(2);
});

it('retries the selected rows', function () {
    config()->set('queue.default', 'database');
    $draft = EmailDraft::factory()->sent()->create();
    $chosen = deliveryWithFailures($draft, 'failed', 0);
    $other = deliveryWithFailures($draft, 'failed', 0);
    deliveryWithFailures($draft, 'sent', 0);

    deliveriesFor($draft)
        ->selectTableRecords([$chosen])
        ->mountAction(TestAction::make('retry_selected')->table()->bulk())
        ->assertMountedActionModalSee('The selected recipient will be sent this email again.')
        ->callMountedAction()
        ->assertNotified(Notification::make()
            ->success()
            ->title('Retry started')
            ->body('Sending to 1 recipient again.'));

    expect($draft->recipients()->orderBy('email_composer_recipients.id')->pluck('delivery_status')->all())
        ->toBe(['pending', 'failed', 'sent']);
});

it('retries every failed row', function () {
    config()->set('queue.default', 'database');
    $draft = EmailDraft::factory()->sent()->create();
    deliveryWithFailures($draft, 'failed', 0);
    deliveryWithFailures($draft, 'sent', 0);
    deliveryWithFailures($draft, 'failed', 0);

    deliveriesFor($draft)
        ->assertActionHasLabel(TestAction::make('retry_failed')->table(), 'Retry all failed (2)')
        ->mountAction(TestAction::make('retry_failed')->table())
        ->assertMountedActionModalSee('All 2 failed recipients will be sent this email again.')
        ->callMountedAction()
        ->assertNotified(Notification::make()
            ->success()
            ->title('Retry started')
            ->body('Sending to 2 recipients again.'));

    expect($draft->recipients()->orderBy('email_composer_recipients.id')->pluck('delivery_status')->all())
        ->toBe(['pending', 'sent', 'pending']);
});

it('hides Retry all failed when none failed', function () {
    $draft = EmailDraft::factory()->sent()->create();
    deliveryWithFailures($draft, 'sent', 0);

    deliveriesFor($draft)->assertActionHidden(TestAction::make('retry_failed')->table());
});

it('reports a retry with nothing to retry', function () {
    $draft = EmailDraft::factory()->sent()->create();
    $failed = deliveryWithFailures($draft, 'failed', 0);
    $component = deliveriesFor($draft)->selectTableRecords([$failed]);
    // Sent by another worker before the click.
    $draft->recipients()->updateExistingPivot($failed->id, ['delivery_status' => 'sent']);

    $component
        ->callAction(TestAction::make('retry_selected')->table()->bulk())
        ->assertNotified(Notification::make()
            ->danger()
            ->title('Retry not started')
            ->body("Cannot retry draft #{$draft->id}: none of the chosen recipients failed or was cancelled."));

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('summarizes the statuses and failed attempts', function () {
    $draft = EmailDraft::factory()->sent()->create();
    deliveryWithFailures($draft, 'sent', 1);
    deliveryWithFailures($draft, 'sent', 0);
    deliveryWithFailures($draft, 'failed', 3);

    deliveriesFor($draft)
        ->assertSee('Pending: 0 · Sent: 2 · Failed: 1 · Skipped: 0 · Cancelled: 0 · Failed attempts: 4')
        ->assertDontSee('Queued:');
});

it('summarizes what is queued while sending', function () {
    $draft = EmailDraft::factory()->sending()->create();
    $draft->recipients()->attach(Recipient::factory()->create(), ['queued_at' => now()]);
    $draft->recipients()->attach(Recipient::factory()->count(2)->create()->modelKeys());

    deliveriesFor($draft)
        ->assertSee('Pending: 3 · Sent: 0 · Failed: 0 · Skipped: 0 · Cancelled: 0 · Failed attempts: 0 · Queued: 1 · Waiting for the rate limits: 2');
});

it('is hidden from a user who cannot view the draft', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, string $ability) => ! in_array($ability, ['view-draft', 'view-any-draft'], true));

    $draft = EmailDraft::factory()->sent()->create();

    expect(DeliveriesRelationManager::canViewForRecord($draft, EditEmailDraft::class))->toBeFalse();
});
