<?php

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

beforeEach(fn () => Storage::fake(config('email-composer.storage.disk')));

it('persists translatable subject and placeholders', function () {
    $subject = ['en' => 'Welcome', 'es' => 'Bienvenido'];
    $placeholders = [
        'en' => ['greeting' => 'Hello', 'body' => 'Glad you are here.'],
        'es' => ['greeting' => 'Hola', 'body' => 'Nos alegra tenerte aquí.'],
    ];

    $draft = draftWithTemplate(compact('subject', 'placeholders'))->fresh();

    expect($draft->getTranslations('subject'))->toBe($subject);
    expect($draft->getTranslations('placeholders'))->toBe($placeholders);
});

it('returns the subject for the requested locale', function () {
    $draft = draftWithTemplate(['subject' => ['en' => 'Welcome', 'es' => 'Bienvenido']]);

    expect($draft->subjectFor('en'))->toBe('Welcome');
    expect($draft->subjectFor('es'))->toBe('Bienvenido');
});

it('falls back to the default locale subject', function () {
    config()->set('email-composer.default_locale', 'es');

    $draft = draftWithTemplate(['subject' => ['es' => 'Bienvenido']]);

    expect($draft->subjectFor('en'))->toBe('Bienvenido');
});

it('returns the public file for the requested locale', function () {
    $draft = draftWithTemplate(['public_files' => ['en' => 'drafts/1/en.html', 'es' => 'drafts/1/es.html']]);

    expect($draft->publicFileFor('es'))->toBe('drafts/1/es.html');
});

it('falls back to the default locale public file', function () {
    config()->set('email-composer.default_locale', 'es');

    $draft = draftWithTemplate(['public_files' => ['es' => 'drafts/1/es.html']]);

    expect($draft->publicFileFor('en'))->toBe('drafts/1/es.html');
});

it('returns null when the draft has no public files', function () {
    $draft = draftWithTemplate(['public_files' => null]);

    expect($draft->publicFileFor('en'))->toBeNull();
});

it('reads the published HTML for the locale', function () {
    Storage::disk(config('email-composer.storage.disk'))->put('drafts/1/es.txt', '<p>Hola</p>');

    $draft = draftWithTemplate(['public_files' => ['en' => 'drafts/1/en.txt', 'es' => 'drafts/1/es.txt']]);

    expect($draft->publishedHtml('es'))->toBe('<p>Hola</p>');
});

it('reads the default locale published HTML for a locale without a file', function () {
    config()->set('email-composer.default_locale', 'es');
    Storage::disk(config('email-composer.storage.disk'))->put('drafts/1/es.txt', '<p>Hola</p>');

    $draft = draftWithTemplate(['public_files' => ['es' => 'drafts/1/es.txt']]);

    expect($draft->publishedHtml('en'))->toBe('<p>Hola</p>');
});

it('returns null published HTML when the draft is unpublished or the file is missing', function (?array $publicFiles) {
    $draft = draftWithTemplate(['public_files' => $publicFiles]);

    expect($draft->publishedHtml('en'))->toBeNull();
})->with([
    'unpublished' => [null],
    'missing file' => [['en' => 'drafts/1/en.txt']],
]);

it('resolves placeholder values for the requested locale', function () {
    $draft = draftWithTemplate(['placeholders' => [
        'en' => ['greeting' => 'Hello', 'body' => 'English body.'],
        'es' => ['greeting' => 'Hola', 'body' => 'Cuerpo español.'],
    ]]);

    expect($draft->placeholderValues('es'))->toBe([
        'greeting' => 'Hola',
        'body' => 'Cuerpo español.',
    ]);
});

it('falls back to the default locale for a placeholder missing in the requested locale', function () {
    $draft = draftWithTemplate(['placeholders' => [
        'en' => ['greeting' => 'Hello', 'body' => 'English body.'],
        'es' => ['greeting' => 'Hola'],
    ]]);

    expect($draft->placeholderValues('es'))->toBe([
        'greeting' => 'Hola',
        'body' => 'English body.',
    ]);
});

it('falls back to the first non-empty locale when the default locale is missing it too', function () {
    $draft = draftWithTemplate(['placeholders' => [
        'en' => ['greeting' => 'Hello'],
        'es' => ['greeting' => 'Hola', 'body' => 'Cuerpo español.'],
    ]]);

    expect($draft->placeholderValues('en'))->toBe([
        'greeting' => 'Hello',
        'body' => 'Cuerpo español.',
    ]);
});

it('resolves a placeholder present in no locale to an empty string', function () {
    $draft = draftWithTemplate(
        ['placeholders' => ['en' => ['greeting' => 'Hello', 'body' => 'English body.']]],
        ['greeting', 'body', 'signature'],
    );

    expect($draft->placeholderValues('en'))->toBe([
        'greeting' => 'Hello',
        'body' => 'English body.',
        'signature' => '',
    ]);
});

it('ignores stored values the template does not declare', function () {
    $draft = draftWithTemplate(['placeholders' => [
        'en' => ['greeting' => 'Hello', 'body' => 'English body.', 'footer' => 'Unused.'],
    ]]);

    expect($draft->placeholderValues('en'))->not->toHaveKey('footer');
});

it('renders interpolated, inlined, and sanitized html', function () {
    $draft = draftWithTemplate(['placeholders' => [
        'en' => [
            'greeting' => '<h1>Greetings</h1><script>alert(1)</script>',
            'body' => '<p>Hello world!</p>',
        ],
    ]]);

    expect($draft->renderFor('en'))
        ->not->toContain('[[')
        ->toContain('Greetings')
        ->toContain('Hello world!')
        // The inliner writes the theme css onto the elements it matches.
        ->toContain('style="')
        ->not->toContain('<script')
        ->not->toContain('alert(1)');
});

it('renders a file template resolved by template key', function () {
    $draft = EmailDraft::factory()->create([
        'email_template_id' => null,
        'template_key' => 'default',
        'placeholders' => ['en' => ['body' => 'From a file template.']],
    ]);

    expect($draft->renderFor('en'))->toContain('From a file template.');
});

it('throws when no template can be resolved', function () {
    $draft = EmailDraft::factory()->create([
        'email_template_id' => null,
        'template_key' => null,
    ]);

    expect(fn () => $draft->renderFor('en'))->toThrow(DomainException::class);
});

it('reports declared placeholders that are empty in a locale', function () {
    $draft = draftWithTemplate(
        ['placeholders' => ['en' => ['greeting' => 'Hello']]],
        ['greeting', 'body', 'signature'],
    );

    expect($draft->missingPlaceholders('en'))->toBe(['body', 'signature']);
});

it('reports no missing placeholders when every value resolves', function () {
    $draft = draftWithTemplate(['placeholders' => [
        'en' => ['greeting' => 'Hello', 'body' => 'English body.'],
    ]]);

    expect($draft->missingPlaceholders('en'))->toBe([]);
});

it('scopes drafts to their author', function () {
    $user = UserFactory::new()->create();
    $mine = EmailDraft::factory()->count(2)->create(['author_id' => $user->getKey()]);
    EmailDraft::factory()->create();

    expect(EmailDraft::query()->ownedBy($user->getKey())->pluck('id')->all())
        ->toBe($mine->pluck('id')->all());
});

it('scopes drafts to a status', function () {
    $underReview = EmailDraft::factory()->underReview()->create();
    EmailDraft::factory()->create();
    EmailDraft::factory()->sent()->create();

    expect(EmailDraft::query()->inStatus(EmailDraftStatus::UnderReview)->pluck('id')->all())
        ->toBe([$underReview->id]);
});

it('keeps the draft and nulls author_id when the author is deleted', function () {
    $user = UserFactory::new()->create();
    $draft = EmailDraft::factory()->create(['author_id' => $user->id]);

    $user->delete();

    expect($draft->fresh())->not->toBeNull()->author_id->toBeNull();
});

it('titles a draft with the subject for the current locale', function () {
    app()->setLocale('es');
    $draft = draftWithTemplate(['subject' => ['en' => 'Welcome', 'es' => 'Bienvenido']]);

    expect($draft->localized_subject)->toBe('Bienvenido');
});

it('titles a draft with the default locale subject when the current locale has none', function () {
    app()->setLocale('es');
    $draft = draftWithTemplate(['subject' => ['en' => 'Welcome']]);

    expect($draft->localized_subject)->toBe('Welcome');
});

it('titles a draft with a placeholder when no subject resolves', function () {
    $draft = draftWithTemplate(['subject' => ['en' => '']]);

    expect($draft->localized_subject)->toBe('Untitled Draft');
});
