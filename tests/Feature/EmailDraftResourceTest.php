<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Factories\UserFactory;

use function Pest\Livewire\livewire;

// Every test here needs a Filament panel, which lands in feat/language-switch.
// They are un-skipped in Branch 10.
pest()->use(RefreshDatabase::class);

afterEach(fn () => EmailComposer::flushResolvers());

it('lists drafts', function () {
    EmailDraft::factory()->count(5)->create();

    livewire(ListEmailDrafts::class)
        ->assertCountTableRecords(5);
})->skip('panel registered in feat/language-switch');

it('filters drafts by status', function () {
    EmailDraft::factory()->count(3)->create();
    $underReview = EmailDraft::factory()->underReview()->create();

    livewire(ListEmailDrafts::class)
        ->filterTable('status', EmailDraftStatus::UnderReview->value)
        ->assertCanSeeTableRecords([$underReview])
        ->assertCountTableRecords(1);
})->skip('panel registered in feat/language-switch');

it('renders the status column with its label', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    livewire(ListEmailDrafts::class)
        ->assertTableColumnFormattedStateSet('status', EmailDraftStatus::UnderReview->label(), $draft);
})->skip('panel registered in feat/language-switch');

it('shows the submit action only where the policy allows it', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability === 'submit-draft');

    $author = UserFactory::new()->create();
    $this->actingAs($author);

    $draft = EmailDraft::factory()->create(['author_id' => $author->getKey()]);
    $sent = EmailDraft::factory()->sent()->create(['author_id' => $author->getKey()]);

    livewire(ListEmailDrafts::class)
        ->assertTableActionVisible('submit', $draft)
        ->assertTableActionHidden('submit', $sent);
})->skip('panel registered in feat/language-switch');

it('renders the create form with no status in state', function () {
    livewire(CreateEmailDraft::class)
        ->assertSuccessful();
})->skip('panel registered in feat/language-switch');

it('disables the edit form for a draft that has left draft status', function () {
    $draft = EmailDraft::factory()->approved()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertSuccessful()
        ->assertFormFieldIsDisabled('subject');
})->skip('panel registered in feat/language-switch');
