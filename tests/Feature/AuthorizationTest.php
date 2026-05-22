<?php

use CSeidl\EmailComposer\EmailComposer;
use Orchestra\Testbench\Factories\UserFactory;

afterEach(fn () => EmailComposer::flushResolvers());

it('denies when no user is provided', function () {
    expect(EmailComposer::userCan(null, 'view-recipients'))->toBeFalse();
});

it('denies when no resolver is registered', function () {
    $user = UserFactory::new()->create();
    expect(EmailComposer::userCan($user, 'view-recipients'))->toBeFalse();
});

it('uses the registered ability resolver', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability) => $ability === 'view-recipients');

    $user = UserFactory::new()->create();
    expect(EmailComposer::userCan($user, 'view-recipients'))->toBeTrue();
    expect(EmailComposer::userCan($user, 'delete-recipients'))->toBeFalse();
});

it('passes the user and ability to the resolver', function () {
    $captured = [];
    EmailComposer::resolveAbilityUsing(function ($user, $ability) use (&$captured) {
        $captured = ['user_id' => $user->id, 'ability' => $ability];

        return true;
    });

    $user = UserFactory::new()->create();
    EmailComposer::userCan($user, 'send-draft');

    expect($captured)->toBe(['user_id' => $user->id, 'ability' => 'send-draft']);
});

it('coerces non-boolean resolver returns to bool', function () {
    EmailComposer::resolveAbilityUsing(fn () => 1);
    $user = UserFactory::new()->create();
    expect(EmailComposer::userCan($user, 'anything'))->toBeTrue();

    EmailComposer::resolveAbilityUsing(fn () => null);
    expect(EmailComposer::userCan($user, 'anything'))->toBeFalse();
});
