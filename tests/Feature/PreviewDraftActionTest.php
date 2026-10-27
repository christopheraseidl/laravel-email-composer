<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Models\EmailDraft;

use function Pest\Livewire\livewire;

afterEach(fn () => EmailComposer::flushResolvers());

it('shows the preview action only on drafts the user can view', function () {
    $hidden = EmailDraft::factory()->create();
    $shown = EmailDraft::factory()->create();

    EmailComposer::resolveAbilityUsing(
        fn ($user, $ability, $model) => $ability === 'view-draft' && ! $model?->is($hidden)
    );

    livewire(EditEmailDraft::class, ['record' => $hidden->getRouteKey()])
        ->assertForbidden();

    livewire(EditEmailDraft::class, ['record' => $shown->getRouteKey()])
        ->assertActionVisible('preview');
});

it('previews in the default locale and switches locale', function () {
    $draft = EmailDraft::factory()->create(['subject' => ['en' => 'Hello there', 'es' => 'Hola amigos']]);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->mountAction('preview')
        ->assertSet('mountedActions.0.data.locale', 'en')
        ->assertMountedActionModalSee('Hello there')
        ->set('mountedActions.0.data.locale', 'es')
        ->assertMountedActionModalSee('Hola amigos')
        ->assertMountedActionModalDontSee('Hello there');
});

it('previews the saved draft in the edit page active locale', function () {
    $draft = EmailDraft::factory()->create(['subject' => ['en' => 'Hello there', 'es' => 'Hola amigos']]);

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->set('activeLocale', 'es')
        ->mountAction('preview')
        ->assertSet('mountedActions.0.data.locale', 'es')
        ->assertMountedActionModalSee([__('email-composer::drafts.helpers.preview'), 'Hola amigos']);
});

it('renders the email escaped into a sandboxed iframe', function () {
    $draft = EmailDraft::factory()->create([
        'placeholders' => ['en' => ['greeting' => 'Hi', 'body' => 'Unique preview body', 'signature' => 'Us']],
    ]);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->mountAction('preview')
        ->assertMountedActionModalSeeHtml('sandbox=""')
        ->assertMountedActionModalSeeHtml('srcdoc="&lt;!doctype html&gt;')
        ->assertMountedActionModalSee('Unique preview body')
        ->assertMountedActionModalDontSeeHtml('<!doctype html>');
});

it('shows the error when the draft has no template', function () {
    $draft = EmailDraft::factory()->create([
        'email_template_id' => null,
        'template_key' => null,
    ]);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->mountAction('preview')
        ->assertMountedActionModalSee("Draft #{$draft->id} has no resolvable template.")
        ->assertMountedActionModalDontSeeHtml('<iframe');
});
