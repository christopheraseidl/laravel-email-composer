<?php

namespace CSeidl\EmailComposer\Jobs;

use Closure;
use CSeidl\EmailComposer\Contracts\ImportNotifierInterface;
use CSeidl\EmailComposer\Data\ImportResult;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Support\CsvReader;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Splits an uploaded CSV into parts small enough to finish within a
 * worker's timeout, imports them as a batch, and reports the totals to the
 * user through ImportNotifierInterface.
 *
 * Rows go to a part by a hash of their email, so every row for one email
 * lands in the same part in file order: the last one still wins, and no two
 * parts race to create the same recipient.
 */
class ImportRecipientsJob implements ShouldQueue
{
    use Queueable;

    /** Raw JSON per part; stays under SQS's 1 MiB once serialized and encrypted. */
    public const MAX_PART_BYTES = 256 * 1024;

    /** Seconds the parts' results wait in the cache for the report. */
    public const RESULT_TTL = 7 * 24 * 60 * 60;

    /** Uploads older than this are left over from imports that never ran. */
    protected const STALE_UPLOAD_SECONDS = 24 * 60 * 60;

    /** A retry would split and import the file again. */
    public int $tries = 1;

    public string $importId;

    /** An id rather than the model, so a user deleted before the job runs does not fail it. */
    public mixed $userId;

    /** The dispatching request's locale, which the notifications use. */
    public string $locale;

    /**
     * @param  string  $path  the upload, on the configured storage disk
     */
    public function __construct(
        Authenticatable $user,
        public string $path,
        public ?int $listId = null,
    ) {
        $this->userId = $user->getAuthIdentifier();
        $this->importId = (string) Str::uuid();
        $this->locale = app()->getLocale();

        $this->onConnection(EmailComposer::config()->queueConnection())
            ->onQueue(EmailComposer::config()->queueName());
    }

    public function handle(CsvReader $reader, ImportNotifierInterface $notifier): void
    {
        static::inLocale($this->locale, fn () => $this->import($reader, $notifier));
    }

    protected function import(CsvReader $reader, ImportNotifierInterface $notifier): void
    {
        $this->pruneStaleUploads();

        $local = tempnam(sys_get_temp_dir(), 'email-composer-import-')
            ?: throw new \RuntimeException('Cannot create a temporary file for the import.');

        try {
            if ($this->listId !== null && ! RecipientList::whereKey($this->listId)->exists()) {
                throw new \DomainException(__('email-composer::import.notifications.missing_list'));
            }

            $this->copyToLocal($local);
            $parts = $this->split($reader, $local);
        } catch (\DomainException $e) {
            $user = static::user($this->userId);

            if ($user !== null) {
                $notifier->failed($user, $e->getMessage());
            }

            return;
        } finally {
            File::delete($local);
            $this->disk()->delete($this->path);
        }

        if ($parts === []) {
            $user = static::user($this->userId);

            if ($user !== null) {
                $notifier->finished($user, new ImportResult, 0, 0);
            }

            return;
        }

        // The callback is stored with the batch, so it holds only scalars: a
        // captured model that was since deleted would keep it from loading.
        $importId = $this->importId;
        $userId = $this->userId;
        $count = count($parts);
        $locale = $this->locale;

        Bus::batch(array_map(
            fn (int $part) => new ImportRecipientsPartJob($importId, $part, $parts[$part], $this->listId, $locale),
            array_keys($parts),
        ))
            ->name('email-composer: import recipients')
            ->allowFailures()
            ->when(EmailComposer::config()->queueConnection(), fn ($batch, $connection) => $batch->onConnection($connection))
            ->onQueue(EmailComposer::config()->queueName())
            ->finally(static fn (Batch $batch) => static::notifyTotals($importId, $userId, $count, $batch->failedJobs, $locale))
            ->dispatch();
    }

    /**
     * Handle an unexpected error before the parts are dispatched.
     */
    public function failed(Throwable $e): void
    {
        $this->disk()->delete($this->path);

        $user = static::user($this->userId);

        if ($user !== null) {
            static::inLocale($this->locale, fn () => app(ImportNotifierInterface::class)
                ->failed($user, __('email-composer::import.notifications.stopped')));
        }
    }

    /**
     * Total the parts' cached results and notify the user, if they still exist.
     */
    public static function notifyTotals(string $importId, mixed $userId, int $parts, int $failed, string $locale): void
    {
        $result = ImportResult::combine(...array_map(
            fn (int $part) => ImportResult::fromArray((array) Cache::pull(static::resultKey($importId, $part), [])),
            range(0, $parts - 1),
        ));

        $user = static::user($userId);

        if ($user !== null) {
            static::inLocale($locale, fn () => app(ImportNotifierInterface::class)->finished($user, $result, $failed, $parts));
        }
    }

    public static function resultKey(string $importId, int $part): string
    {
        return "email-composer:import:{$importId}:{$part}";
    }

    /**
     * Route the rows into parts, one JSON line per row.
     *
     * @return array<int, string> part number to its JSON lines
     *
     * @throws \DomainException when the file has no email column
     */
    protected function split(CsvReader $reader, string $local): array
    {
        $rows = $bytes = 0;

        foreach ($reader->rows($local, ['email']) as $line => $cells) {
            $rows++;
            $bytes += strlen(json_encode([$line, $cells], JSON_THROW_ON_ERROR)) + 1;
        }

        $parts = (int) max(
            ceil($rows / EmailComposer::config()->importRowsPerPart()),
            ceil($bytes / self::MAX_PART_BYTES),
        );

        if ($parts === 0) {
            return [];
        }

        $handles = [];

        try {
            foreach (range(0, $parts - 1) as $part) {
                $handles[$part] = tmpfile() ?: throw new \RuntimeException('Cannot create a temporary file for the import.');
            }

            foreach ($reader->rows($local, ['email']) as $line => $cells) {
                $part = isset($cells['email'])
                    ? crc32(mb_strtolower($cells['email'])) % $parts
                    : $line % $parts;

                fwrite($handles[$part], json_encode([$line, $cells], JSON_THROW_ON_ERROR)."\n");
            }

            return array_map(function ($handle) {
                rewind($handle);

                return (string) stream_get_contents($handle);
            }, $handles);
        } finally {
            array_map(fclose(...), $handles);
        }
    }

    /** Delete uploads left behind by imports that were lost before they ran. */
    protected function pruneStaleUploads(): void
    {
        $disk = $this->disk();
        $cutoff = now()->getTimestamp() - self::STALE_UPLOAD_SECONDS;

        foreach ($disk->files(EmailComposer::config()->importsPath()) as $file) {
            if ($file !== $this->path && $disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }

    /** The reader uses SplFileObject, which needs a local path. */
    protected function copyToLocal(string $local): void
    {
        $stream = $this->disk()->readStream($this->path)
            ?? throw new \RuntimeException("Cannot read the uploaded file {$this->path}.");

        try {
            file_put_contents($local, $stream);
        } finally {
            fclose($stream);
        }
    }

    /** The importing user, unless they were deleted since. */
    protected static function user(mixed $userId): ?Authenticatable
    {
        $user = EmailComposer::config()->userModel()::query()->whereKey($userId)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * Run the callback in the importing user's locale. Static, since the
     * batch callback holds no job, so it takes Localizable from a bare object.
     *
     * @param  Closure(): mixed  $callback
     */
    protected static function inLocale(string $locale, Closure $callback): void
    {
        (new class
        {
            use Localizable;
        })->withLocale($locale, $callback);
    }

    protected function disk(): Filesystem
    {
        return Storage::disk(EmailComposer::config()->storageDisk());
    }
}
