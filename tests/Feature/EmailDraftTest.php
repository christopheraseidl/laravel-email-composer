<?php

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

// approve() hands the draft to DraftPublisher, which will write rendered HTML
// to the configured disk once it is implemented.
beforeEach(fn () => Storage::fake(config('email-composer.storage.disk')));

/**
 * Create a draft backed by a template that declares the given placeholders.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<int, string>  $placeholders
 */
function draftWithTemplate(array $attributes = [], array $placeholders = ['greeting', 'body']): EmailDraft
{
    $template = EmailTemplate::factory()->create([
        'key' => 'greeting-and-body',
        'body' => '<div>[[ greeting ]]</div><div>[[ body ]]</div>',
        'placeholders' => $placeholders,
    ]);

    return EmailDraft::factory()->create([
        'email_template_id' => $template->id,
        ...$attributes,
    ]);
}

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
    // A locale that is neither present nor the application fallback, so the
    // config value is the only thing that can resolve it.
    config()->set('email-composer.default_locale', 'es');

    $draft = draftWithTemplate(['subject' => ['es' => 'Bienvenido']]);

    expect($draft->subjectFor('fr'))->toBe('Bienvenido');
});

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
        'es' => ['greeting' => 'Hola'],
        'fr' => ['body' => 'Corps français.'],
    ]]);

    expect($draft->placeholderValues('es'))->toBe([
        'greeting' => 'Hola',
        'body' => 'Corps français.',
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

it('submits a draft for review', function () {
    $draft = draftWithTemplate();

    $draft->submit();

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::UnderReview)
        ->submitted_at->not->toBeNull();
});

it('approves a draft under review', function () {
    $approver = UserFactory::new()->create();
    $draft = EmailDraft::factory()->underReview()->create();

    $draft->approve($approver);

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Approved)
        ->approved_at->not->toBeNull()
        ->approved_by->toBe($approver->getKey());
});

it('sends an approved draft', function () {
    $draft = EmailDraft::factory()->approved()->create();

    $draft->send();

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Sent)
        ->sent_at->not->toBeNull();
});

it('returns a draft under review to draft', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    $draft->returnToDraft();

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Draft)
        ->submitted_at->toBeNull();
});

it('rejects an invalid transition', function (string $state, string $action) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();

    $call = $action === 'approve'
        ? fn () => $draft->approve(UserFactory::new()->create())
        : fn () => $draft->{$action}();

    expect($call)->toThrow(DomainException::class);
})->with([
    'approve while in draft' => ['draft', 'approve'],
    'send while in draft' => ['draft', 'send'],
    'return a draft to draft' => ['draft', 'returnToDraft'],
    'submit while under review' => ['underReview', 'submit'],
    'send while under review' => ['underReview', 'send'],
    'submit while approved' => ['approved', 'submit'],
    'return an approved draft to draft' => ['approved', 'returnToDraft'],
    'send an already sent draft' => ['sent', 'send'],
    'submit a sent draft' => ['sent', 'submit'],
]);

it('scopes drafts to their author', function () {
    $user = UserFactory::new()->create();
    $mine = EmailDraft::factory()->count(2)->create(['author_id' => $user->getKey()]);
    EmailDraft::factory()->create();

    expect(EmailDraft::ownedBy($user->getKey())->pluck('id')->all())
        ->toBe($mine->pluck('id')->all());
});

it('scopes drafts to a status', function () {
    $underReview = EmailDraft::factory()->underReview()->create();
    EmailDraft::factory()->create();
    EmailDraft::factory()->sent()->create();

    expect(EmailDraft::inStatus(EmailDraftStatus::UnderReview)->pluck('id')->all())
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
    expect($draft->title)->toBe('Bienvenido');
});

it('titles a draft with the default locale subject when the current locale has none', function () {
    app()->setLocale('fr');
    $draft = draftWithTemplate(['subject' => ['en' => 'Welcome']]);

    expect($draft->title)->toBe('Welcome');
});

it('titles a draft with a placeholder when no subject resolves', function () {
    $draft = draftWithTemplate(['subject' => ['en' => '']]);

    expect($draft->title)->toBe('Untitled Draft');
});
