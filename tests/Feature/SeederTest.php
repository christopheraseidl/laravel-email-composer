<?php

use CSeidl\EmailComposer\Database\Seeders\EmailDraftSeeder;
use CSeidl\EmailComposer\Database\Seeders\RecipientListSeeder;
use CSeidl\EmailComposer\Database\Seeders\RecipientSeeder;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;

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
