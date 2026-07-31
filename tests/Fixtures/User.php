<?php

namespace CSeidl\EmailComposer\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Orchestra\Testbench\Factories\UserFactory;

/**
 * Stand-in for the host application's user model.
 *
 * Testbench's default (Illuminate\Foundation\Auth\User) has no HasFactory, so
 * EmailDraftFactory's `config('auth.providers.users.model')::factory()` call
 * would fail against it. Real applications always have the trait.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    /** @return Factory<static> */
    protected static function newFactory(): Factory
    {
        // Named explicitly: TestCase rewrites factory-name guessing to the
        // package's own factory namespace, which holds no UserFactory.
        return UserFactory::new();
    }
}
