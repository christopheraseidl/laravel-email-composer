<?php

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

beforeEach(function () {
    Storage::fake(config('email-composer.storage.disk'));
    config()->set('email-composer.locales', ['en', 'es']);
    config()->set('email-composer.default_locale', 'en');
});

function publishedDraft(bool $published = true): EmailDraft
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

it('serves the stored file as HTML for each published locale', function (string $locale, string $greeting) {
    $draft = publishedDraft();

    $this->get($draft->viewInBrowserUrl($locale))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee($greeting);
})->with([
    ['en', 'Hello there'],
    ['es', 'Hola a todos'],
]);

it('sends hardening headers', function () {
    $draft = publishedDraft();

    $this->get($draft->viewInBrowserUrl('en'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex')
        ->assertHeader('Content-Security-Policy', "script-src 'none'");
});

it('falls back to the default locale file when the requested locale has none', function () {
    $draft = publishedDraft();

    $this->get($draft->viewInBrowserUrl('de'))
        ->assertOk()
        ->assertSee('Hello there')
        ->assertDontSee('Hola a todos');
});

it('renders the draft again when its stored file is gone', function () {
    $draft = publishedDraft();
    Storage::disk(config('email-composer.storage.disk'))->delete($draft->fresh()->publicFileFor('es'));

    $this->get($draft->viewInBrowserUrl('es'))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee('Hola a todos');
});

it('serves a file published before drafts were stored as .txt', function () {
    $draft = publishedDraft();
    $legacy = str_replace('.txt', '.html', $draft->fresh()->publicFileFor('en'));
    Storage::disk(config('email-composer.storage.disk'))->put($legacy, '<p>Legacy body</p>');
    $draft->forceFill(['public_files' => ['en' => $legacy]])->save();

    $this->get($draft->viewInBrowserUrl('en'))
        ->assertOk()
        ->assertSee('Legacy body');
});

it('refuses a tampered link', function () {
    $draft = publishedDraft();
    $link = $draft->viewInBrowserUrl('en').'x';

    $this->get($link)->assertForbidden();
});

it('returns 404 for an unpublished draft', function () {
    $draft = publishedDraft(false);

    $this->get($draft->viewInBrowserUrl('en'))->assertNotFound();
});
