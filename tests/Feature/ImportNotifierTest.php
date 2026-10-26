<?php

use CSeidl\EmailComposer\Contracts\ImportNotifierInterface;
use CSeidl\EmailComposer\Data\ImportResult;
use CSeidl\EmailComposer\Filament\Notifications\FilamentImportNotifier;
use CSeidl\EmailComposer\Jobs\ImportRecipientsJob;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Notifications\NullImportNotifier;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

it('binds the Filament notifier when Filament is installed', function () {
    expect(app(ImportNotifierInterface::class))->toBeInstanceOf(FilamentImportNotifier::class);
});

it('imports without notifying when the null notifier is bound', function () {
    app()->bind(ImportNotifierInterface::class, NullImportNotifier::class);
    Storage::disk('local')->put('upload.csv', "email\nada@example.com\n");

    dispatch_sync(new ImportRecipientsJob(auth()->user(), 'upload.csv'));

    expect(Recipient::sole()->email)->toBe('ada@example.com')
        ->and(DB::table('notifications')->count())->toBe(0);
});

it('reports the totals to the bound notifier', function () {
    $this->mock(ImportNotifierInterface::class)->shouldReceive('finished')->once()->withArgs(
        fn (Authenticatable $user, ImportResult $result, int $failedParts, int $parts) => $user->is(auth()->user())
            && $result->created === 1
            && $result->rejected === [3 => 'The email field must be a valid email address.']
            && $failedParts === 0
            && $parts === 1,
    );
    Storage::disk('local')->put('upload.csv', "email\nada@example.com\nbad\n");

    dispatch_sync(new ImportRecipientsJob(auth()->user(), 'upload.csv'));
});

it('notifies in the locale the import was dispatched in', function (string $csv, array $expected) {
    Storage::disk('local')->put('upload.csv', $csv);

    app()->setLocale('es');
    $job = new ImportRecipientsJob(auth()->user(), 'upload.csv');
    app()->setLocale('en');

    dispatch_sync($job);

    expect(auth()->user()->notifications()->sole()->data)->toMatchArray($expected)
        ->and(app()->getLocale())->toBe('en');
})->with([
    'finished' => ["email\nada@example.com\n", [
        'title' => 'Destinatarios importados',
        'body' => 'Creados: 1, actualizados: 0, omitidos: 0, rechazados: 0.',
    ]],
    'failed' => ["name\nAda\n", [
        'title' => 'Importación fallida',
        'body' => 'El archivo no tiene una columna email.',
    ]],
]);

it('reports why an import stopped to the bound notifier', function () {
    $this->mock(ImportNotifierInterface::class)->shouldReceive('failed')->once()->withArgs(
        fn (Authenticatable $user, string $reason) => $user->is(auth()->user()) && $reason === 'The file has no email column.',
    );
    Storage::disk('local')->put('upload.csv', "name\nAda\n");

    dispatch_sync(new ImportRecipientsJob(auth()->user(), 'upload.csv'));
});
