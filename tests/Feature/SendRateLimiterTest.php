<?php

use CSeidl\EmailComposer\Services\SendRateLimiter;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

function sendCount(string $name): int
{
    return (int) app(RateLimiter::class)->attempts(md5(SendRateLimiter::NAME.$name));
}

it('reserves a send against every limit', function () {
    config([
        'email-composer.queue.rate_limit_per_minute' => 2,
        'email-composer.queue.rate_limit_per_hour' => 2,
    ]);

    expect(app(SendRateLimiter::class)->attempt())->toBeTrue()
        ->and(sendCount('minute'))->toBe(1)
        ->and(sendCount('hour'))->toBe(1);
});

it('takes back what it reserved when a later limit is full', function () {
    config([
        'email-composer.queue.rate_limit_per_minute' => 5,
        'email-composer.queue.rate_limit_per_hour' => 1,
    ]);
    $limiter = app(SendRateLimiter::class);
    $limiter->attempt();

    expect($limiter->attempt())->toBeFalse()
        ->and(sendCount('minute'))->toBe(1)
        ->and(sendCount('hour'))->toBe(1);
});

it('refuses the last slot to a second worker whose read was stale', function () {
    // Reads report no sends, as if another worker sent between this read and the increment.
    $stale = new class(app('cache')->store()) extends RateLimiter
    {
        public function attempts($key)
        {
            return 0;
        }
    };
    $stale->for(SendRateLimiter::NAME, fn () => [new Limit('minute', 1, 60)]);
    $limiter = new SendRateLimiter($stale);

    expect($limiter->attempt())->toBeTrue()
        ->and($limiter->attempt())->toBeFalse()
        ->and(sendCount('minute'))->toBe(1);
});
