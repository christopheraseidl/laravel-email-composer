<?php

use CSeidl\EmailComposer\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/** Per parallel worker, so one worker's cleanup leaves another's files alone. */
function csvFixturePrefix(): string
{
    return sys_get_temp_dir().'/email-composer-import-'.(getenv('TEST_TOKEN') ?: '0').'-';
}

function csvFixture(string $contents): string
{
    $path = csvFixturePrefix().bin2hex(random_bytes(4)).'.csv';
    file_put_contents($path, $contents);

    return $path;
}

/**
 * Run every queued job on the database queue, the way a worker would. The worker's
 * default 128 MB limit stops it after one job in a long-running test process.
 */
function workDatabaseQueue(bool $once = false): void
{
    test()->artisan('queue:work', [
        'connection' => 'database',
        '--sleep' => 0,
        '--memory' => 4096,
        $once ? '--once' : '--stop-when-empty' => true,
    ]);
}
