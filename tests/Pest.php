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
