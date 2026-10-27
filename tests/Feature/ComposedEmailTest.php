<?php

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Mail\MailableSender;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Unsubscribe\UnsubscribeLink;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;
use Symfony\Component\Mime\Email;

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

function composedText(ComposedEmail $mail): string
{
    return (fn () => $this->renderForAssertions()[1])->call($mail);
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

it('puts the footer in the layout footer cell', function () {
    $draft = composedDraft();
    $recipient = Recipient::factory()->create();

    $html = (new ComposedEmail($draft, $recipient, 'en'))->render();

    expect($html)
        ->not->toContain(ComposedEmail::FOOTER_MARKER)
        ->toMatch('/<td class="email-footer"[^>]*><div class="email-footer"/')
        ->and(substr_count($html, e(UnsubscribeLink::for($recipient))))->toBe(1);
});

it('prefers the footer marker over </body>', function () {
    $draft = composedDraft();
    composedDisk()->put(
        $draft->public_files['en'],
        '<html><body><td>'.ComposedEmail::FOOTER_MARKER.'</td><p>After</p></body></html>',
    );

    $html = (new ComposedEmail($draft, Recipient::factory()->create(), 'en'))->render();

    expect($html)
        ->toStartWith('<html><body><td><div class="email-footer"')
        ->toEndWith('</td><p>After</p></body></html>')
        ->and(substr_count($html, 'class="email-footer"'))->toBe(1);
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

it('adds one-click list-unsubscribe headers pointing at the footer link', function () {
    $mail = new ComposedEmail(composedDraft(), Recipient::factory()->create(), 'en');

    $headers = $mail->headers()->text;

    expect($headers)
        ->toHaveKey('List-Unsubscribe')
        ->toHaveKey('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click')
        ->and($headers['List-Unsubscribe'])->toMatch('/^<.+>$/');

    $url = substr($headers['List-Unsubscribe'], 1, -1);

    expect($mail->render())->toContain(e($url));
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

it('marks only a recipient built for a test send as a test', function () {
    expect(Recipient::forTestSend('tester@example.com', 'es'))
        ->isTest()->toBeTrue()
        ->exists->toBeFalse()
        ->name->toBe('tester@example.com')
        ->email->toBe('tester@example.com')
        ->locale->toBe('es')
        ->and(Recipient::factory()->create()->isTest())->toBeFalse()
        ->and((new Recipient(['email' => 'unsaved@example.com']))->isTest())->toBeFalse();
});

it('prefixes the subject of a test email', function () {
    $mail = new ComposedEmail(composedDraft(), Recipient::forTestSend('tester@example.com', 'es'), 'es');

    $mail->assertHasSubject('[Test] Novedades de primavera');
});

it('gives a test email a placeholder unsubscribe link and no list-unsubscribe headers', function () {
    $mail = new ComposedEmail(composedDraft(), Recipient::forTestSend('tester@example.com', 'en'), 'en');

    expect($mail->headers()->text)->toBe([])
        ->and($mail->render())
        ->toContain('href="#"')
        ->toContain('tester@example.com')
        ->not->toContain('unsubscribe/');
});

it('adds a plain-text part built from the body without the footer', function () {
    $draft = composedDraft();
    $recipient = Recipient::factory()->create();
    composedDisk()->put(
        $draft->public_files['en'],
        '<html><head><style>p { color: red; }</style></head><body>'
            .'<h1>Spring</h1><p>Read <a href="https://example.com/news">the news</a> &amp; more.</p>'
            .ComposedEmail::FOOTER_MARKER.'</body></html>',
    );

    $text = composedText(new ComposedEmail($draft, $recipient, 'en'));

    expect(trim($text))->toBe(
        "Spring\n\nRead the news (https://example.com/news) & more.\n\n"
            .'View this email in your browser: '.$draft->viewInBrowserUrl('en')."\n"
            .'Unsubscribe: '.UnsubscribeLink::for($recipient)
    )
        ->not->toMatch('/<[a-z\/!]/i')
        ->not->toContain('color: red')
        ->not->toMatch('/&[a-z#0-9]+;/i');
});

it('sends both a text/plain and a text/html part', function () {
    config()->set('mail.default', 'array');
    $draft = composedDraft();
    $recipient = Recipient::factory()->create();

    /** @var Email $email */
    $email = Mail::to($recipient->email)
        ->send(new ComposedEmail($draft, $recipient, 'en'))
        ->getOriginalMessage();
    $types = array_map(
        fn ($part) => $part->getMediaType().'/'.$part->getMediaSubtype(),
        $email->getBody()->getParts(),
    );

    expect($types)->toBe(['text/plain', 'text/html'])
        ->and($email->getTextBody())
        ->toContain('Hello there')
        ->toContain('Unsubscribe: '.UnsubscribeLink::for($recipient))
        ->and($email->getHtmlBody())
        ->toContain('Hello there')
        ->toContain('class="email-footer"');
});

it('translates the plain-text links into the email locale', function () {
    $mail = new ComposedEmail(composedDraft(), Recipient::factory()->create(), 'es');

    $mail->assertSeeInOrderInText(['Hola a todos', 'Ver este correo en el navegador: ', 'Darse de baja: '])
        ->assertDontSeeInText('Unsubscribe');
});

it('gives a test email a placeholder unsubscribe link in the plain-text part', function () {
    $mail = new ComposedEmail(composedDraft(), Recipient::forTestSend('tester@example.com', 'en'), 'en');

    expect(trim(composedText($mail)))->toEndWith("\nUnsubscribe: #");
});
