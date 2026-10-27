<?php

use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\ListEmailDrafts;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Gate;

use function Pest\Livewire\livewire;

beforeEach(fn () => Bus::fake());

it('disables send for an approved draft with no recipients', function () {
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->assertActionDisabled('send');
});

it('shows a lost send claim as an error notification', function () {
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);
    $draft->recipients()->attach(Recipient::factory()->create());

    $this->partialMock(DraftDispatcher::class)
        ->shouldReceive('dispatch')
        ->andThrow(new DomainException("Cannot send draft #{$draft->id}: it is already sending or sent."));

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('send')
        ->assertNotified('Draft not sent');

    Bus::assertNothingBatched();
});

it('shows approving a draft with no template as an error notification', function () {
    $draft = EmailDraft::factory()->underReview()->create([
        'email_template_id' => null,
        'template_key' => null,
    ]);

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('approve')
        ->assertNotified('Draft not approved');

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::UnderReview);
});

it('shows returning a sent draft as an error notification', function () {
    Gate::before(fn () => true);

    $draft = EmailDraft::factory()->sent()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getRouteKey()])
        ->callAction('returnToDraft')
        ->assertNotified(__('email-composer::drafts.notifications.not_returned'));

    expect($draft->fresh()->status)->toBe(EmailDraftStatus::Sent);
});

it('keeps the row link to a draft whose send is disabled', function () {
    $draft = EmailDraft::factory()->approved()->create(['audience' => EmailAudience::Selected]);

    expect(livewire(ListEmailDrafts::class)->instance()->getTable()->getRecordUrl($draft))
        ->toBe(EditEmailDraft::getUrl(['record' => $draft]));
});
