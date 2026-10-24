<?php

use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;

use function Pest\Livewire\livewire;

beforeEach(fn () => Bus::fake());

it('disables send for an approved draft with no recipients', function () {
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);

    livewire(ListEmailDrafts::class)
        ->assertActionDisabled(TestAction::make('send')->table($draft));
});

it('shows a lost send claim as an error notification', function () {
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);
    $draft->recipients()->attach(Recipient::factory()->create());

    $this->partialMock(DraftDispatcher::class)
        ->shouldReceive('dispatch')
        ->andThrow(new DomainException("Cannot send draft #{$draft->id}: it is already sending or sent."));

    livewire(ListEmailDrafts::class)
        ->callAction(TestAction::make('send')->table($draft))
        ->assertNotified('Draft not sent');

    Bus::assertNothingBatched();
});

it('shows approving a draft with no template as an error notification', function () {
    $draft = EmailDraft::factory()->underReview()->create([
        'email_template_id' => null,
        'template_key' => null,
    ]);

    livewire(ListEmailDrafts::class)
        ->callAction(TestAction::make('approve')->table($draft))
        ->assertNotified('Draft not approved');

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::UnderReview);
});
