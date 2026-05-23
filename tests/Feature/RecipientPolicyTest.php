<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

afterEach(fn () => EmailComposer::flushResolvers());

it('denies all actions when no resolver is registered', function () {
    $user = UserFactory::new()->create();
    $recipient = Recipient::factory()->create();

    expect($user->can('viewAny', Recipient::class))->toBeFalse();
    expect($user->can('view', $recipient))->toBeFalse();
    expect($user->can('create', Recipient::class))->toBeFalse();
    expect($user->can('update', $recipient))->toBeFalse();
    expect($user->can('delete', $recipient))->toBeFalse();
});

it('grants actions based on the registered resolver', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, $ability, $model) => in_array($ability, [
        'view-recipients',
        'view-recipient',
        'create-recipients',
        'update-recipient',
        'restore-recipient',
    ]));

    $user = UserFactory::new()->create();
    $recipient = Recipient::factory()->create();

    expect($user->can('viewAny', $recipient))->toBeTrue();
    expect($user->can('view', $recipient))->toBeTrue();
    expect($user->can('create', $recipient))->toBeTrue();
    expect($user->can('update', $recipient))->toBeTrue();
    expect($user->can('delete', $recipient))->toBeFalse();
    expect($user->can('restore', $recipient))->toBeTrue();
    expect($user->can('forceDelete', $recipient))->toBeFalse();
});

it('forwards the recipient instance to the resolver for per-model abilities', function () {
    $captured = [];
    EmailComposer::resolveAbilityUsing(function ($user, $ability, $model) use (&$captured) {
        $captured[$ability] = $model;

        return true;
    });

    $user = UserFactory::new()->create();
    $recipient = Recipient::factory()->create();

    $user->can('view', $recipient);
    $user->can('update', $recipient);
    $user->can('delete', $recipient);
    $user->can('restore', $recipient);
    $user->can('forceDelete', $recipient);

    expect($captured['view-recipient'])->toBe($recipient);
    expect($captured['update-recipient'])->toBe($recipient);
    expect($captured['delete-recipient'])->toBe($recipient);
    expect($captured['restore-recipient'])->toBe($recipient);
    expect($captured['force-delete-recipient'])->toBe($recipient);
});
