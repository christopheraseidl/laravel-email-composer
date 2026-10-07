<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Mail\MailableSender;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Unsubscribe\UnsubscribeLink;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake(config('email-composer.storage.disk'));
    config()->set('email-composer.locales', ['en', 'es']);
    config()->set('email-composer.default_locale', 'en');
});

function composedDraft(bool $published = true): EmailDraft
{
    $template = EmailTemplate::factory()->create([
        'body' => '<div>[[ greeting ]]</div>',
        'placeholders' => ['greeting'],
    ]);

    $draft = EmailDraft::factory()->underReview()->create([
        'email_template_id' => $template->id,
        'subject' => ['en' => 'Spring update', 'es' => 'Novedades de primavera'],
        'placeholders' => ['en' => ['greeting' => 'Hello there'], 'es' => ['greeting' => 'Hola a todos']],
    ]);

    if ($published) {
        $draft->approve(UserFactory::new()->create());
    }

    return $draft;
}

function composedDisk(): Filesystem
{
    return Storage::disk(config('email-composer.storage.disk'));
}

it('takes the subject from the draft in the given locale', function (string $locale, string $subject) {
    $mail = new ComposedEmail(composedDraft(), Recipient::factory()->create(), $locale);

    $mail->assertHasSubject($subject);
})->with([
    ['en', 'Spring update'],
    ['es', 'Novedades de primavera'],
]);

it('reads the body from the published file for the locale', function () {
    $draft = composedDraft();
    composedDisk()->put($draft->public_files['es'], '<html><body><p>Stored copy</p></body></html>');

    $mail = new ComposedEmail($draft, Recipient::factory()->create(), 'es');

    $mail->assertSeeInHtml('Stored copy')
        ->assertDontSeeInHtml('Hola a todos');
});

it('falls back to the default locale file when the locale has none', function () {
    $draft = composedDraft();

    $mail = new ComposedEmail($draft, Recipient::factory()->create(), 'fr');

    $mail->assertSeeInHtml('Hello there')
        ->assertDontSeeInHtml('Hola a todos');
});

it('renders on the fly when the draft was never published', function () {
    $draft = composedDraft(false);

    $mail = new ComposedEmail($draft, Recipient::factory()->create(), 'es');

    expect($draft->public_files)->toBeNull();
    $mail->assertSeeInHtml('Hola a todos');
});

it('renders on the fly when the published file is missing', function () {
    $draft = composedDraft();
    composedDisk()->delete($draft->public_files['en']);

    $mail = new ComposedEmail($draft, Recipient::factory()->create(), 'en');

    $mail->assertSeeInHtml('Hello there');
});

it('adds a footer with both of the recipient\'s links', function () {
    $draft = composedDraft();
    $recipient = Recipient::factory()->create();

    $html = (new ComposedEmail($draft, $recipient, 'en'))->render();

    expect($html)
        ->toContain(e($draft->viewInBrowserUrl('en')))
        ->toContain(e(UnsubscribeLink::for($recipient)))
        ->toContain('View this email in your browser')
        ->toContain('Unsubscribe');
});

it('puts the footer before </body> when the body has one', function () {
    $draft = composedDraft();
    $recipient = Recipient::factory()->create();
    composedDisk()->put($draft->public_files['en'], '<html><body><p>Stored copy</p></BODY></html>');

    $html = (new ComposedEmail($draft, $recipient, 'en'))->render();

    expect($html)
        ->toStartWith('<html><body><p>Stored copy</p>')
        ->toEndWith('</body></html>');
    expect(strpos($html, e(UnsubscribeLink::for($recipient))))->toBeLessThan(strpos($html, '</body>'));
});

it('translates the footer into the email locale', function () {
    $mail = new ComposedEmail(composedDraft(), Recipient::factory()->create(), 'es');

    $mail->assertSeeInHtml('Ver este correo en el navegador')
        ->assertSeeInHtml('Darse de baja')
        ->assertDontSeeInHtml('View this email in your browser');
});

it('appends the footer when the body has no </body>', function () {
    $draft = composedDraft();
    composedDisk()->put($draft->public_files['en'], '<p>Fragment</p>');

    $html = (new ComposedEmail($draft, Recipient::factory()->create(), 'en'))->render();

    expect($html)
        ->toStartWith('<p>Fragment</p>')
        ->toContain(e($draft->viewInBrowserUrl('en')));
});

it('binds the sender interface to the mailable sender', function () {
    expect(app(EmailSenderInterface::class))->toBeInstanceOf(MailableSender::class);
});

it('sends the composed email to the recipient', function () {
    Mail::fake();
    $draft = composedDraft();
    $recipient = Recipient::factory()->create();

    app(EmailSenderInterface::class)->send($draft, $recipient, 'es');

    Mail::assertSent(ComposedEmail::class, fn (ComposedEmail $mail) => $mail->hasTo($recipient->email)
        && $mail->draft->is($draft)
        && $mail->recipient->is($recipient)
        && $mail->locale === 'es');
});
