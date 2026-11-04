<?php

use CSeidl\EmailComposer\Actions\Drafts\SummarizeSend;
use CSeidl\EmailComposer\Data\SendSummary;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Facades\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('email-composer.default_locale', 'en');
});

function summarizableDraft(array $attributes = []): EmailDraft
{
    return EmailDraft::factory()->approved()->create([
        'public_files' => ['en' => 'drafts/1/en.html', 'es' => 'drafts/1/es.html'],
        ...$attributes,
    ]);
}

it('counts the audience of each type', function (EmailAudience $audience) {
    $inAudience = Recipient::factory()->count(2)->create(['locale' => 'en'])
        ->merge(Recipient::factory()->count(3)->create(['locale' => 'es']));
    Recipient::factory()->count(4)->create(['locale' => 'en']);

    $list = RecipientList::factory()->create();
    $list->recipients()->attach($inAudience);
    $draft = summarizableDraft(['audience' => $audience, 'recipient_list_id' => $list->id]);
    $draft->recipients()->attach($inAudience);

    $summary = app(SummarizeSend::class)($draft);

    expect($summary)
        ->toBeInstanceOf(SendSummary::class)
        ->skipped->toBe(0)
        ->and([$summary->total, $summary->perLocale])->toBe(match ($audience) {
            EmailAudience::All => [9, ['en' => 6, 'es' => 3]],
            default => [5, ['en' => 2, 'es' => 3]],
        });
})->with(EmailAudience::cases());

it('counts unsubscribed recipients as skipped and leaves deleted ones out', function () {
    $draft = summarizableDraft();
    $draft->recipients()->attach(Recipient::factory()->count(3)->create(['locale' => 'en']));
    $draft->recipients()->attach(Recipient::factory()->unsubscribed()->count(2)->create(['locale' => 'es']));
    $deleted = Recipient::factory()->count(4)->create(['locale' => 'es']);
    $draft->recipients()->attach($deleted);
    $deleted->each->delete();

    expect(EmailComposer::dryRun($draft))
        ->total->toBe(5)
        ->skipped->toBe(2)
        ->perLocale->toBe(['en' => 3]);
});

it('counts the sendable recipients the draft was already sent to', function () {
    $draft = summarizableDraft();
    $draft->recipients()->attach(Recipient::factory()->count(2)->create(), ['delivery_status' => 'sent']);
    $draft->recipients()->attach(Recipient::factory()->unsubscribed()->create(), ['delivery_status' => 'sent']);
    $draft->recipients()->attach(Recipient::factory()->create(), ['delivery_status' => 'cancelled']);
    $draft->recipients()->attach(Recipient::factory()->create());

    expect(EmailComposer::dryRun($draft)->resent)->toBe(2);
});

it('counts a recipient whose locale has no public file under the default locale', function () {
    $draft = summarizableDraft(['public_files' => ['en' => 'drafts/1/en.html']]);
    $draft->recipients()->attach([
        Recipient::factory()->create(['locale' => 'en'])->id,
        Recipient::factory()->create(['locale' => 'es'])->id,
        Recipient::factory()->create(['locale' => 'fr'])->id,
        Recipient::factory()->create(['locale' => null])->id,
    ]);

    expect(EmailComposer::dryRun($draft)->perLocale)->toBe(['en' => 4]);
});

it('lists the missing placeholders for each delivery locale', function () {
    $draft = summarizableDraft(['placeholders' => [
        'en' => ['greeting' => 'Hello', 'body' => '', 'signature' => 'The team'],
        'es' => ['greeting' => 'Hola', 'body' => 'Cuerpo.', 'signature' => ''],
    ]]);
    $draft->recipients()->attach([
        Recipient::factory()->create(['locale' => 'en'])->id,
        Recipient::factory()->create(['locale' => 'es'])->id,
    ]);

    expect(EmailComposer::dryRun($draft)->missingPlaceholders)->toBe([
        'en' => ['body'],
        'es' => ['signature'],
    ]);
});

it('throws when the draft list no longer exists', function () {
    $draft = summarizableDraft(['audience' => EmailAudience::List, 'recipient_list_id' => null]);

    EmailComposer::dryRun($draft);
})->throws(DomainException::class, 'its recipient list no longer exists');

it('queues and writes nothing', function () {
    Bus::fake();
    Queue::fake();
    $draft = summarizableDraft(['audience' => EmailAudience::All]);
    Recipient::factory()->count(3)->create();
    Recipient::factory()->unsubscribed()->create();

    $writes = [];
    DB::listen(function ($query) use (&$writes) {
        if (! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
            $writes[] = $query->sql;
        }
    });

    EmailComposer::dryRun($draft);

    expect($writes)->toBe([])
        ->and($draft->recipients()->count())->toBe(0);
    Bus::assertNothingDispatched();
    Bus::assertNothingBatched();
    Queue::assertNothingPushed();
});
