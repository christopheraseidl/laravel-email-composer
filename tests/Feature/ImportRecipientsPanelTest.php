<?php

use CSeidl\EmailComposer\Actions\Recipients\ImportRecipients;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Resources\Recipients\Pages\ListRecipients;
use CSeidl\EmailComposer\Jobs\ImportRecipientsJob;
use CSeidl\EmailComposer\Jobs\ImportRecipientsPartJob;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Support\CsvReader;
use Illuminate\Bus\PendingBatch;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

beforeEach(fn () => Storage::fake('local'));

afterEach(fn () => EmailComposer::flushResolvers());

function csvUpload(string $contents): File
{
    return UploadedFile::fake()->createWithContent('recipients.csv', $contents);
}

/** @return array<string, mixed> the data of the user's only database notification */
function importNotification(): array
{
    return auth()->user()->notifications()->sole()->data;
}

function storedImports(): array
{
    return Storage::disk('local')->allFiles(EmailComposer::config()->importsPath());
}

/** Run every queued job on the database queue, the way a worker would. */
function workDatabaseQueue(bool $once = false): void
{
    test()->artisan('queue:work', ['connection' => 'database', $once ? '--once' : '--stop-when-empty' => true]);
}

it('hides the import action without the import-recipients ability', function () {
    EmailComposer::resolveAbilityUsing(fn ($user, string $ability) => $ability !== 'import-recipients');

    livewire(ListRecipients::class)->assertActionHidden('import');

    EmailComposer::resolveAbilityUsing(fn () => true);

    livewire(ListRecipients::class)->assertActionVisible('import');
});

it('queues the import instead of running it in the request', function () {
    Queue::fake();
    $list = RecipientList::factory()->create();

    livewire(ListRecipients::class)
        ->callAction('import', data: [
            'file' => csvUpload("email\nada@example.com\n"),
            'list' => $list->id,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Import queued');

    Queue::assertPushed(ImportRecipientsJob::class, fn (ImportRecipientsJob $job) => $job->userId === auth()->id()
        && $job->listId === $list->id
        && Storage::disk('local')->exists($job->path));

    expect(Recipient::count())->toBe(0)
        ->and(storedImports())->toHaveCount(1);
});

it('rejects a file over the configured size', function () {
    Queue::fake();
    config()->set('email-composer.import.max_file_size', 1);

    livewire(ListRecipients::class)
        ->callAction('import', data: ['file' => csvUpload("email\n".str_repeat("person@example.com\n", 100))])
        ->assertHasActionErrors(['file']);

    Queue::assertNothingPushed();
});

it('imports into the list, notifies the user with the counts, and deletes the upload', function () {
    $list = RecipientList::factory()->create();

    livewire(ListRecipients::class)->callAction('import', data: [
        'file' => csvUpload("email,name\nada@example.com,Ada\nbad,Bad\n"),
        'list' => $list->id,
    ]);

    expect(Recipient::sole()->email)->toBe('ada@example.com')
        ->and($list->recipients()->count())->toBe(1)
        ->and(importNotification())->toMatchArray([
            'title' => 'Recipients imported',
            'body' => 'Created 1, updated 0, skipped 0, rejected 1.<br>Line 3: The email field must be a valid email address.',
            'status' => 'warning',
        ])
        ->and(storedImports())->toBe([]);
});

it('imports names from a Windows-1252 upload intact', function () {
    livewire(ListRecipients::class)->callAction('import', data: [
        'file' => csvUpload("email,name\nada@example.com,Jos\xE9\ngrace@example.com,Nu\xF1o\n"),
    ]);

    expect(Recipient::orderBy('email')->pluck('name')->all())->toBe(['José', 'Nuño'])
        ->and(importNotification())->toMatchArray([
            'title' => 'Recipients imported',
            'status' => 'success',
        ]);
});

it('notifies the user when the file has no email column', function () {
    livewire(ListRecipients::class)->callAction('import', data: ['file' => csvUpload("name\nAda\n")]);

    expect(importNotification())->toMatchArray([
        'title' => 'Import failed',
        'body' => 'The file has no email column.',
        'status' => 'danger',
    ])
        ->and(Recipient::count())->toBe(0)
        ->and(storedImports())->toBe([]);
});

it('notifies the user when the list was deleted before the job ran', function () {
    $list = RecipientList::factory()->create();
    Storage::disk('local')->put('upload.csv', "email\nada@example.com\n");
    $job = new ImportRecipientsJob(auth()->user(), 'upload.csv', $list->id);
    $list->forceDelete();

    dispatch_sync($job);

    expect(importNotification())->toMatchArray([
        'title' => 'Import failed',
        'body' => 'The recipient list no longer exists.',
    ])
        ->and(Recipient::count())->toBe(0)
        ->and(Storage::disk('local')->exists('upload.csv'))->toBeFalse();
});

it('notifies the user and deletes the upload when splitting throws', function () {
    $this->mock(CsvReader::class)->shouldReceive('rows')->andThrow(new RuntimeException('Disk failed'));
    Storage::disk('local')->put('upload.csv', "email\nada@example.com\n");

    expect(fn () => dispatch(new ImportRecipientsJob(auth()->user(), 'upload.csv')))
        ->toThrow(RuntimeException::class, 'Disk failed');

    expect(importNotification())->toMatchArray([
        'title' => 'Import failed',
        'body' => 'The import stopped with an error before any rows were imported.',
        'status' => 'danger',
    ])
        ->and(Storage::disk('local')->exists('upload.csv'))->toBeFalse();
});

it('keeps every row for one email in the same part, so the last row still wins', function () {
    config()->set('email-composer.import.rows_per_part', 1);
    Storage::disk('local')->put('upload.csv', "email,name\nada@example.com,First\ngrace@example.com,Grace\nADA@example.com,Last\nbad,Bad\n");

    dispatch_sync(new ImportRecipientsJob(auth()->user(), 'upload.csv'));

    expect(Recipient::firstWhere('email', 'ADA@example.com')->name)->toBe('Last')
        ->and(Recipient::count())->toBe(2)
        ->and(importNotification())->toMatchArray([
            'title' => 'Recipients imported',
            'body' => 'Created 2, updated 0, skipped 1, rejected 1.<br>Line 5: The email field must be a valid email address.',
        ])
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('splits wide rows into more parts to keep each payload small', function () {
    Bus::fake([ImportRecipientsPartJob::class]);
    config()->set('email-composer.import.rows_per_part', 100000);
    $notes = str_repeat('x', 200);
    Storage::disk('local')->put('upload.csv', "email,notes\n".collect(range(1, 3000))
        ->map(fn (int $i) => "person{$i}@example.com,{$notes}")->implode("\n"));

    dispatch_sync(new ImportRecipientsJob(auth()->user(), 'upload.csv'));

    Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() > 1
        && $batch->jobs->sum(fn (ImportRecipientsPartJob $job) => substr_count($job->rows, "\n")) === 3000
        && $batch->jobs->every(fn (ImportRecipientsPartJob $job) => strlen($job->rows) < 1024 * 1024));
});

it('encrypts the part payloads, which hold email addresses', function () {
    config()->set('queue.default', 'database');
    Storage::disk('local')->put('upload.csv', "email\nada@example.com\n");

    dispatch(new ImportRecipientsJob(auth()->user(), 'upload.csv'));
    workDatabaseQueue(once: true);

    $payload = DB::table('jobs')->sole()->payload;

    expect($payload)->toContain('ImportRecipientsPartJob')
        ->not->toContain('ada@example.com');
});

it('reports a failed part and still imports the others', function () {
    config()->set('queue.default', 'database');
    config()->set('email-composer.import.rows_per_part', 1);

    // Two emails that hash to different parts of two.
    $emails = collect(range(1, 50))->map(fn (int $i) => "person{$i}@example.com")
        ->groupBy(fn (string $email) => crc32($email) % 2)
        ->map->first();

    // Part 0 runs first and fails; part 1 runs the real import.
    $real = app(ImportRecipients::class);
    $calls = 0;
    $this->partialMock(ImportRecipients::class)->shouldReceive('__invoke')
        ->andReturnUsing(function (iterable $rows, ?RecipientList $list = null) use ($real, &$calls) {
            if (++$calls === 1) {
                // Not a lost-connection message, which would stop the worker.
                throw new RuntimeException('Part import failed');
            }

            return $real($rows, $list);
        });

    Storage::disk('local')->put('upload.csv', "email\n{$emails[0]}\n{$emails[1]}\n");
    dispatch(new ImportRecipientsJob(auth()->user(), 'upload.csv'));
    workDatabaseQueue();

    expect(Recipient::pluck('email')->all())->toBe([$emails[1]])
        ->and(importNotification())->toMatchArray([
            'title' => 'Import partly failed',
            'body' => 'Created 1, updated 0, skipped 0, rejected 0.<br>1 of 2 parts failed. Some of their rows may be imported.',
            'status' => 'danger',
        ]);
});

it('still cleans up when the user is deleted before the parts finish', function () {
    config()->set('queue.default', 'database');
    Storage::disk('local')->put('upload.csv', "email\nada@example.com\n");
    $job = new ImportRecipientsJob(auth()->user(), 'upload.csv');

    dispatch($job);
    workDatabaseQueue(once: true);
    auth()->user()->delete();
    workDatabaseQueue();

    expect(Recipient::sole()->email)->toBe('ada@example.com')
        ->and(DB::table('notifications')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(0)
        ->and(Cache::has(ImportRecipientsJob::resultKey($job->importId, 0)))->toBeFalse();
});

it('sweeps uploads older than a day when an import starts', function () {
    $disk = Storage::disk('local');
    $directory = EmailComposer::config()->importsPath();
    $disk->put("{$directory}/stale.csv", "email\n");
    $disk->put("{$directory}/recent.csv", "email\n");
    $disk->put("{$directory}/upload.csv", "email\nada@example.com\n");
    touch($disk->path("{$directory}/stale.csv"), now()->subDays(2)->getTimestamp());

    dispatch_sync(new ImportRecipientsJob(auth()->user(), "{$directory}/upload.csv"));

    expect($disk->allFiles($directory))->toBe(["{$directory}/recent.csv"])
        ->and(Recipient::sole()->email)->toBe('ada@example.com');
});

it('reports an empty import for a file with only a header', function () {
    livewire(ListRecipients::class)->callAction('import', data: ['file' => csvUpload("email,name\n")]);

    expect(importNotification())->toMatchArray([
        'title' => 'Recipients imported',
        'body' => 'Created 0, updated 0, skipped 0, rejected 0.',
        'status' => 'success',
    ]);
});

it('runs on the configured queue', function () {
    config()->set('email-composer.queue.connection', 'database');
    config()->set('email-composer.queue.queue', 'imports');

    $job = new ImportRecipientsJob(auth()->user(), 'upload.csv');

    expect($job)->connection->toBe('database')->queue->toBe('imports')->tries->toBe(1);
});
