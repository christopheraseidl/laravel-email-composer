<?php

use CSeidl\EmailComposer\Actions\Recipients\ImportRecipients;
use CSeidl\EmailComposer\Data\ImportResult;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Support\CsvReader;
use Illuminate\Support\Facades\Schema;

function importCsv(string $contents, ?RecipientList $list = null): ImportResult
{
    return app(ImportRecipients::class)(app(CsvReader::class)->rows(csvFixture($contents), ['email']), $list);
}

afterEach(fn () => array_map(unlink(...), glob(csvFixturePrefix().'*') ?: []));

it('creates recipients, falling back to the email for a missing name', function () {
    $result = importCsv("email,name,locale\nada@example.com,Ada,es\ngrace@example.com,,\n");

    expect($result->created)->toBe(2)
        ->and($result->updated)->toBe(0)
        ->and($result->rejected)->toBe([])
        ->and(Recipient::firstWhere('email', 'ada@example.com'))
        ->name->toBe('Ada')
        ->locale->toBe('es')
        ->and(Recipient::firstWhere('email', 'grace@example.com'))
        ->name->toBe('grace@example.com')
        ->locale->toBeNull();
});

it('updates a recipient matched by email case-insensitively, and reads a messy header', function () {
    $recipient = Recipient::factory()->create(['email' => 'Ada@Example.com', 'name' => 'Old', 'locale' => 'en']);

    $result = importCsv(" EMAIL , Name ,LOCALE\nada@EXAMPLE.com,Ada Lovelace,es\n");

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(1)
        ->and(Recipient::count())->toBe(1)
        ->and($recipient->fresh())
        ->email->toBe('Ada@Example.com')
        ->name->toBe('Ada Lovelace')
        ->locale->toBe('es');
});

it('reads a header that starts with a byte-order mark', function (string $header) {
    $result = importCsv("\xEF\xBB\xBF{$header},name\nada@example.com,Ada\n");

    expect($result->created)->toBe(1)
        ->and(Recipient::sole()->email)->toBe('ada@example.com');
})->with([
    'mark then name' => 'email',
    'mark, space, then name' => ' Email',
]);

it('indexes lower(email) so the lookup can use it', function () {
    expect(collect(Schema::getIndexes('email_composer_recipients'))->pluck('name'))
        ->toContain('email_composer_recipients_email_lower_index');
});

it('does not rewrite a recipient whose values are unchanged', function () {
    $recipient = Recipient::factory()->create(['email' => 'ada@example.com', 'name' => 'Ada', 'locale' => 'es']);
    $this->travel(1)->hour();

    $result = importCsv("email,name,locale\nada@example.com,Ada,es\n");

    expect($result->updated)->toBe(1)
        ->and($recipient->fresh()->updated_at->equalTo($recipient->updated_at))->toBeTrue();
});

it('keeps the existing name and locale when the cells are empty', function () {
    $recipient = Recipient::factory()->create(['email' => 'ada@example.com', 'name' => 'Ada', 'locale' => 'es']);

    importCsv("email,name,locale\nada@example.com,,\n");

    expect($recipient->fresh())->name->toBe('Ada')->locale->toBe('es');
});

it('leaves an unsubscribed recipient unsubscribed', function () {
    $recipient = Recipient::factory()->unsubscribed()->create(['email' => 'ada@example.com']);
    $unsubscribedAt = $recipient->unsubscribed_at;

    $result = importCsv("email,name,unsubscribed_at\nada@example.com,Ada,\n");

    expect($result->updated)->toBe(1)
        ->and($recipient->fresh()->unsubscribed_at->equalTo($unsubscribedAt))->toBeTrue();
});

it('leaves a deleted recipient deleted and counts it as skipped', function () {
    $recipient = Recipient::factory()->create(['email' => 'ada@example.com', 'name' => 'Ada']);
    $recipient->delete();
    $list = RecipientList::factory()->create();

    $result = importCsv("email,name\nADA@example.com,Changed\n", $list);

    expect($result->skipped)->toBe(1)
        ->and($result->updated)->toBe(0)
        ->and($result->created)->toBe(0)
        ->and($recipient->fresh())
        ->trashed()->toBeTrue()
        ->name->toBe('Ada')
        ->and($list->recipients()->count())->toBe(0);
});

it('rejects an invalid email and an unknown locale with their line numbers', function () {
    $result = importCsv("email,locale\nnot-an-email,en\n\nada@example.com,fr\n,en\ngrace@example.com,es\n");

    expect($result->created)->toBe(1)
        ->and(array_keys($result->rejected))->toBe([2, 4, 5])
        ->and($result->rejected[2])->toContain('valid email')
        ->and($result->rejected[4])->toContain('locale')
        ->and($result->rejected[5])->toContain('required')
        ->and(Recipient::pluck('email')->all())->toBe(['grace@example.com']);
});

it('stores extra columns in metadata, merged over existing keys', function () {
    $recipient = Recipient::factory()->create([
        'email' => 'ada@example.com',
        'metadata' => ['plan' => 'free', 'team' => 'math'],
    ]);

    importCsv("email,Plan,city,empty\nada@example.com,paid,London,\nnew@example.com,,Paris,\n");

    expect($recipient->fresh()->metadata)->toBe(['plan' => 'paid', 'team' => 'math', 'city' => 'London'])
        ->and(Recipient::firstWhere('email', 'new@example.com')->metadata)->toBe(['city' => 'Paris']);
});

it('lets the last row win for a repeated email', function () {
    $result = importCsv("email,name\nada@example.com,First\nADA@example.com,Last\n");

    expect($result->created)->toBe(1)
        ->and($result->skipped)->toBe(1)
        ->and(Recipient::sole())->email->toBe('ADA@example.com')->name->toBe('Last');
});

it('attaches imported recipients to a list and keeps existing members', function () {
    $list = RecipientList::factory()->create();
    $member = Recipient::factory()->create();
    $existing = Recipient::factory()->create(['email' => 'ada@example.com']);
    $list->recipients()->attach([$member->id, $existing->id]);

    importCsv("email\nada@example.com\ngrace@example.com\n", $list);

    expect($list->recipients()->pluck('email')->sort()->values()->all())
        ->toBe(collect([$member->email, 'ada@example.com', 'grace@example.com'])->sort()->values()->all());
});

it('imports more rows than fit in one chunk', function () {
    $rows = collect(range(1, ImportRecipients::CHUNK_SIZE + 1))->map(fn (int $i) => "person{$i}@example.com");

    $result = importCsv("email\n".$rows->implode("\n")."\n");

    expect($result->created)->toBe(ImportRecipients::CHUNK_SIZE + 1)
        ->and(Recipient::count())->toBe(ImportRecipients::CHUNK_SIZE + 1);
});

it('rejects a file without an email column', function (string $contents) {
    importCsv($contents);
})->with([
    'no email column' => "name,locale\nAda,en\n",
    'empty file' => '',
])->throws(DomainException::class, 'no email column');

it('reads cells saved as Windows-1252, as Excel on Windows saves them', function () {
    $result = importCsv("email,name,poblaci\xF3n\nada@example.com,Jos\xE9,Logro\xF1o\ngrace@example.com,Nu\xF1o,\n");

    expect($result->created)->toBe(2)
        ->and(Recipient::firstWhere('email', 'ada@example.com'))
        ->name->toBe('José')
        ->metadata->toBe(['población' => 'Logroño'])
        ->and(Recipient::firstWhere('email', 'grace@example.com')->name)->toBe('Nuño');
});

it('leaves UTF-8 cells as they are', function () {
    importCsv("email,name\nada@example.com,José\ngrace@example.com,Nuño\n");

    expect(Recipient::orderBy('email')->pluck('name')->all())->toBe(['José', 'Nuño']);
});

it('rejects a name longer than the column holds', function () {
    $result = importCsv("email,name\nada@example.com,".str_repeat('a', 256)."\ngrace@example.com,".str_repeat('a', 255)."\n");

    expect(array_keys($result->rejected))->toBe([2])
        ->and($result->rejected[2])->toContain('name')
        ->and(Recipient::pluck('email')->all())->toBe(['grace@example.com']);
});

it('counts a repeated email as skipped when the repeat lands in a later chunk', function () {
    $others = collect(range(1, ImportRecipients::CHUNK_SIZE))->map(fn (int $i) => "person{$i}@example.com,Person");

    $result = importCsv("email,name\nada@example.com,First\n".$others->implode("\n")."\nADA@example.com,Last\n");

    expect($result->created)->toBe(ImportRecipients::CHUNK_SIZE + 1)
        ->and($result->updated)->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and(Recipient::firstWhere('email', 'ada@example.com')->name)->toBe('Last');
});

it('combines results, keeping rejected lines in line order', function () {
    $result = ImportResult::combine(
        new ImportResult(1, 2, 3, [9 => 'nine', 2 => 'two']),
        new ImportResult(4, 5, 6, [5 => 'five']),
    );

    expect($result)
        ->created->toBe(5)
        ->updated->toBe(7)
        ->skipped->toBe(9)
        ->rejected->toBe([2 => 'two', 5 => 'five', 9 => 'nine'])
        ->and(ImportResult::fromArray($result->toArray()))->toEqual($result);
});
