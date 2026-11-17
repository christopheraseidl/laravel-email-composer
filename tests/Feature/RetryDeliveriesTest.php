<?php

use CSeidl\EmailComposer\Actions\Drafts\RetryDeliveries;
use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\SendFailure;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('queue.default', 'database');
});

function retriedDraft(): EmailDraft
{
    return EmailDraft::factory()->sent()->create();
}

function deliveryIn(EmailDraft $draft, string $status): Recipient
{
    $recipient = Recipient::factory()->create();
    $draft->recipients()->attach($recipient, [
        'delivery_status' => $status,
        'error' => $status === 'failed' ? 'SMTP down' : null,
        'sent_at' => $status === 'sent' ? now()->subHour() : null,
        'attempts' => $status === 'failed' ? 3 : 1,
        'queued_at' => now()->subHour(),
    ]);

    return $recipient;
}

function retryRow(EmailDraft $draft, Recipient $recipient): object
{
    return $draft->recipients()->findOrFail($recipient->getKey())->pivot;
}

it('sends again to the chosen failed recipients alone and keeps their failure rows', function () {
    $draft = retriedDraft();
    $chosen = deliveryIn($draft, 'failed');
    $other = deliveryIn($draft, 'failed');
    $sent = deliveryIn($draft, 'sent');
    $skipped = deliveryIn($draft, 'skipped');
    SendFailure::factory()->count(3)->create(['email_draft_id' => $draft->id, 'recipient_id' => $chosen->id]);
    $this->mock(EmailSenderInterface::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(fn ($d, Recipient $r) => $r->is($chosen));

    $count = app(RetryDeliveries::class)($draft, [$chosen->id, $sent->id, $skipped->id]);

    expect($count)->toBe(1)
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sending)
        ->and(retryRow($draft, $chosen))
        ->delivery_status->toBe('pending')
        ->error->toBeNull()
        ->attempts->toBe(0);

    workDatabaseQueue();

    expect(retryRow($draft, $chosen))
        ->delivery_status->toBe('sent')
        ->attempts->toBe(1)
        ->and(retryRow($draft, $other))->delivery_status->toBe('failed')->error->toBe('SMTP down')
        ->and(retryRow($draft, $sent))->delivery_status->toBe('sent')->attempts->toBe(1)
        ->and(retryRow($draft, $skipped))->delivery_status->toBe('skipped')
        ->and(SendFailure::where('recipient_id', $chosen->id)->count())->toBe(3);
});

it('ends the draft sent with its first sent_at', function () {
    $draft = retriedDraft();
    $firstSentAt = $draft->sent_at;
    $recipient = deliveryIn($draft, 'failed');
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->once();
    $this->travel(1)->day();

    app(RetryDeliveries::class)($draft, [$recipient->id]);
    workDatabaseQueue();
    $this->artisan('email-composer:queue-sends')->assertSuccessful();

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Sent)
        ->sent_at->timestamp->toBe($firstSentAt->timestamp);
});

it('queues no more than the rate limit allows', function () {
    config(['email-composer.queue.rate_limit_per_minute' => 2]);
    $draft = retriedDraft();
    $recipients = collect(range(1, 3))->map(fn () => deliveryIn($draft, 'failed'));
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->times(3);

    app(RetryDeliveries::class)($draft, $recipients->pluck('id')->all());

    expect(DB::table('jobs')->count())->toBe(2);

    workDatabaseQueue();

    expect($recipients->map(fn (Recipient $r) => retryRow($draft, $r)->delivery_status)->all())
        ->toBe(['sent', 'sent', 'pending']);

    $this->travel(61)->seconds();
    $this->artisan('email-composer:queue-sends')->assertSuccessful();
    workDatabaseQueue();
    $this->artisan('email-composer:queue-sends')->assertSuccessful();

    expect(retryRow($draft, $recipients->last())->delivery_status)->toBe('sent')
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('retries more recipients while a retry is sending', function () {
    $draft = retriedDraft();
    [$first, $second] = [deliveryIn($draft, 'failed'), deliveryIn($draft, 'failed')];
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->twice();

    app(RetryDeliveries::class)($draft, [$first->id]);
    app(RetryDeliveries::class)($draft, [$second->id]);

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sending)
        ->and(retryRow($draft, $second)->delivery_status)->toBe('pending');

    workDatabaseQueue();
    $this->artisan('email-composer:queue-sends')->assertSuccessful();

    expect(retryRow($draft, $first)->delivery_status)->toBe('sent')
        ->and(retryRow($draft, $second)->delivery_status)->toBe('sent')
        ->and($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('retries a failed recipient during the first send', function () {
    $draft = EmailDraft::factory()->sending()->create();
    $recipient = deliveryIn($draft, 'failed');

    app(RetryDeliveries::class)($draft, [$recipient->id]);

    expect(retryRow($draft, $recipient)->delivery_status)->toBe('pending')
        ->and($draft->fresh())
        ->status->toBe(EmailDraftStatus::Sending)
        ->sent_at->toBeNull();
});

it('retries the failed recipients of a cancelled draft and leaves it cancelled', function () {
    $draft = EmailDraft::factory()->cancelled()->create();
    $failed = deliveryIn($draft, 'failed');
    $cancelled = deliveryIn($draft, 'cancelled');
    $this->mock(EmailSenderInterface::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(fn ($d, Recipient $r) => $r->is($failed));

    app(RetryDeliveries::class)($draft, [$failed->id]);

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sending);

    workDatabaseQueue();
    $this->artisan('email-composer:queue-sends')->assertSuccessful();

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Cancelled)
        ->sent_at->toBeNull()
        ->and(retryRow($draft, $failed)->delivery_status)->toBe('sent')
        ->and(retryRow($draft, $cancelled)->delivery_status)->toBe('cancelled');
});

it('refuses a draft that has not been sent', function (string $state) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();
    $recipient = deliveryIn($draft, 'failed');

    expect(fn () => app(RetryDeliveries::class)($draft, [$recipient->id]))
        ->toThrow(DomainException::class, "Cannot retry draft #{$draft->id}: status is");

    expect(retryRow($draft, $recipient)->delivery_status)->toBe('failed')
        ->and($draft->fresh()->status)->toBe($draft->status);
})->with(['draft', 'underReview', 'approved']);

it('refuses a choice with no failed recipients', function () {
    $draft = retriedDraft();
    $sent = deliveryIn($draft, 'sent');
    deliveryIn($draft, 'failed');

    expect(fn () => app(RetryDeliveries::class)($draft, [$sent->id]))
        ->toThrow(DomainException::class, 'none of the chosen recipients failed');

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sent)
        ->and(DB::table('jobs')->count())->toBe(0);
});
