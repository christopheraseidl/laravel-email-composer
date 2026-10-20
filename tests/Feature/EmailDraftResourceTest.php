<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Factories\UserFactory;

use function Pest\Livewire\livewire;

pest()->use(RefreshDatabase::class);

afterEach(fn () => EmailComposer::flushResolvers());

it('lists drafts', function () {
    EmailDraft::factory()->count(5)->create();

    livewire(ListEmailDrafts::class)
        ->assertCountTableRecords(5);
});

it('filters drafts by status', function () {
    EmailDraft::factory()->count(3)->create();
    $underReview = EmailDraft::factory()->underReview()->create();

    livewire(ListEmailDrafts::class)
        ->filterTable('status', EmailDraftStatus::UnderReview->value)
        ->assertCanSeeTableRecords([$underReview])
        ->assertCountTableRecords(1);
});

it('renders the status column with its label', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    livewire(ListEmailDrafts::class)
        ->assertTableColumnFormattedStateSet('status', EmailDraftStatus::UnderReview->label(), $draft);
});

it('shows the submit action only where the policy allows it', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => in_array($ability, ['view-any-draft', 'submit-draft'], true));

    $author = UserFactory::new()->create();
    $this->actingAs($author);

    $draft = EmailDraft::factory()->create(['author_id' => $author->getKey()]);
    $sent = EmailDraft::factory()->sent()->create(['author_id' => $author->getKey()]);

    livewire(ListEmailDrafts::class)
        ->assertTableActionVisible('submit', $draft)
        ->assertTableActionHidden('submit', $sent);
});

it('renders the create form with no status in state', function () {
    livewire(CreateEmailDraft::class)
        ->assertSuccessful();
});

it('disables the edit form for a draft that has left draft status', function () {
    $draft = EmailDraft::factory()->approved()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertSuccessful()
        ->assertFormFieldDisabled('subject');
});

it('refuses to save a draft that has left draft status', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->call('save')
        ->assertForbidden();
});

it('refuses the edit page to a user who cannot view the draft', function () {
    EmailComposer::flushResolvers();

    $draft = EmailDraft::factory()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertForbidden();
});

it('creates a draft in every locale filled through the locale switcher', function () {
    $template = EmailTemplate::factory()->create(['placeholders' => ['greeting']]);

    livewire(CreateEmailDraft::class)
        ->fillForm([
            'template' => "db:{$template->getKey()}",
            'subject' => 'Hello',
            'placeholders' => ['greeting' => 'Hi'],
        ])
        ->set('activeLocale', 'es')
        ->fillForm([
            'subject' => 'Hola',
            'placeholders' => ['greeting' => 'Buenas'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $draft = EmailDraft::sole();

    expect($draft->getTranslations('subject'))->toBe(['es' => 'Hola', 'en' => 'Hello'])
        ->and($draft->getTranslations('placeholders'))->toBe(['es' => ['greeting' => 'Buenas'], 'en' => ['greeting' => 'Hi']]);
});

it('creates a draft in the active locale only', function () {
    app()->setLocale('es');

    $template = EmailTemplate::factory()->create(['placeholders' => ['greeting']]);

    livewire(CreateEmailDraft::class)
        ->fillForm([
            'template' => "db:{$template->getKey()}",
            'subject' => 'Hola',
            'placeholders' => ['greeting' => 'Buenas'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $draft = EmailDraft::sole();

    expect($draft->getTranslations('subject'))->toBe(['es' => 'Hola'])
        ->and($draft->getTranslations('placeholders'))->toBe(['es' => ['greeting' => 'Buenas']]);
});

it('fills the edit form with the active locale', function () {
    $draft = EmailDraft::factory()->create(['subject' => ['en' => 'Hello', 'es' => 'Hola']]);

    app()->setLocale('es');

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertSchemaStateSet(['subject' => 'Hola']);
});

it('saves edits to the active locale and keeps the others', function () {
    $draft = EmailDraft::factory()->create(['subject' => ['en' => 'Hello', 'es' => 'Hola']]);

    app()->setLocale('es');

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->fillForm(['subject' => 'Buenas'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($draft->refresh()->getTranslations('subject'))->toBe(['en' => 'Hello', 'es' => 'Buenas']);
});

it('creates a draft from a file template', function () {
    livewire(CreateEmailDraft::class)
        ->fillForm(['template' => 'file:default'])
        ->assertSeeText('BODY')
        ->fillForm(['subject' => 'Hello', 'placeholders' => ['body' => 'Hi']])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(EmailDraft::sole())
        ->template_key->toBe('default')
        ->email_template_id->toBeNull();
});

it('creates a draft from a database template', function () {
    $template = EmailTemplate::factory()->create(['placeholders' => ['greeting']]);

    livewire(CreateEmailDraft::class)
        ->fillForm([
            'template' => "db:{$template->getKey()}",
            'subject' => 'Hello',
            'placeholders' => ['greeting' => 'Hi'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(EmailDraft::sole())
        ->email_template_id->toBe($template->getKey())
        ->template_key->toBeNull();
});

it('selects the file template when editing a draft that uses one', function () {
    $draft = EmailDraft::factory()->create(['email_template_id' => null, 'template_key' => 'default']);

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertSchemaStateSet(['template' => 'file:default']);
});
