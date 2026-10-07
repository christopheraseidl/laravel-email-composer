<?php

use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Unsubscribe\UnsubscribeLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    config()->set('email-composer.default_locale', 'en');
});

it('builds a link without expiry when no ttl is configured', function () {
    config()->set('email-composer.unsubscribe.link_ttl_days', null);
    $link = UnsubscribeLink::for(Recipient::factory()->create());

    expect($link)->not->toContain('expires=');

    $this->travel(5)->years();

    $this->get($link)->assertOk();
});

it('builds a link that expires after the configured ttl', function () {
    config()->set('email-composer.unsubscribe.link_ttl_days', 3);
    $link = UnsubscribeLink::for(Recipient::factory()->create());

    expect($link)->toContain('expires=');

    $this->travel(3)->days();
    $this->get($link)->assertOk();

    $this->travel(1)->minute();
    $this->get($link)->assertForbidden();
    $this->post($link)->assertForbidden();
});

it('shows a confirmation with the recipient email and a form posting to the signed link', function () {
    $recipient = Recipient::factory()->create(['email' => 'ada@example.com', 'locale' => 'en']);
    $link = UnsubscribeLink::for($recipient);

    $this->get($link)
        ->assertOk()
        ->assertViewIs('email-composer::unsubscribe.show')
        ->assertSee('ada@example.com')
        ->assertSee('action="'.e($link).'"', false);

    expect($recipient->fresh()->unsubscribed_at)->toBeNull();
});

it('unsubscribes the recipient and shows the done page', function () {
    $recipient = Recipient::factory()->create(['email' => 'ada@example.com', 'locale' => 'en']);

    $this->post(UnsubscribeLink::for($recipient))
        ->assertOk()
        ->assertViewIs('email-composer::unsubscribe.done')
        ->assertSee('ada@example.com');

    expect($recipient->fresh()->unsubscribed_at)->not->toBeNull();
});

it('keeps the original unsubscribe time when posted again', function () {
    $recipient = Recipient::factory()->create(['unsubscribed_at' => Carbon::parse('2026-01-01 00:00:00')]);

    $this->post(UnsubscribeLink::for($recipient))->assertOk();

    expect($recipient->fresh()->unsubscribed_at->toDateTimeString())->toBe('2026-01-01 00:00:00');
});

it('refuses a tampered link', function () {
    $recipient = Recipient::factory()->create();
    $other = Recipient::factory()->create();
    $tampered = str_replace("/unsubscribe/{$recipient->id}?", "/unsubscribe/{$other->id}?", UnsubscribeLink::for($recipient));

    $this->get($tampered)->assertForbidden();
    $this->post($tampered)->assertForbidden();
    $this->post(UnsubscribeLink::for($recipient).'x')->assertForbidden();

    expect(Recipient::unsubscribed()->count())->toBe(0);
});

it('renders in the recipient locale', function (?string $locale, string $title, string $done) {
    $recipient = Recipient::factory()->create(['locale' => $locale]);
    $link = UnsubscribeLink::for($recipient);

    $this->get($link)->assertOk()->assertSee($title);
    $this->post($link)->assertOk()->assertSee($done);
})->with([
    'english' => ['en', 'Unsubscribe', 'You have been unsubscribed'],
    'spanish' => ['es', 'Darse de baja', 'Se ha dado de baja'],
    'no locale' => [null, 'Unsubscribe', 'You have been unsubscribed'],
    'unconfigured locale' => ['fr', 'Unsubscribe', 'You have been unsubscribed'],
]);

it('has every translation key for every configured locale', function () {
    $keys = array_keys(require __DIR__.'/../../resources/lang/en/unsubscribe.php');

    foreach (config('email-composer.locales') as $locale) {
        foreach ($keys as $key) {
            expect(trans()->hasForLocale("email-composer::unsubscribe.{$key}", $locale))
                ->toBeTrue("Missing {$key} for {$locale}");
        }
    }
});
