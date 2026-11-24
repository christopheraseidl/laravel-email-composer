<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\EditEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers\FeedbackRelationManager;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Str;

use function Pest\Livewire\livewire;

afterEach(fn () => EmailComposer::flushResolvers());

it('shows the add feedback action only under review', function () {
    $draft = EmailDraft::factory()->create();
    $underReview = EmailDraft::factory()->underReview()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertActionHidden('addFeedback');

    livewire(EditEmailDraft::class, ['record' => $underReview->getKey()])
        ->assertActionVisible('addFeedback');
});

it('hides the add feedback action without comment-on-draft', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability !== 'comment-on-draft');
    $draft = EmailDraft::factory()->underReview()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->assertActionHidden('addFeedback');
});

it('saves the comment as the signed-in user', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->callAction('addFeedback', ['comment' => 'Shorten the greeting.'])
        ->assertHasNoFormErrors()
        ->assertDispatched(FeedbackRelationManager::REFRESH_EVENT)
        ->assertNotified();

    $feedback = $draft->feedback()->sole();

    expect($feedback)
        ->comment->toBe('Shorten the greeting.')
        ->user_id->toBe(auth()->id())
        ->reviewer_name->toBeNull()
        ->reviewer_email->toBeNull();
});

it('refreshes the feedback table when feedback is added', function () {
    $draft = EmailDraft::factory()->underReview()->create();
    $table = livewire(FeedbackRelationManager::class, [
        'ownerRecord' => $draft,
        'pageClass' => EditEmailDraft::class,
    ])->assertCountTableRecords(0);

    $draft->feedback()->create(['user_id' => auth()->id(), 'comment' => 'Shorten the greeting.']);

    $table->dispatch(FeedbackRelationManager::REFRESH_EVENT)
        ->assertCountTableRecords(1);
});

it('requires a comment', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    livewire(EditEmailDraft::class, ['record' => $draft->getKey()])
        ->callAction('addFeedback', ['comment' => ''])
        ->assertHasFormErrors(['comment' => 'required']);

    expect($draft->feedback()->exists())->toBeFalse();
});

it('shows the full comment in the view feedback modal', function () {
    $draft = EmailDraft::factory()->underReview()->create();
    $comment = str_repeat('Long feedback. ', 30)."\nSecond line & more.";
    $feedback = $draft->feedback()->create(['user_id' => auth()->id(), 'comment' => $comment]);

    livewire(FeedbackRelationManager::class, [
        'ownerRecord' => $draft,
        'pageClass' => EditEmailDraft::class,
    ])
        ->assertTableColumnFormattedStateSet('comment', Str::limit($comment, 150), $feedback)
        ->mountTableAction('viewFeedback', $feedback)
        ->assertMountedActionModalSeeHtml(nl2br(e($comment)));
});
