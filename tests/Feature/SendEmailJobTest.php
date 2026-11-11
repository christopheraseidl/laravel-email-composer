<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

beforeEach(function () {
    Storage::fake(config('email-composer.storage.disk'));
    config()->set('email-composer.locales', ['en', 'es']);
    config()->set('email-composer.default_locale', 'en');
});

function sendableDraft(): EmailDraft
{
    $template = EmailTemplate::factory()->create([
        'body' => '<div>[[ greeting ]]</div>',
        'placeholders' => ['greeting'],
    ]);

    $draft = EmailDraft::factory()->underReview()->create([
        'email_template_id' => $template->id,
        'subject' => ['en' => 'Spring update', 'es' => 'Novedades de primavera', 'fr' => 'Nouvelles du printemps'],
        'placeholders' => [
            'en' => ['greeting' => 'Hello there'],
            'es' => ['greeting' => 'Hola a todos'],
            'fr' => ['greeting' => 'Bonjour tout le monde'],
        ],
    ]);

    $draft->approve(UserFactory::new()->create());

    return $draft;
}

function attachedRecipient(EmailDraft $draft, array $attributes = []): Recipient
{
    $recipient = Recipient::factory()->create(['locale' => 'en', ...$attributes]);
    $draft->recipients()->attach($recipient->getKey());

    return $recipient;
}

/** A job for the recipient's row, stamped as QueueSends stamps it. */
function queuedJob(EmailDraft $draft, Recipient $recipient): SendEmailJob
{
    $queuedAt = now();
    $draft->recipients()->updateExistingPivot($recipient->getKey(), ['queued_at' => $queuedAt]);

    return new SendEmailJob($draft, $recipient, $queuedAt);
}

function runSendJob(EmailDraft $draft, Recipient $recipient): void
{
    app()->call([queuedJob($draft, $recipient), 'handle']);
}

function pivotRow(EmailDraft $draft, Recipient $recipient): object
{
    return $draft->recipients()->findOrFail($recipient->getKey())->pivot;
}

/** @return Collection<int, Recipient> */
function queueSends(EmailDraft $draft, int $count): Collection
{
    config()->set('queue.default', 'database');
    $recipients = collect(range(1, $count))->map(fn () => attachedRecipient($draft));
    $recipients->each(fn (Recipient $recipient) => dispatch(queuedJob($draft, $recipient)));

    return $recipients;
}

/** @param  Collection<int, Recipient>  $recipients */
function deliveryStatuses(EmailDraft $draft, Collection $recipients): array
{
    return $recipients->map(fn (Recipient $recipient) => pivotRow($draft, $recipient)->delivery_status)->all();
}

it('successfully sends an email', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft, ['locale' => 'es']);
    $this->mock(EmailSenderInterface::class)
        ->shouldReceive('send')
        ->once()
        ->withArgs(fn ($d, $r, $locale) => $d->is($draft) && $r->is($recipient) && $locale === 'es');

    runSendJob($draft, $recipient);

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('sent')
        ->locale->toBe('es')
        ->sent_at->not->toBeNull()
        ->attempts->toBe(1);
});

it('skips an unsubscribed recipient', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft, ['unsubscribed_at' => now()]);
    $this->mock(EmailSenderInterface::class)->shouldNotReceive('send');

    runSendJob($draft, $recipient);

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('skipped')
        ->sent_at->toBeNull()
        ->attempts->toBe(0);
});

it('skips a soft deleted recipient', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft);
    $recipient->delete();
    $this->mock(EmailSenderInterface::class)->shouldNotReceive('send');

    runSendJob($draft, $recipient);

    expect($draft->recipients()->withTrashed()->findOrFail($recipient->getKey())->pivot)
        ->delivery_status->toBe('skipped')
        ->sent_at->toBeNull()
        ->attempts->toBe(0);
});

it('sends nothing for a row that was cancelled', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft);
    $job = queuedJob($draft, $recipient);
    $draft->recipients()->updateExistingPivot($recipient->getKey(), ['delivery_status' => 'cancelled']);
    $this->mock(EmailSenderInterface::class)->shouldNotReceive('send');

    app()->call([$job, 'handle']);

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('cancelled')
        ->attempts->toBe(0);
});

it('sends nothing for a row queued again since', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft);
    $stale = queuedJob($draft, $recipient);
    $this->travel(1)->minute();
    queuedJob($draft, $recipient);
    $this->mock(EmailSenderInterface::class)->shouldNotReceive('send');

    app()->call([$stale, 'handle']);
    $stale->failed(new RuntimeException('late'));

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('pending')
        ->error->toBeNull()
        ->attempts->toBe(0);
});

it('records the failure and its error', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft);

    queuedJob($draft, $recipient)->failed(new RuntimeException('SMTP down'));

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('failed')
        ->error->toBe('SMTP down');
});

it('counts an attempt that throws', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft);
    $this->mock(EmailSenderInterface::class)
        ->shouldReceive('send')
        ->andThrow(new RuntimeException('SMTP down'));

    expect(fn () => runSendJob($draft, $recipient))->toThrow(RuntimeException::class, 'SMTP down');
    expect(fn () => runSendJob($draft, $recipient))->toThrow(RuntimeException::class);

    expect(pivotRow($draft, $recipient))
        ->attempts->toBe(2)
        ->delivery_status->toBe('pending');
});

it('sends to the specified locale', function () {
    Mail::fake();
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft, ['locale' => 'fr']);

    runSendJob($draft, $recipient);

    $mail = Mail::sent(ComposedEmail::class)->sole();
    expect($mail->locale)->toBe('fr');
    $mail->assertSeeInHtml('Bonjour tout le monde')
        ->assertDontSeeInHtml('Hello there');
    expect(pivotRow($draft, $recipient)->locale)->toBe('fr');
});

it('defaults to fallback when locale is unknown', function () {
    Mail::fake();
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft, ['locale' => 'de']);

    runSendJob($draft, $recipient);

    $mail = Mail::sent(ComposedEmail::class)->sole();
    expect($mail->locale)->toBe('en');
    $mail->assertSeeInHtml('Hello there');
    expect(pivotRow($draft, $recipient)->locale)->toBe('en');
});

it('allows three exceptions with a 30 second backoff', function () {
    $job = new SendEmailJob(sendableDraft(), Recipient::factory()->create(), now());

    expect($job)->maxExceptions->toBe(3)->backoff->toBe(30);
});

it('retries for a day', function () {
    $this->freezeTime();
    config(['email-composer.queue.rate_limit_per_day' => 1000]);

    $job = new SendEmailJob(sendableDraft(), Recipient::factory()->create(), now());

    expect($job->retryUntil()->timestamp)->toBe(now()->addDay()->timestamp);
});

it('sends the configured number per window and hands back the rest until it ends', function (array $limits, int $window) {
    config(collect($limits)->mapWithKeys(fn ($value, $key) => ["email-composer.queue.{$key}" => $value])->all());
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->times(3);
    $draft = sendableDraft();
    $sent = queueSends($draft, 2);

    workDatabaseQueue();

    expect(deliveryStatuses($draft, $sent))->toBe(['sent', 'sent']);

    $this->travel($window - 5)->seconds();
    [$held] = queueSends($draft, 1)->all();
    workDatabaseQueue();

    expect(pivotRow($draft, $held))->delivery_status->toBe('pending')->queued_at->toBeNull();

    $this->travel(10)->seconds();
    dispatch(queuedJob($draft, $held));
    workDatabaseQueue();

    expect(pivotRow($draft, $held)->delivery_status)->toBe('sent');
})->with([
    'per minute' => [['rate_limit_per_minute' => 2], 60],
    'per hour' => [['rate_limit_per_hour' => 2], 3600],
    'per day' => [['rate_limit_per_day' => 2], 86400],
    'per month' => [['rate_limit_per_month' => 2], 30 * 86400],
    'custom' => [['custom_rate_limit_amount' => 2, 'custom_rate_limit_seconds' => 10], 10],
    'the strictest of several' => [['rate_limit_per_minute' => 10, 'rate_limit_per_hour' => 2], 3600],
]);

it('leaves its row for QueueSends to queue again when the limit is full', function () {
    config(['email-composer.queue.rate_limit_per_minute' => 1]);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->once();
    $draft = sendableDraft();
    [, $held] = queueSends($draft, 2)->all();

    workDatabaseQueue();

    expect(pivotRow($draft, $held))
        ->delivery_status->toBe('pending')
        ->queued_at->toBeNull()
        ->attempts->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('lets a send that sends nothing through without counting it', function () {
    config(['email-composer.queue.rate_limit_per_minute' => 1]);
    $this->mock(EmailSenderInterface::class)->shouldReceive('send')->once();
    $draft = sendableDraft();
    $unsubscribed = attachedRecipient($draft, ['unsubscribed_at' => now()]);
    config()->set('queue.default', 'database');
    dispatch(queuedJob($draft, $unsubscribed));
    [$recipient] = queueSends($draft, 1)->all();

    workDatabaseQueue();

    expect(pivotRow($draft, $unsubscribed)->delivery_status)->toBe('skipped')
        ->and(pivotRow($draft, $recipient)->delivery_status)->toBe('sent');
});

it('gives up once the recipient has three failed attempts, across jobs', function () {
    $this->mock(EmailSenderInterface::class)->shouldNotReceive('send');
    $draft = sendableDraft();
    [$recipient] = queueSends($draft, 1)->all();
    $draft->recipients()->updateExistingPivot($recipient->getKey(), ['attempts' => 3]);

    workDatabaseQueue();

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('failed')
        ->error->toBe('Gave up after 3 failed attempts.')
        ->attempts->toBe(3);
});

it('marks the recipient failed after three exceptions', function () {
    config(['email-composer.queue.rate_limit_per_minute' => 10]);
    $this->mock(EmailSenderInterface::class)
        ->shouldReceive('send')
        ->times(3)
        ->andThrow(new RuntimeException('SMTP down'));
    $draft = sendableDraft();
    [$recipient] = queueSends($draft, 1)->all();

    workDatabaseQueue();
    $this->travel(31)->seconds();
    workDatabaseQueue();

    expect(pivotRow($draft, $recipient)->delivery_status)->toBe('pending');

    $this->travel(31)->seconds();
    workDatabaseQueue();

    expect(pivotRow($draft, $recipient))
        ->delivery_status->toBe('failed')
        ->error->toBe('SMTP down')
        ->attempts->toBe(3);
    expect(DB::table('jobs')->count())->toBe(0);
});
