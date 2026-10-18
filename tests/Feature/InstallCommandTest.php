<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

pest()->use(RefreshDatabase::class);

function deletePublishedInstallFiles(): void
{
    File::delete(config_path('email-composer.php'));
    File::delete(File::glob(database_path('migrations/*_create_email_composer_*_table.php')));
    File::delete(File::glob(database_path('migrations/*_create_job_batches_table.php')));
}

it('publishes the config and migrations, and the migrate prompt can be declined', function () {
    try {
        $this->artisan('email-composer:install')
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
        $this->artisan('email-composer:install')
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
        $this->artisan('email-composer:install')
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('created job_batches migration')
            ->assertSuccessful();

        expect(File::glob(database_path('migrations/*_create_job_batches_table.php')))->toHaveCount(1);

        $this->artisan('email-composer:install')
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('job_batches migration already present, run migrate')
            ->assertSuccessful();
    } finally {
        deletePublishedInstallFiles();
    }
});

it('prints the ability resolver and plugin next steps', function () {
    try {
        $this->artisan('email-composer:install')
            ->expectsConfirmation('Would you like to run the migrations now?', 'no')
            ->expectsOutputToContain('resolveAbilityUsing')
            ->expectsOutputToContain('->plugin(EmailComposerPlugin::make())')
            ->doesntExpectOutputToContain('composer require')
            ->assertSuccessful();
    } finally {
        deletePublishedInstallFiles();
    }
});
