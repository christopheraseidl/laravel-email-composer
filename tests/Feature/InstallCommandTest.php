<?php

use CSeidl\EmailComposer\Commands\InstallCommand;
use CSeidl\EmailComposer\Tests\Fixtures\InstallCommandWithMissingClasses;
use Filament\Panel;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

function deletePublishedInstallFiles(): void
{
    File::delete(config_path('email-composer.php'));
    File::delete(File::glob(database_path('migrations/*_create_email_composer_*_table.php')));
    File::delete(File::glob(database_path('migrations/*_create_job_batches_table.php')));
    File::delete(File::glob(database_path('migrations/*_create_notifications_table.php')));
}

function bindInstallCommandWithMissingClasses(string ...$classes): void
{
    InstallCommandWithMissingClasses::$missing = $classes;
    app()->bind(InstallCommand::class, InstallCommandWithMissingClasses::class);
}

it('publishes the config and migrations, and the migrate prompt can be declined', function () {
    try {
        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->doesntExpectOutputToContain('Running migrations')
            ->assertSuccessful();

        expect(File::exists(config_path('email-composer.php')))->toBeTrue()
            ->and(File::glob(database_path('migrations/*_create_email_composer_*_table.php')))->toHaveCount(8);
    } finally {
        deletePublishedInstallFiles();
    }
});

it('reports an existing job_batches table', function () {
    try {
        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('job_batches table already exists')
            ->assertSuccessful();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('generates the job_batches migration when the table is missing', function () {
    Schema::drop('job_batches');

    try {
        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('created job_batches migration')
            ->assertSuccessful();

        expect(File::glob(database_path('migrations/*_create_job_batches_table.php')))->toHaveCount(1);

        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('job_batches migration already present, run migrate')
            ->assertSuccessful();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('generates the notifications migration when the table is missing', function () {
    Schema::drop('notifications');

    try {
        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('job_batches table already exists')
            ->expectsOutputToContain('created notifications migration')
            ->assertSuccessful();

        expect(File::glob(database_path('migrations/*_create_notifications_table.php')))->toHaveCount(1);
    } finally {
        deletePublishedInstallFiles();
    }
});

it('skips the job_batches migration when batches are not stored in the database', function () {
    config()->set('queue.batching.driver', 'dynamodb');
    Schema::drop('job_batches');

    try {
        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('job_batches table skipped')
            ->assertSuccessful();

        expect(File::glob(database_path('migrations/*_create_job_batches_table.php')))->toBeEmpty();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('prints nothing about Filament with --no-filament', function () {
    Process::fake();

    try {
        $this->artisan('email-composer:install', ['--no-filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('resolveAbilityUsing')
            ->doesntExpectOutputToContain('Filament')
            ->doesntExpectOutputToContain('EmailComposerPlugin')
            ->doesntExpectOutputToContain('databaseNotifications')
            ->doesntExpectOutputToContain('composer require')
            ->assertSuccessful();

        Process::assertNothingRan();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('asks for Filament to be set up first when it is not installed', function () {
    Process::fake();
    bindInstallCommandWithMissingClasses(Panel::class);

    try {
        $this->artisan('email-composer:install', ['--filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('Install and set up Filament, then run php artisan email-composer:install again.')
            ->doesntExpectOutputToContain('EmailComposerPlugin')
            ->doesntExpectOutputToContain('databaseNotifications')
            ->doesntExpectOutputToContain('composer require')
            ->assertSuccessful();

        Process::assertNothingRan();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('requires only the missing plugin packages with composer', function () {
    Process::fake();
    bindInstallCommandWithMissingClasses('BezhanSalleh\LanguageSwitch\LanguageSwitch');

    try {
        $this->artisan('email-composer:install', ['--filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsConfirmation('The plugin needs bezhansalleh/filament-language-switch. Install with composer now?', 'yes')
            ->expectsOutputToContain('->plugin(EmailComposerPlugin::make())')
            ->doesntExpectOutputToContain('composer require')
            ->assertSuccessful();

        Process::assertRanTimes(
            fn (PendingProcess $process) => $process->command === ['composer', 'require', 'bezhansalleh/filament-language-switch'],
        );
    } finally {
        deletePublishedInstallFiles();
    }
});

it('prints the composer command when composer require fails', function () {
    Process::fake(fn () => Process::result(exitCode: 1));
    bindInstallCommandWithMissingClasses('LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin');

    try {
        $this->artisan('email-composer:install', ['--filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsConfirmation('The plugin needs lara-zeus/spatie-translatable. Install with composer now?', 'yes')
            ->expectsOutputToContain('composer require lara-zeus/spatie-translatable')
            ->assertSuccessful();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('prints the ability resolver and plugin next steps', function () {
    try {
        $this->artisan('email-composer:install', ['--filament' => true])
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('resolveAbilityUsing')
            ->expectsOutputToContain('->plugin(EmailComposerPlugin::make())')
            ->expectsOutputToContain('->databaseNotifications()')
            ->doesntExpectOutputToContain('composer require')
            ->assertSuccessful();
    } finally {
        deletePublishedInstallFiles();
    }
});
