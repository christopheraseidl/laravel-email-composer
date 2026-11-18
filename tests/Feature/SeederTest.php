<?php

use CSeidl\EmailComposer\Database\Seeders\EmailDraftSeeder;
use CSeidl\EmailComposer\Database\Seeders\RecipientListSeeder;
use CSeidl\EmailComposer\Database\Seeders\RecipientSeeder;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Models\SendFailure;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed([RecipientSeeder::class, RecipientListSeeder::class, EmailDraftSeeder::class]);
});

it('seeds three active lists and one trashed list', function () {
    expect(RecipientList::count())->toBe(3)
        ->and(RecipientList::onlyTrashed()->count())->toBe(1);
});

it('gives each active list members', function () {
    RecipientList::all()->each(
        fn (RecipientList $list) => expect($list->recipients()->exists())->toBeTrue()
    );
});

it('puts a recipient on two lists', function () {
    expect(Recipient::has('lists', '>=', 2)->exists())->toBeTrue();
});

it('seeds a list-audience draft with an audience', function () {
    $draft = EmailDraft::where('audience', EmailAudience::List)->sole();

    expect($draft->recipients()->exists())->toBeFalse()
        ->and($draft->hasAudience())->toBeTrue();
});

it('seeds one failure row per try that threw, matching each delivery', function () {
    $rows = DB::table('email_composer_draft_recipient')->get();

    $rows->each(function (object $row) {
        $failures = SendFailure::where('email_draft_id', $row->email_draft_id)
            ->where('recipient_id', $row->recipient_id)
            ->orderByDesc('failed_at')
            ->pluck('error');

        match ($row->delivery_status) {
            'failed' => expect($failures)->toHaveCount($row->attempts)->and($failures->first())->toBe($row->error),
            'sent' => expect($failures)->toHaveCount($row->attempts - 1),
            default => expect($failures)->toBeEmpty(),
        };
    });

    expect(SendFailure::count())->toBeGreaterThan(0)
        ->and($rows->where('delivery_status', 'sent')->where('attempts', '>', 1))->not->toBeEmpty();
});

it('seeds a draft for each delivery state the Deliveries tab shows', function () {
    $statuses = fn (EmailDraftStatus $status) => EmailDraft::inStatus($status)->sole()
        ->recipients()->withTrashed()->pluck('delivery_status')->unique()->sort()->values()->all();
    $queued = EmailDraft::inStatus(EmailDraftStatus::Sending)->sole()
        ->recipients()->wherePivot('delivery_status', 'pending')->pluck('queued_at');

    expect($statuses(EmailDraftStatus::Sent))->toBe(['failed', 'sent', 'skipped'])
        ->and($statuses(EmailDraftStatus::Sending))->toBe(['failed', 'pending', 'sent'])
        ->and($statuses(EmailDraftStatus::Cancelled))->toBe(['cancelled', 'failed', 'sent'])
        ->and($queued->filter()->count())->toBe(1)
        ->and($queued->reject(fn ($value) => $value !== null)->count())->toBe(2);
});
