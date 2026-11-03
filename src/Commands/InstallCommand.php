<?php

namespace CSeidl\EmailComposer\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'email-composer:install
        {--filament : Set up the Filament plugin without asking}
        {--no-filament : Skip the Filament plugin setup}';

    protected $description = 'Install laravel-email-composer';

    public function handle(): int
    {
        if (config('queue.batching.driver', 'database') === 'database') {
            $this->ensureTable(
                'job_batches',
                config('queue.batching.database'),
                config('queue.batching.table', 'job_batches'),
                'make:queue-batches-table',
            );
        } else {
            $this->info('job_batches table skipped, queue.batching.driver is not database');
        }

        // Panel imports report back through Filament database notifications.
        if (class_exists('Filament\Panel')) {
            $this->ensureTable('notifications', null, 'notifications', 'make:notifications-table');
        }

        foreach (['config', 'migrations'] as $tag) {
            $this->comment("Publishing {$tag}...");
            $this->callSilently('vendor:publish', ['--tag' => "email-composer-{$tag}"]);
        }

        if ($this->confirm('Would you like to run the migrations now?')) {
            $this->comment('Running migrations...');
            $this->call('migrate');
        }

        $this->configureFilament();
        $this->printNextSteps();
        $this->info('email-composer has been installed!');

        return self::SUCCESS;
    }

    private function ensureTable(string $label, ?string $connection, string $table, string $command): void
    {
        try {
            $exists = Schema::connection($connection)->hasTable($table);
        } catch (QueryException) {
            $exists = false;
        }

        if ($exists) {
            $this->info("{$label} table already exists");

            return;
        }

        $this->callSilently($command) === self::SUCCESS
            ? $this->info("created {$label} migration")
            : $this->info("{$label} migration already present, run migrate");
    }

    private function configureFilament(): void
    {
        $usesFilament = match (true) {
            (bool) $this->option('filament') => true,
            (bool) $this->option('no-filament') => false,
            default => $this->confirm('Are you using Filament?', false),
        };

        if (! $usesFilament) {
            return;
        }

        if (! $this->classExists('Filament\Panel')) {
            $this->line('Install and set up Filament, then run php artisan email-composer:install again.');

            return;
        }

        $missing = array_keys(array_filter([
            'lara-zeus/spatie-translatable' => ! $this->classExists('LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin'),
            'bezhansalleh/filament-language-switch' => ! $this->classExists('BezhanSalleh\LanguageSwitch\LanguageSwitch'),
        ]));

        if ($missing !== []) {
            $installed = $this->confirm('The plugin needs '.implode(' and ', $missing).'. Install with composer now?', true)
                && Process::forever()->tty(Process::supportsTty())->run(['composer', 'require', ...$missing])->successful();

            if (! $installed) {
                $this->line('Install the plugin dependencies:');
                $this->line('    composer require '.implode(' ', $missing));
            }
        }

        $this->newLine();
        $this->line('Register the plugin on your Filament panel:');
        $this->line('    ->plugin(EmailComposerPlugin::make())');
        $this->line('Enable database notifications on the panel to see recipient import results:');
        $this->line('    ->databaseNotifications()');
    }

    protected function classExists(string $class): bool
    {
        return class_exists($class);
    }

    private function printNextSteps(): void
    {
        $this->newLine();
        $this->comment('Next steps:');
        $this->line('Register an ability resolver in a service provider. Every policy denies until you do:');
        $this->line('    EmailComposer::resolveAbilityUsing(fn ($user, string $ability, $model) => ...);');
    }
}
