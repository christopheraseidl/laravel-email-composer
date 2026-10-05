<?php

use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

it('persists feedback from an in-app user', function () {
    $user = UserFactory::new()->create();
    $draft = EmailDraft::factory()->underReview()->create();

    $feedback = DraftFeedback::factory()->for($draft, 'draft')->create([
        'user_id' => $user->getKey(),
        'comment' => 'Tighten the opening line.',
    ])->fresh();

    expect($feedback)
        ->comment->toBe('Tighten the opening line.')
        ->resolved->toBeFalse()
        ->reviewer_name->toBeNull()
        ->reviewer_email->toBeNull();
    expect($feedback->user->is($user))->toBeTrue();
    expect($feedback->draft->is($draft))->toBeTrue();
});

it('persists feedback from an external reviewer', function () {
    $feedback = DraftFeedback::factory()->external()->create([
        'reviewer_name' => 'Ada Reviewer',
        'reviewer_email' => 'ada@example.com',
    ])->fresh();

    expect($feedback)
        ->user_id->toBeNull()
        ->reviewer_name->toBe('Ada Reviewer')
        ->reviewer_email->toBe('ada@example.com');
    expect($feedback->user)->toBeNull();
});

it('casts resolved to a boolean', function () {
    $feedback = DraftFeedback::factory()->create();

    $feedback->update(['resolved' => 1]);

    expect($feedback->fresh()->resolved)->toBeTrue();
});

it('lists feedback on its draft', function () {
    $draft = EmailDraft::factory()->create();
    $mine = DraftFeedback::factory()->count(2)->for($draft, 'draft')->create();
    DraftFeedback::factory()->create();

    expect($draft->feedback->pluck('id')->all())->toBe($mine->pluck('id')->all());
});

it('keeps the comment and nulls user_id when the user is deleted', function () {
    $user = UserFactory::new()->create();
    $feedback = DraftFeedback::factory()->create(['user_id' => $user->getKey()]);

    $user->delete();

    expect($feedback->fresh())->not->toBeNull()
        ->user_id->toBeNull()
        ->comment->toBe($feedback->comment);
});

it('deletes feedback when its draft is force deleted', function () {
    $feedback = DraftFeedback::factory()->create();

    $feedback->draft->forceDelete();

    expect(DraftFeedback::find($feedback->id))->toBeNull();
});
