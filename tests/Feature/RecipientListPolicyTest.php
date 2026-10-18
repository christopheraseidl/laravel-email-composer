<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Factories\UserFactory;

pest()->use(RefreshDatabase::class);

afterEach(fn () => EmailComposer::flushResolvers());

it('denies all actions when no resolver is registered', function () {
    EmailComposer::flushResolvers();

    $user = UserFactory::new()->create();
    $list = RecipientList::factory()->create();

    expect($user->can('viewAny', RecipientList::class))->toBeFalse();
    expect($user->can('view', $list))->toBeFalse();
    expect($user->can('create', RecipientList::class))->toBeFalse();
    expect($user->can('update', $list))->toBeFalse();
    expect($user->can('delete', $list))->toBeFalse();
    expect($user->can('restore', $list))->toBeFalse();
    expect($user->can('forceDelete', $list))->toBeFalse();
});

it('maps each policy method to its ability', function (string $method, string $ability, bool $withModel) {
    EmailComposer::resolveAbilityUsing(fn ($user, $granted) => $granted === $ability);

    $user = UserFactory::new()->create();
    $list = RecipientList::factory()->create();
    $argument = $withModel ? $list : RecipientList::class;

    expect($user->can($method, $argument))->toBeTrue();

    EmailComposer::resolveAbilityUsing(fn ($user, $granted) => $granted !== $ability);

    expect($user->can($method, $argument))->toBeFalse();
})->with([
    ['viewAny', 'view-recipient-lists', false],
    ['view', 'view-recipient-list', true],
    ['create', 'create-recipient-lists', false],
    ['update', 'update-recipient-list', true],
    ['delete', 'delete-recipient-list', true],
    ['restore', 'restore-recipient-list', true],
    ['forceDelete', 'force-delete-recipient-list', true],
]);

it('forwards the list instance to the resolver for per-model abilities', function () {
    $captured = [];
    EmailComposer::resolveAbilityUsing(function ($user, $ability, $model) use (&$captured) {
        $captured[$ability] = $model;

        return true;
    });

    $user = UserFactory::new()->create();
    $list = RecipientList::factory()->create();

    $user->can('view', $list);
    $user->can('update', $list);
    $user->can('delete', $list);
    $user->can('restore', $list);
    $user->can('forceDelete', $list);

    expect($captured['view-recipient-list'])->toBe($list);
    expect($captured['update-recipient-list'])->toBe($list);
    expect($captured['delete-recipient-list'])->toBe($list);
    expect($captured['restore-recipient-list'])->toBe($list);
    expect($captured['force-delete-recipient-list'])->toBe($list);
});
