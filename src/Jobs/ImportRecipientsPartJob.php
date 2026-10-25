<?php

namespace CSeidl\EmailComposer\Jobs;

use CSeidl\EmailComposer\Actions\Recipients\ImportRecipients;
use CSeidl\EmailComposer\Models\RecipientList;
use Generator;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Imports one part split off by ImportRecipientsJob and caches its counts
 * for the batch's report. Encrypted, since the rows hold email addresses.
 */
class ImportRecipientsPartJob implements ShouldBeEncrypted, ShouldQueue
{
    use Batchable;
    use Queueable;

    /** A retry would import the part again and misreport the counts. */
    public int $tries = 1;

    /**
     * @param  string  $rows  JSON lines, each [line number, cells by column]
     */
    public function __construct(
        public string $importId,
        public int $part,
        public string $rows,
        public ?int $listId = null,
    ) {}

    public function handle(ImportRecipients $import): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $list = $this->listId === null ? null : (RecipientList::find($this->listId)
            ?? throw new \DomainException('The recipient list no longer exists.'));

        Cache::put(
            ImportRecipientsJob::resultKey($this->importId, $this->part),
            $import($this->rows(), $list)->toArray(),
            ImportRecipientsJob::RESULT_TTL,
        );
    }

    /** @return Generator<int, array<string, string>> line number to cells by column */
    protected function rows(): Generator
    {
        foreach (explode("\n", trim($this->rows)) as $json) {
            if ($json === '') {
                continue;
            }

            $row = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($row) || ! is_int($row[0] ?? null) || ! is_array($row[1] ?? null)) {
                throw new \UnexpectedValueException("Malformed row in import part {$this->part}.");
            }

            yield $row[0] => array_map(strval(...), $row[1]);
        }
    }
}
