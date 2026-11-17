<?php

use CSeidl\EmailComposer\Actions\Drafts\CancelSend;
use CSeidl\EmailComposer\Actions\Drafts\ResumeSend;
use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;

beforeEach(function () {
    config()->set('queue.default', 'database');
});

function resumedDelivery(EmailDraft $draft, string $status): Recipient
{
    $recipient = Recipient::factory()->create();
    $draft->recipients()->attach($recipient, ['delivery_status' => $status, 'queued_at' => now()->subHour()]);

    return $recipient;
}

function resumedStatus(EmailDraft $draft, Recipient $recipient): string
{
    return $draft->recipients()->findOrFail($recipient->getKey())->pivot->delivery_status;
}

it('sends to the cancelled and failed recipients alone and ends sent', function () {
    $draft = EmailDraft::factory()->cancelled()->create();
    $sent = resumedDelivery($draft, 'sent');
    $skipped = resumedDelivery($draft, 'skipped');
    $failed = resumedDelivery($draft, 'failed');
    $cancelled = resumedDelivery($draft, 'cancelled');
    $this->mock(EmailSenderInterface::class)
        ->shouldReceive('send')
        ->twice()
        ->withArgs(fn ($d, Recipient $r) => $r->is($failed) || $r->is($cancelled));

    expect(app(ResumeSend::class)($draft))->toBe(2)
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sending);

    workDatabaseQueue();
    $this->artisan('email-composer:queue-sends')->assertSuccessful();

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Sent)
        ->sent_at->not->toBeNull()
        ->and(resumedStatus($draft, $failed))->toBe('sent')
        ->and(resumedStatus($draft, $cancelled))->toBe('sent')
        ->and(resumedStatus($draft, $sent))->toBe('sent')
        ->and(resumedStatus($draft, $skipped))->toBe('skipped');
});

it('ends cancelled again when the resumed send is cancelled', function () {
    $draft = EmailDraft::factory()->cancelled()->create();
    $cancelled = resumedDelivery($draft, 'cancelled');
    $failed = resumedDelivery($draft, 'failed');

    app(ResumeSend::class)($draft);
    app(CancelSend::class)($draft);

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Cancelled)
        ->and(resumedStatus($draft, $cancelled))->toBe('cancelled')
        ->and(resumedStatus($draft, $failed))->toBe('cancelled');
});

it('refuses a draft that is not cancelled', function (string $state) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();
    $recipient = resumedDelivery($draft, 'cancelled');

    expect(fn () => app(ResumeSend::class)($draft))
        ->toThrow(DomainException::class, "Cannot resume draft #{$draft->id}: status is");

    expect(resumedStatus($draft, $recipient))->toBe('cancelled');
})->with(['draft', 'underReview', 'approved', 'sending', 'sent']);

it('refuses a draft every recipient was sent to or skipped', function () {
    $draft = EmailDraft::factory()->cancelled()->create();
    resumedDelivery($draft, 'sent');
    resumedDelivery($draft, 'skipped');

    expect(fn () => app(ResumeSend::class)($draft))
        ->toThrow(DomainException::class, 'every recipient was sent to or skipped');

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Cancelled);
});
