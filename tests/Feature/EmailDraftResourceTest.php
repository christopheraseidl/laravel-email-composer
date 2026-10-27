<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\Factories\UserFactory;

use function Pest\Livewire\livewire;

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

it('shows the submit action only where the policy allows it', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => in_array($ability, ['view-any-draft', 'submit-draft'], true));

    $author = UserFactory::new()->create();
    $this->actingAs($author);

    $draft = EmailDraft::factory()->create(['author_id' => $author->getKey()]);
    $sent = EmailDraft::factory()->sent()->create(['author_id' => $author->getKey()]);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertActionVisible('submit');

    livewire(EditEmailDraft::class, ['record' => $sent->getRouteKey()])
        ->assertActionHidden('submit');
});

it('links each row to the edit page', function () {
    $draft = EmailDraft::factory()->create();

    expect(livewire(ListEmailDrafts::class)->instance()->getTable()->getRecordUrl($draft))
        ->toBe(EditEmailDraft::getUrl(['record' => $draft]));
});

it('links a row only when the user can view the draft', function () {
    $hidden = EmailDraft::factory()->create();
    $shown = EmailDraft::factory()->create();

    EmailComposer::resolveAbilityUsing(
        fn ($user, $ability, $model) => $ability === 'view-draft' && ! $model?->is($hidden)
    );

    $table = livewire(ListEmailDrafts::class)->instance()->getTable();

    expect($table->getRecordUrl($hidden))->toBeNull()
        ->and($table->getRecordUrl($shown))->toBe(EditEmailDraft::getUrl(['record' => $shown]));
});

it('shows return to draft on any draft that has left draft status but is not sending or sent', function (string $state, bool $visible) {
    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();

    $component = livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()]);

    $visible
        ? $component->assertActionVisible('returnToDraft')
        : $component->assertActionHidden('returnToDraft');
})->with([
    'draft' => ['draft', false],
    'under review' => ['underReview', true],
    'approved' => ['approved', true],
    'sending' => ['sending', false],
    'sent' => ['sent', false],
]);

it('hides return to draft without the approve-draft ability', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability !== 'approve-draft');

    $draft = EmailDraft::factory()->underReview()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertActionHidden('returnToDraft');
});

it('reopens the form after returning a draft to draft', function (string $state) {
    $draft = EmailDraft::factory()->{$state}()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertFormFieldDisabled('subject')
        ->callAction('returnToDraft')
        ->assertNoRedirect()
        ->assertFormFieldEnabled('subject')
        ->assertActionHidden('returnToDraft')
        ->assertActionVisible('submit');

    expect($draft->fresh())
        ->status->toBe(EmailDraftStatus::Draft)
        ->submitted_at->toBeNull();
})->with(['underReview', 'approved']);

it('locks the form after submitting a draft', function () {
    $draft = EmailDraft::factory()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertFormFieldEnabled('subject')
        ->callAction('submit')
        ->assertNoRedirect()
        ->assertFormFieldDisabled('subject')
        ->assertActionHidden('submit')
        ->assertActionVisible('returnToDraft');

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::UnderReview);
});

it('labels the draft actions in the panel language', function (string $state, string $action, string $label) {
    app()->setLocale('es');

    $draft = $state === 'draft'
        ? EmailDraft::factory()->create()
        : EmailDraft::factory()->{$state}()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertActionHasLabel($action, $label);
})->with([
    ['draft', 'preview', 'Vista previa'],
    ['draft', 'sendTest', 'Enviar prueba'],
    ['underReview', 'reviewLink', 'Enlace de revisión'],
    ['draft', 'submit', 'Enviar a revisión'],
    ['underReview', 'approve', 'Aprobar'],
    ['underReview', 'returnToDraft', 'Devolver a borrador'],
    ['approved', 'send', 'Enviar'],
]);

it('shows the send test notification in the panel language', function () {
    Mail::fake();
    app()->setLocale('es');

    $draft = EmailDraft::factory()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('sendTest', data: ['emails' => ['ada@example.com'], 'locale' => 'en'])
        ->assertNotified(Notification::make()
            ->success()
            ->title('Correo de prueba enviado')
            ->body('Enviado a ada@example.com.'));
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

it('saves each audience', function (EmailAudience $audience) {
    $list = RecipientList::factory()->create();
    $recipients = Recipient::factory()->count(2)->create();

    $component = livewire(CreateEmailDraft::class)
        ->assertSchemaStateSet(['audience' => EmailAudience::All->value])
        ->assertSchemaComponentHidden('recipient_list_id')
        ->assertSchemaComponentHidden('recipients')
        ->fillForm(['template' => 'file:default', 'subject' => 'Hello', 'audience' => $audience->value]);

    foreach (['recipient_list_id' => EmailAudience::List, 'recipients' => EmailAudience::Selected] as $field => $shownFor) {
        $audience === $shownFor
            ? $component->assertSchemaComponentVisible($field)
            : $component->assertSchemaComponentHidden($field);
    }

    match ($audience) {
        EmailAudience::All => null,
        EmailAudience::List => $component->fillForm(['recipient_list_id' => $list->id]),
        EmailAudience::Selected => $component->fillForm(['recipients' => $recipients->modelKeys()]),
    };

    $component
        ->call('create')
        ->assertHasNoFormErrors();

    $draft = EmailDraft::sole();

    expect($draft->audience)->toBe($audience)
        ->and($draft->recipient_list_id)->toBe($audience === EmailAudience::List ? $list->id : null)
        ->and($draft->recipients()->count())->toBe($audience === EmailAudience::Selected ? 2 : 0);
})->with(EmailAudience::cases());

it('requires a list when the audience is a list', function () {
    livewire(CreateEmailDraft::class)
        ->fillForm(['template' => 'file:default', 'subject' => 'Hello', 'audience' => EmailAudience::List->value])
        ->call('create')
        ->assertHasFormErrors(['recipient_list_id' => 'required']);
});

it('clears the list when another audience is picked', function () {
    $list = RecipientList::factory()->create();
    $draft = EmailDraft::factory()->create(['audience' => EmailAudience::List, 'recipient_list_id' => $list->id]);

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->fillForm(['audience' => EmailAudience::All->value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($draft->refresh())
        ->audience->toBe(EmailAudience::All)
        ->recipient_list_id->toBeNull();
});

it('selects the file template when editing a draft that uses one', function () {
    $draft = EmailDraft::factory()->create(['email_template_id' => null, 'template_key' => 'default']);

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertSchemaStateSet(['template' => 'file:default']);
});
