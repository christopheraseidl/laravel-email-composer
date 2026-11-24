<?php

use CSeidl\EmailComposer\Actions\Feedback\StoreReviewFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;

it('stores open feedback from an external reviewer on the draft', function () {
    $draft = EmailDraft::factory()->underReview()->create();

    $feedback = app(StoreReviewFeedback::class)($draft, [
        'reviewer_name' => 'Ada Reviewer',
        'reviewer_email' => 'ada@example.com',
        'comment' => 'Shorten the greeting.',
    ]);

    expect($feedback->fresh())
        ->email_draft_id->toBe($draft->id)
        ->user_id->toBeNull()
        ->reviewer_name->toBe('Ada Reviewer')
        ->reviewer_email->toBe('ada@example.com')
        ->comment->toBe('Shorten the greeting.')
        ->resolved->toBeFalse();
});
