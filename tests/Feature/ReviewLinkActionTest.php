<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Models\EmailDraft;

use function Pest\Livewire\livewire;

afterEach(fn () => EmailComposer::flushResolvers());

it('hides the review link action on a draft and shows it on one under review', function () {
    $draft = EmailDraft::factory()->create();
    $underReview = EmailDraft::factory()->underReview()->create();

    livewire(ListEmailDrafts::class)
        ->assertTableActionHidden('reviewLink', $draft)
        ->assertTableActionVisible('reviewLink', $underReview);

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertActionHidden('reviewLink');

    livewire(EditEmailDraft::class, ['record' => $underReview->getKey()])
        ->assertActionVisible('reviewLink');
});

it('shows a review link that opens the review page', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    $url = livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->mountAction('reviewLink')
        ->instance()
        ->mountedActions[0]['data']['url'];

    $this->get($url)->assertOk();
});
