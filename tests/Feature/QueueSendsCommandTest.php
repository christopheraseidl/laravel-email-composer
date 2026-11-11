<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function queueSendsEvent(): Event
{
    app()->forgetInstance(Schedule::class);

    return collect(app(Schedule::class)->events())
        ->sole(fn (Event $event) => str_contains((string) $event->command, 'email-composer:queue-sends'));
}

it('runs as often as the shortest rate limit window allows', function (array $limits, ?int $seconds) {
    config(collect($limits)->mapWithKeys(fn ($value, $key) => ["email-composer.queue.{$key}" => $value])->all());

    $event = queueSendsEvent();

    expect($event->expression)->toBe('* * * * *')
        ->and($event->repeatSeconds)->toBe($seconds);
})->with([
    'no limit' => [[], null],
    'per minute' => [['rate_limit_per_minute' => 600], null],
    'per hour' => [['rate_limit_per_hour' => 600], null],
    'every 10 seconds' => [['custom_rate_limit_amount' => 500, 'custom_rate_limit_seconds' => 10], 10],
    'every 7 seconds, the next shorter frequency' => [['custom_rate_limit_amount' => 50, 'custom_rate_limit_seconds' => 7], 5],
    'every second' => [['custom_rate_limit_amount' => 5, 'custom_rate_limit_seconds' => 1], 1],
]);
