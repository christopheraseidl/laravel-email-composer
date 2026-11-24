<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\DraftFeedback;
use Orchestra\Testbench\Factories\UserFactory;

afterEach(fn () => EmailComposer::flushResolvers());

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
