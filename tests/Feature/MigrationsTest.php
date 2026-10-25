<?php

use CSeidl\EmailComposer\EmailComposerServiceProvider;
use Illuminate\Support\Facades\DB;

// SQLite has no name limit, so compile what MySQL and Postgres would run, without connecting.
it('keeps every identifier within the database name limit', function (string $driver, int $limit) {
    $default = config('database.default');
    config()->set("database.connections.{$driver}-limits", [
        'driver' => $driver,
        'host' => '127.0.0.1',
        'database' => 'unused',
        'prefix' => '',
    ]);
    config()->set('database.default', "{$driver}-limits");

    try {
        $queries = DB::connection("{$driver}-limits")->pretend(function () {
            foreach (EmailComposerServiceProvider::MIGRATIONS as $migration) {
                (include __DIR__."/../../database/migrations/{$migration}.php.stub")->up();
            }
        });
    } finally {
        config()->set('database.default', $default);
    }

    $tooLong = collect($queries)
        ->flatMap(fn (array $query) => preg_match_all('/[`"](\w+)[`"]/', $query['query'], $matches) ? $matches[1] : [])
        ->unique()
        ->filter(fn (string $identifier) => strlen($identifier) > $limit)
        ->values()
        ->all();

    expect($queries)->not->toBeEmpty()
        ->and($tooLong)->toBe([]);
})->with([
    'mysql' => ['mysql', 64],
    'pgsql' => ['pgsql', 63],
]);
