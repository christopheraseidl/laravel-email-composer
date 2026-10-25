<?php

namespace CSeidl\EmailComposer\Commands;

use CSeidl\EmailComposer\Actions\Recipients\ImportRecipients;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Support\CsvReader;
use Illuminate\Console\Command;

class ImportRecipientsCommand extends Command
{
    protected $signature = 'email-composer:import-recipients
                            {file : path to a CSV file with a header row}
                            {--list= : recipient list id or name}';

    protected $description = 'Create or update recipients from a CSV file';

    public function handle(ImportRecipients $import, CsvReader $reader): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error("Cannot read {$path}.");

            return self::FAILURE;
        }

        $list = null;
        $option = $this->option('list');

        if (is_string($option) && $option !== '') {
            $list = RecipientList::query()
                ->where('name', $option)
                ->when(ctype_digit($option), fn ($query) => $query->orWhere('id', (int) $option))
                ->first();

            if ($list === null) {
                $this->error("No recipient list matches {$option}.");

                return self::FAILURE;
            }
        }

        try {
            $result = $import($reader->rows($path, ['email']), $list);
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Created: {$result->created}");
        $this->info("Updated: {$result->updated}");
        $this->info("Skipped: {$result->skipped}");
        $this->info('Rejected: '.count($result->rejected));

        if ($result->rejected !== []) {
            $this->table(
                ['Line', 'Reason'],
                array_map(fn (int $line, string $reason) => [$line, $reason], array_keys($result->rejected), $result->rejected),
            );
        }

        return self::SUCCESS;
    }
}
