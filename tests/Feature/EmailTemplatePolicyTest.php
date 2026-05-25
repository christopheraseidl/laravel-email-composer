<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Orchestra\Testbench\Factories\UserFactory;

afterEach(fn () => EmailComposer::flushResolvers());

it('denies all actions when no resolver is registered', function () {
    $user = UserFactory::new()->create();
    $template = EmailTemplate::factory()->create();

    expect($user->can('view', $template))->toBeFalse();
    expect($user->can('create', EmailTemplate::class))->toBeFalse();
    expect($user->can('update', $template))->toBeFalse();
    expect($user->can('delete', $template))->toBeFalse();
});

it('grants view and update but not delete with a partial resolver', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => in_array($ability, [
        'view-templates', 'view-template', 'update-template',
    ]));

    $user = UserFactory::new()->create();
    $template = EmailTemplate::factory()->create();

    expect($user->can('viewAny', EmailTemplate::class))->toBeTrue();
    expect($user->can('view', $template))->toBeTrue();
    expect($user->can('create', EmailTemplate::class))->toBeFalse();
    expect($user->can('update', $template))->toBeTrue();
    expect($user->can('delete', $template))->toBeFalse();
    expect($user->can('restore', $template))->toBeFalse();
    expect($user->can('force-delete', $template))->toBeFalse();
});
