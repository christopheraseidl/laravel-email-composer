<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\DraftFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

afterEach(fn () => EmailComposer::flushResolvers());

it('denies every action when no resolver is registered', function () {
    $user = UserFactory::new()->create();
    $feedback = DraftFeedback::factory()->create();

    expect($user->can('viewAny', DraftFeedback::class))->toBeFalse();
    expect($user->can('view', $feedback))->toBeFalse();
    expect($user->can('create', DraftFeedback::class))->toBeFalse();
    expect($user->can('update', $feedback))->toBeFalse();
});

it('gates each action on its ability', function (string $action, string $ability, bool $withModel) {
    $user = UserFactory::new()->create();
    $target = $withModel ? DraftFeedback::factory()->create() : DraftFeedback::class;

    EmailComposer::resolveAbilityUsing(fn ($user, $granted) => $granted === $ability);
    expect($user->can($action, $target))->toBeTrue();

    EmailComposer::resolveAbilityUsing(fn ($user, $granted) => $granted !== $ability);
    expect($user->can($action, $target))->toBeFalse();
})->with([
    'viewAny' => ['viewAny', 'view-feedback', false],
    'view' => ['view', 'view-feedback', true],
    'create' => ['create', 'comment-on-draft', false],
    'update' => ['update', 'update-feedback', true],
]);

it('does not let comment-on-draft read or resolve feedback', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability === 'comment-on-draft');

    $user = UserFactory::new()->create();
    $feedback = DraftFeedback::factory()->create();

    expect($user->can('viewAny', DraftFeedback::class))->toBeFalse();
    expect($user->can('view', $feedback))->toBeFalse();
    expect($user->can('update', $feedback))->toBeFalse();
});

it('does not let view-feedback resolve feedback', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability === 'view-feedback');

    $user = UserFactory::new()->create();
    $feedback = DraftFeedback::factory()->create();

    expect($user->can('view', $feedback))->toBeTrue();
    expect($user->can('update', $feedback))->toBeFalse();
});

it('forwards the feedback to the resolver for per-model abilities', function () {
    $captured = [];
    EmailComposer::resolveAbilityUsing(function ($user, $ability, $model) use (&$captured) {
        $captured[] = [$ability, $model];

        return true;
    });

    $user = UserFactory::new()->create();
    $feedback = DraftFeedback::factory()->create();

    $user->can('viewAny', DraftFeedback::class);
    $user->can('view', $feedback);
    $user->can('create', DraftFeedback::class);
    $user->can('update', $feedback);

    expect($captured)->toBe([
        ['view-feedback', null],
        ['view-feedback', $feedback],
        ['comment-on-draft', null],
        ['update-feedback', $feedback],
    ]);
});
