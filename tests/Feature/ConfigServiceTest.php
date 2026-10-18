<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Services\ConfigService;
use CSeidl\EmailComposer\Tests\Fixtures\User;
use Illuminate\Support\Arr;

function forgetComposerConfig(string $key): void
{
    $config = config('email-composer');
    Arr::forget($config, $key);
    config(['email-composer' => $config]);
}

it('is reachable from EmailComposer', function () {
    expect(EmailComposer::config())->toBeInstanceOf(ConfigService::class);
});

it('returns the configured value', function (string $method, string $key, mixed $value) {
    config(["email-composer.{$key}" => $value]);

    expect(EmailComposer::config()->{$method}())->toBe($value);
})->with([
    ['defaultLocale', 'default_locale', 'es'],
    ['locales', 'locales', ['en', 'fr']],
    ['storageDisk', 'storage.disk', 's3'],
    ['storagePath', 'storage.path', 'mail'],
    ['publicDisk', 'storage.public_disk', 's3'],
    ['templatesPath', 'templates.path', '/tmp/templates'],
    ['queueConnection', 'queue.connection', 'redis'],
    ['queueName', 'queue.queue', 'mail'],
    ['bulkChunkSize', 'bulk.chunk_size', 50],
    ['unsubscribeLinkTtlDays', 'unsubscribe.link_ttl_days', 30],
    ['reviewLinkTtlDays', 'review.link_ttl_days', 3],
    ['navigationGroup', 'filament.navigation_group', 'Mail'],
    ['navigationGroup', 'filament.navigation_group', null],
]);

it('falls back when the key is unset', function (string $method, string $key, mixed $fallback) {
    forgetComposerConfig($key);

    expect(EmailComposer::config()->{$method}())->toBe($fallback);
})->with([
    ['defaultLocale', 'default_locale', 'en'],
    ['storageDisk', 'storage.disk', null],
    ['storagePath', 'storage.path', 'email-composer'],
    ['publicDisk', 'storage.public_disk', 'public'],
    ['templatesPath', 'templates.path', null],
    ['queueConnection', 'queue.connection', null],
    ['queueName', 'queue.queue', null],
    ['bulkChunkSize', 'bulk.chunk_size', 100],
    ['unsubscribeLinkTtlDays', 'unsubscribe.link_ttl_days', null],
    ['reviewLinkTtlDays', 'review.link_ttl_days', 7],
    ['navigationGroup', 'filament.navigation_group', 'Email'],
]);

it('returns the host user model', function () {
    expect(EmailComposer::config()->userModel())->toBe(User::class);
});

it('rejects a user model that is not an Eloquent model', function (mixed $model) {
    config(['auth.providers.users.model' => $model]);

    EmailComposer::config()->userModel();
})->with([null, stdClass::class])->throws(UnexpectedValueException::class);

it('falls back to the default locale when locales is unset', function () {
    config(['email-composer.default_locale' => 'es']);
    forgetComposerConfig('locales');

    expect(EmailComposer::config()->locales())->toBe(['es']);
});
