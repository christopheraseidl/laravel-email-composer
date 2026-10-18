<?php

use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

pest()->use(RefreshDatabase::class);

it('enforces unique name', function () {
    RecipientList::create(['name' => 'Staff']);

    expect(fn () => RecipientList::create(['name' => 'Staff']))
        ->toThrow(QueryException::class);
});

it('relates lists and recipients in both directions', function () {
    $list = RecipientList::factory()->create();
    $recipients = Recipient::factory()->count(2)->create();

    $list->recipients()->attach($recipients);

    expect($list->recipients->modelKeys())->toEqualCanonicalizing($recipients->modelKeys())
        ->and($recipients->first()->lists->modelKeys())->toBe([$list->id]);
});

it('keeps pivot rows when a list is soft deleted and restored', function () {
    $list = RecipientList::factory()->create();
    $recipient = Recipient::factory()->create();
    $list->recipients()->attach($recipient);

    $list->delete();

    expect(DB::table('email_composer_list_recipient')->count())->toBe(1)
        ->and($recipient->fresh()->lists)->toBeEmpty();

    $list->restore();

    expect($recipient->fresh()->lists->modelKeys())->toBe([$list->id]);
});

it('removes pivot rows when a list is force deleted', function () {
    $list = RecipientList::factory()->create();
    $list->recipients()->attach(Recipient::factory()->create());

    $list->forceDelete();

    expect(DB::table('email_composer_list_recipient')->count())->toBe(0);
});

it('keeps pivot rows when a recipient is soft deleted', function () {
    $list = RecipientList::factory()->create();
    $recipient = Recipient::factory()->create();
    $list->recipients()->attach($recipient);

    $recipient->delete();

    expect(DB::table('email_composer_list_recipient')->count())->toBe(1)
        ->and($list->recipients()->count())->toBe(0);
});

it('removes pivot rows when a recipient is force deleted', function () {
    $list = RecipientList::factory()->create();
    $recipient = Recipient::factory()->create();
    $list->recipients()->attach($recipient);

    $recipient->forceDelete();

    expect(DB::table('email_composer_list_recipient')->count())->toBe(0);
});
