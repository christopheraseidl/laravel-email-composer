<?php

namespace CSeidl\EmailComposer\Commands;

use Illuminate\Console\Command;

class EmailComposerCommand extends Command
{
    public $signature = 'laravel-email-composer';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
