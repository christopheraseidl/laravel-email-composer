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
    ['rateLimitPerMinute', 'queue.rate_limit_per_minute', 600],
    ['rateLimitPerHour', 'queue.rate_limit_per_hour', 6000],
    ['rateLimitPerDay', 'queue.rate_limit_per_day', 60000],
    ['rateLimitPerMonth', 'queue.rate_limit_per_month', 600000],
    ['bulkChunkSize', 'bulk.chunk_size', 50],
    ['importMaxFileSize', 'import.max_file_size', 2048],
    ['importRowsPerPart', 'import.rows_per_part', 500],
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
    ['importMaxFileSize', 'import.max_file_size', 10240],
    ['importRowsPerPart', 'import.rows_per_part', 2000],
    ['unsubscribeLinkTtlDays', 'unsubscribe.link_ttl_days', null],
    ['reviewLinkTtlDays', 'review.link_ttl_days', 7],
    ['navigationGroup', 'filament.navigation_group', 'Email'],
]);

it('reads a rate limit from an env string', function () {
    config(['email-composer.queue.rate_limit_per_minute' => '600']);

    expect(EmailComposer::config()->rateLimitPerMinute())->toBe(600);
});

it('has no rate limit for null or below 1', function (string $method, string $key, mixed $value) {
    config(["email-composer.queue.{$key}" => $value]);

    expect(EmailComposer::config()->{$method}())->toBeNull();
})->with([
    ['rateLimitPerMinute', 'rate_limit_per_minute'],
    ['rateLimitPerHour', 'rate_limit_per_hour'],
    ['rateLimitPerDay', 'rate_limit_per_day'],
    ['rateLimitPerMonth', 'rate_limit_per_month'],
])->with([null, 0, -1, '0', '']);

it('has a custom rate limit only when the amount and the window are both set', function (mixed $amount, mixed $seconds, ?array $expected) {
    config([
        'email-composer.queue.custom_rate_limit_amount' => $amount,
        'email-composer.queue.custom_rate_limit_seconds' => $seconds,
    ]);

    expect(EmailComposer::config()->customRateLimit())->toBe($expected);
})->with([
    'both' => ['500', '10', ['amount' => 500, 'seconds' => 10]],
    'no window' => [500, null, null],
    'no amount' => [null, 10, null],
    'a zero window' => [500, 0, null],
]);

it('lists every configured rate limit with its window', function () {
    config([
        'email-composer.queue.rate_limit_per_minute' => 100,
        'email-composer.queue.rate_limit_per_month' => 50000,
        'email-composer.queue.custom_rate_limit_amount' => 500,
        'email-composer.queue.custom_rate_limit_seconds' => 10,
    ]);

    expect(EmailComposer::config()->rateLimits())->toBe([
        'minute' => ['amount' => 100, 'seconds' => 60],
        'month' => ['amount' => 50000, 'seconds' => 30 * 86400],
        'custom' => ['amount' => 500, 'seconds' => 10],
    ]);
});

it('translates the navigation group', function (?string $group, ?string $expected) {
    app()->setLocale('es');
    config(['email-composer.filament.navigation_group' => $group]);

    expect(EmailComposer::config()->navigationGroup())->toBe($expected);
})->with([
    'the default key' => ['email-composer::navigation.group', 'Correo'],
    'a plain string' => ['Mail', 'Mail'],
    'null' => [null, null],
]);

it('keeps import uploads under the storage path', function () {
    config(['email-composer.storage.path' => 'mail']);

    expect(EmailComposer::config()->importsPath())->toBe('mail/imports');
});

it('reports the public disk visibility', function (?string $diskVisibility, string $expected) {
    config([
        'email-composer.storage.public_disk' => 's3',
        'filesystems.disks.s3.visibility' => $diskVisibility,
    ]);

    expect(EmailComposer::config()->publicDiskVisibility())->toBe($expected);
})->with([
    ['public', 'public'],
    [null, 'private'],
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
