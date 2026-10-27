<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use Orchestra\Testbench\Factories\UserFactory;

afterEach(fn () => EmailComposer::flushResolvers());

/**
 * Grant the given abilities to everyone.
 *
 * @param  array<int, string>  $abilities
 */
function grantAbilities(array $abilities): void
{
    EmailComposer::resolveAbilityUsing(
        fn ($user, $ability, $model) => in_array($ability, $abilities, true)
    );
}

/**
 * Grant the given abilities, but only over drafts the user authored.
 *
 * The policy holds no ownership rules of its own. It hands the draft to the
 * host application's resolver, which is where ownership is decided.
 *
 * @param  array<int, string>  $abilities
 */
function grantAbilitiesToAuthor(array $abilities): void
{
    EmailComposer::resolveAbilityUsing(
        fn ($user, $ability, $model) => in_array($ability, $abilities, true)
            && (! $model instanceof EmailDraft || $model->author_id === $user->getKey())
    );
}

/** Build a draft in the given factory state. */
function draftInState(string $state, array $attributes = []): EmailDraft
{
    return $state === 'draft'
        ? EmailDraft::factory()->create($attributes)
        : EmailDraft::factory()->{$state}()->create($attributes);
}

it('lets the author update a draft and denies everyone else', function () {
    grantAbilitiesToAuthor(['update-draft']);

    $author = UserFactory::new()->create();
    $other = UserFactory::new()->create();
    $draft = EmailDraft::factory()->create(['author_id' => $author->getKey()]);

    expect($author->can('update', $draft))->toBeTrue();
    expect($other->can('update', $draft))->toBeFalse();
});

it('lets the author delete a draft and denies everyone else', function () {
    grantAbilitiesToAuthor(['delete-draft']);

    $author = UserFactory::new()->create();
    $other = UserFactory::new()->create();
    $draft = EmailDraft::factory()->create(['author_id' => $author->getKey()]);

    expect($author->can('delete', $draft))->toBeTrue();
    expect($other->can('delete', $draft))->toBeFalse();
});

it('denies updating a draft that has left draft status', function (string $state) {
    grantAbilitiesToAuthor(['update-draft']);

    $author = UserFactory::new()->create();
    $draft = draftInState($state, ['author_id' => $author->getKey()]);

    expect($author->can('update', $draft))->toBeFalse();
})->with(['underReview', 'approved', 'sending', 'sent']);

it('denies deleting a sending or sent draft but allows deleting an approved one', function () {
    grantAbilitiesToAuthor(['delete-draft']);

    $author = UserFactory::new()->create();
    $sending = draftInState('sending', ['author_id' => $author->getKey()]);
    $sent = draftInState('sent', ['author_id' => $author->getKey()]);
    $approved = draftInState('approved', ['author_id' => $author->getKey()]);

    expect($author->can('delete', $sending))->toBeFalse();
    expect($author->can('delete', $sent))->toBeFalse();
    expect($author->can('delete', $approved))->toBeTrue();
});

it('denies force deleting a sending or sent draft but allows it otherwise', function () {
    grantAbilitiesToAuthor(['force-delete-draft']);

    $author = UserFactory::new()->create();
    $sending = draftInState('sending', ['author_id' => $author->getKey()]);
    $sent = draftInState('sent', ['author_id' => $author->getKey()]);
    $draft = draftInState('draft', ['author_id' => $author->getKey()]);

    expect($author->can('forceDelete', $sending))->toBeFalse();
    expect($author->can('forceDelete', $sent))->toBeFalse();
    expect($author->can('forceDelete', $draft))->toBeTrue();
});

it('lets view-any-draft see a draft the user does not own', function () {
    grantAbilitiesToAuthor(['view-any-draft']);

    $draft = EmailDraft::factory()->create();
    $other = UserFactory::new()->create();

    // view-any-draft is checked without a model, so the author check in the
    // resolver never applies to it.
    expect($other->can('view', $draft))->toBeTrue();
});

it('falls back to a per-draft check without view-any-draft', function () {
    grantAbilitiesToAuthor(['view-draft']);

    $author = UserFactory::new()->create();
    $other = UserFactory::new()->create();
    $draft = EmailDraft::factory()->create(['author_id' => $author->getKey()]);

    expect($author->can('view', $draft))->toBeTrue();
    expect($other->can('view', $draft))->toBeFalse();
});

it('allows viewAny from either draft-viewing ability', function (string $ability) {
    grantAbilities([$ability]);

    expect(UserFactory::new()->create()->can('viewAny', EmailDraft::class))->toBeTrue();
})->with(['view-any-draft', 'view-draft']);

it('gates a transition action on the draft status', function (string $action, string $ability, string $allowedState) {
    grantAbilities([$ability]);

    $user = UserFactory::new()->create();

    foreach (['draft', 'underReview', 'approved', 'sending', 'sent'] as $state) {
        expect($user->can($action, draftInState($state)))->toBe($state === $allowedState);
    }
})->with([
    'submit' => ['submit', 'submit-draft', 'draft'],
    'approve' => ['approve', 'approve-draft', 'underReview'],
    'share' => ['share', 'share-draft', 'underReview'],
    'send' => ['send', 'send-draft', 'approved'],
]);

it('allows returning a draft to draft in any status but draft, sending or sent', function (string $state) {
    grantAbilities(['approve-draft']);

    expect(UserFactory::new()->create()->can('returnToDraft', draftInState($state)))
        ->toBe(! in_array($state, ['draft', 'sending', 'sent'], true));
})->with(['draft', 'underReview', 'approved', 'sending', 'sent']);

it('denies returning a draft to draft without the approve-draft ability', function () {
    grantAbilities(['view-any-draft', 'update-draft']);

    expect(UserFactory::new()->create()->can('returnToDraft', draftInState('underReview')))->toBeFalse();
});

it('allows sending a test of a draft in any status but sent', function (string $state) {
    grantAbilities(['test-draft']);

    expect(UserFactory::new()->create()->can('sendTest', draftInState($state)))->toBe($state !== 'sent');
})->with(['draft', 'underReview', 'approved', 'sending', 'sent']);

it('denies sending a test without the test-draft ability', function () {
    grantAbilities(['view-any-draft', 'send-draft']);

    expect(UserFactory::new()->create()->can('sendTest', draftInState('draft')))->toBeFalse();
});

it('forwards the draft to the resolver for per-model abilities', function () {
    $captured = [];
    EmailComposer::resolveAbilityUsing(function ($user, $ability, $model) use (&$captured) {
        $captured[$ability] = $model;

        return true;
    });

    $user = UserFactory::new()->create();
    $draft = EmailDraft::factory()->create();

    $user->can('update', $draft);
    $user->can('delete', $draft);
    $user->can('submit', $draft);
    $user->can('approve', $draft);
    $user->can('returnToDraft', $draft);
    $user->can('share', $draft);
    $user->can('send', $draft);
    $user->can('sendTest', $draft);
    $user->can('restore', $draft);
    $user->can('forceDelete', $draft);

    expect($captured['update-draft'])->toBe($draft);
    expect($captured['delete-draft'])->toBe($draft);
    expect($captured['submit-draft'])->toBe($draft);
    expect($captured['approve-draft'])->toBe($draft);
    expect($captured['share-draft'])->toBe($draft);
    expect($captured['send-draft'])->toBe($draft);
    expect($captured['test-draft'])->toBe($draft);
    expect($captured['restore-draft'])->toBe($draft);
    expect($captured['force-delete-draft'])->toBe($draft);
});
