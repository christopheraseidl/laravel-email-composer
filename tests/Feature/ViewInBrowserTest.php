<?php

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

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

it('serves the stored HTML for each published locale', function (string $locale, string $greeting) {
    $draft = publishedDraft();

    $this->get($draft->viewInBrowserUrl($locale))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/html; charset=UTF-8')
        ->assertSee($greeting);
})->with([
    ['en', 'Hello there'],
    ['es', 'Hola a todos'],
]);

it('falls back to the default locale file when the requested locale has none', function () {
    $draft = publishedDraft();

    $this->get($draft->viewInBrowserUrl('de'))
        ->assertOk()
        ->assertSee('Hello there')
        ->assertDontSee('Hola a todos');
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
