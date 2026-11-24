<?php

use CSeidl\EmailComposer\Actions\Recipients\UnsubscribeRecipient;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Support\Carbon;

it('unsubscribes a subscribed recipient', function () {
    $recipient = Recipient::factory()->create();

    app(UnsubscribeRecipient::class)($recipient);

    expect($recipient->fresh()->unsubscribed_at)->not->toBeNull();
});

it('keeps the first unsubscribe time', function () {
    $recipient = Recipient::factory()->create(['unsubscribed_at' => Carbon::parse('2026-01-01 00:00:00')]);

    app(UnsubscribeRecipient::class)($recipient);

    expect($recipient->fresh()->unsubscribed_at->toDateTimeString())->toBe('2026-01-01 00:00:00');
});
