<?php

use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;

afterEach(fn () => array_map(unlink(...), glob(csvFixturePrefix().'*') ?: []));

it('prints the counts and a table of rejected rows', function () {
    Recipient::factory()->create(['email' => 'ada@example.com']);
    $list = RecipientList::factory()->create(['name' => 'Newsletter']);

    $this->artisan('email-composer:import-recipients', [
        'file' => csvFixture("email,locale\nada@example.com,es\ngrace@example.com,\nbad,en\n"),
        '--list' => 'Newsletter',
    ])
        ->expectsOutput('Created: 1')
        ->expectsOutput('Updated: 1')
        ->expectsOutput('Skipped: 0')
        ->expectsOutput('Rejected: 1')
        ->expectsTable(['Line', 'Reason'], [[4, 'The email field must be a valid email address.']])
        ->assertSuccessful();

    expect($list->recipients()->count())->toBe(2);
});

it('finds the list by id', function () {
    $list = RecipientList::factory()->create();

    $this->artisan('email-composer:import-recipients', [
        'file' => csvFixture("email\nada@example.com\n"),
        '--list' => (string) $list->id,
    ])->assertSuccessful();

    expect($list->recipients()->count())->toBe(1);
});

it('fails on an unknown list, a missing file, or a file without an email column', function () {
    $this->artisan('email-composer:import-recipients', [
        'file' => csvFixture("email\nada@example.com\n"),
        '--list' => 'Missing',
    ])->expectsOutput('No recipient list matches Missing.')->assertFailed();

    $this->artisan('email-composer:import-recipients', ['file' => '/nonexistent/recipients.csv'])
        ->expectsOutput('Cannot read /nonexistent/recipients.csv.')
        ->assertFailed();

    $this->artisan('email-composer:import-recipients', ['file' => csvFixture("name\nAda\n")])
        ->expectsOutput('The file has no email column.')
        ->assertFailed();

    expect(Recipient::count())->toBe(0);
});
