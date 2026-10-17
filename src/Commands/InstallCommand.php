<?php

namespace CSeidl\EmailComposer\Commands;

use Filament\Panel;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'email-composer:install';

    protected $description = 'Install laravel-email-composer';

    public function handle(): int
    {
        $this->ensureBatchesTable();

        foreach (['config', 'migrations'] as $tag) {
            $this->comment("Publishing {$tag}...");
            $this->callSilently('vendor:publish', ['--tag' => "email-composer-{$tag}"]);
        }

        if ($this->confirm('Would you like to run the migrations now?')) {
            $this->comment('Running migrations...');
            $this->call('migrate');
        }

        $this->printNextSteps();
        $this->info('email-composer has been installed!');

        return self::SUCCESS;
    }

    private function ensureBatchesTable(): void
    {
        try {
            $exists = Schema::connection(config('queue.batching.database'))
                ->hasTable(config('queue.batching.table', 'job_batches'));
        } catch (QueryException) {
            $exists = false;
        }

        if ($exists) {
            $this->info('job_batches table already exists');

            return;
        }

        $this->callSilently('make:queue-batches-table') === self::SUCCESS
            ? $this->info('created job_batches migration')
            : $this->info('job_batches migration already present, run migrate');
    }

    private function printNextSteps(): void
    {
        $this->newLine();
        $this->comment('Next steps:');
        $this->line('Register an ability resolver in a service provider. Every policy denies until you do:');
        $this->line('    EmailComposer::resolveAbilityUsing(fn ($user, string $ability, $model) => ...);');

        if (! class_exists(Panel::class)) {
            return;
        }

        $this->line('Register the plugin on your Filament panel:');
        $this->line('    ->plugin(EmailComposerPlugin::make())');

        $missing = array_keys(array_filter([
            'lara-zeus/spatie-translatable' => ! class_exists('LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin'),
            'bezhansalleh/filament-language-switch' => ! class_exists('BezhanSalleh\LanguageSwitch\LanguageSwitch'),
        ]));

        if ($missing !== []) {
            $this->line('The plugin needs: composer require '.implode(' ', $missing));
        }
    }
}
