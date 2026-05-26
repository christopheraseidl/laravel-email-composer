<?php

namespace CSeidl\EmailComposer;

use Filament\Panel;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EmailComposerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-email-composer')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigrations([
                'create_email_composer_recipients_table',
                'create_email_composer_templates_table',
                // 'create_email_composer_drafts_table',
                // 'create_email_composer_draft_recipient_table',
                // 'create_email_composer_draft_feedbacks_table',
            ])
            ->runsMigrations()
            ->hasRoute('web');
    }

    public function packageBooted(): void
    {
        // Filament integration is opt-in — only register when Filament is present.
        if (! class_exists(Panel::class)) {
            return;
        }

        // Resources are registered via a plugin (see Branch 8); the plugin gives
        // consumers control over which panel(s) get the email-composer UI.
    }
}
