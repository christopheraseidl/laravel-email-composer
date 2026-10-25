<?php

namespace CSeidl\EmailComposer\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class InstallCommand extends Command
{
    protected $signature = 'email-composer:install';

    protected $description = 'Install laravel-email-composer';

    public function handle(): int
    {
        $this->ensureTable(
            'job_batches',
            config('queue.batching.database'),
            config('queue.batching.table', 'job_batches'),
            'make:queue-batches-table',
        );

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

    private function printNextSteps(): void
    {
        $this->newLine();
        $this->comment('Next steps:');
        $this->line('Register an ability resolver in a service provider. Every policy denies until you do:');
        $this->line('    EmailComposer::resolveAbilityUsing(fn ($user, string $ability, $model) => ...);');

        if (! class_exists('Filament\Panel')) {
            return;
        }

        $this->line('Register the plugin on your Filament panel:');
        $this->line('    ->plugin(EmailComposerPlugin::make())');
        $this->line('Enable database notifications on the panel to see recipient import results:');
        $this->line('    ->databaseNotifications()');

        $missing = array_keys(array_filter([
            'lara-zeus/spatie-translatable' => ! class_exists('LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin'),
            'bezhansalleh/filament-language-switch' => ! class_exists('BezhanSalleh\LanguageSwitch\LanguageSwitch'),
        ]));

        if ($missing !== []) {
            $this->line('The plugin needs: composer require '.implode(' ', $missing));
        }
    }
}
