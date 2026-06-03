<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTheme;
use Orchestra\Testbench\Factories\UserFactory;

afterEach(fn () => EmailComposer::flushResolvers());

it('denies all actions when no resolver is registered', function () {
    $user = UserFactory::new()->create();
    $theme = EmailTheme::current();

    expect($user->can('view', $theme))->toBeFalse();
    expect($user->can('update', $theme))->toBeFalse();
});

it('grants view but not update with a partial resolver', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => in_array($ability, [
        'view-theme',
    ]));

    $user = UserFactory::new()->create();
    $theme = EmailTheme::current();

    expect($user->can('view', $theme))->toBeTrue();
    expect($user->can('update', $theme))->toBeFalse();
});
