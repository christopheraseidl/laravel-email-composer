<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\Recipient;
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

function runSendJob(EmailDraft $draft, Recipient $recipient): void
{
    app()->call([new SendEmailJob($draft, $recipient), 'handle']);
}

function pivotRow(EmailDraft $draft, Recipient $recipient): object
{
    return $draft->recipients()->findOrFail($recipient->getKey())->pivot;
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

it('records the failure and its error', function () {
    $draft = sendableDraft();
    $recipient = attachedRecipient($draft);

    (new SendEmailJob($draft, $recipient))->failed(new RuntimeException('SMTP down'));

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

it('is retried three times with a 30 second backoff', function () {
    $job = new SendEmailJob(sendableDraft(), Recipient::factory()->create());

    expect($job)->tries->toBe(3)->backoff->toBe(30);
});
