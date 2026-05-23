<?php

use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

pest()->use(RefreshDatabase::class);

it('persists name, email, locale, and metadata', function () {
    $recipient = Recipient::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'locale' => 'es',
        'metadata' => ['department' => 'literature'],
    ]);

    expect($recipient->fresh())
        ->name->toBe('John Doe')
        ->email->toBe('john@example.com')
        ->locale->toBe('es')
        ->metadata->toBe(['department' => 'literature']);
});

it('enforces unique email', function () {
    Recipient::create(['name' => 'A', 'email' => 'dup@example.com']);

    expect(fn () => Recipient::create(['name' => 'B', 'email' => 'dup@example.com']))
        ->toThrow(QueryException::class);
});

it('scopes subscribed and unsubscribed recipients', function () {
    Recipient::factory()->create(['email' => 'subscribed@example.com']);
    Recipient::factory()->create(['email' => 'unsubscribed@example.com', 'unsubscribed_at' => now()]);

    expect(Recipient::subscribed()->pluck('email')->all())->toBe(['subscribed@example.com']);
    expect(Recipient::unsubscribed()->pluck('email')->all())->toBe(['unsubscribed@example.com']);
});

it('casts unsubscribed_at to a datetime', function () {
    $recipient = Recipient::factory()->create(['unsubscribed_at' => now()]);

    expect($recipient->fresh()->unsubscribed_at)->toBeInstanceOf(Carbon::class);
});

it('marks recipients as unsubscribed via the factory state', function () {
    $recipient = Recipient::factory()->unsubscribed()->create();

    expect($recipient->fresh()->unsubscribed_at)->not->toBeNull();
});
