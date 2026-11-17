<?php

use CSeidl\EmailComposer\Actions\Drafts\CancelSend;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;

it('cancels the draft and every recipient not sent yet', function () {
    $draft = EmailDraft::factory()->sending()->create();
    [$sent, $queued, $waiting] = Recipient::factory()->count(3)->create()->all();
    $draft->recipients()->attach($sent, ['delivery_status' => 'sent']);
    $draft->recipients()->attach($queued, ['queued_at' => now()]);
    $draft->recipients()->attach($waiting);

    app(CancelSend::class)($draft);

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Cancelled)
        ->and($draft->recipients()->orderBy('email_composer_recipients.id')->pluck('delivery_status')->all())
        ->toBe(['sent', 'cancelled', 'cancelled']);
});

it('fails a cancelled retry\'s recipients again and keeps the draft sent', function () {
    $sentAt = now()->subHour();
    $draft = EmailDraft::factory()->sending()->create(['sent_at' => $sentAt]);
    [$sent, $retried] = Recipient::factory()->count(2)->create()->all();
    $draft->recipients()->attach($sent, ['delivery_status' => 'sent']);
    $draft->recipients()->attach($retried, ['queued_at' => now()]);

    app(CancelSend::class)($draft);

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Sent)
        ->sent_at->timestamp->toBe($sentAt->timestamp)
        ->and($draft->recipients()->findOrFail($retried->id)->pivot)
        ->delivery_status->toBe('failed')
        ->error->toBe('Retry cancelled.')
        ->and($draft->recipients()->findOrFail($sent->id)->pivot->delivery_status)->toBe('sent');
});

it('refuses a draft that is not sending', function (string $state) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();
    $draft->recipients()->attach(Recipient::factory()->create());

    expect(fn () => app(CancelSend::class)($draft))->toThrow(DomainException::class, 'Cannot cancel');

    expect($draft->recipients()->first()->pivot->delivery_status)->toBe('pending');
})->with(['draft', 'underReview', 'approved', 'sent', 'cancelled']);
