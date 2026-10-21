<?php

namespace CSeidl\EmailComposer;

use CSeidl\EmailComposer\Commands\InstallCommand;
use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Mail\MailableSender;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EmailComposerServiceProvider extends PackageServiceProvider
{
    /** In dependency order: a table comes after every table it references. */
    public const MIGRATIONS = [
        'create_email_composer_recipients_table',
        'create_email_composer_recipient_lists_table',
        'create_email_composer_list_recipient_table',
        'create_email_composer_templates_table',
        'create_email_composer_theme_table',
        'create_email_composer_drafts_table',
        'create_email_composer_draft_recipient_table',
        'create_email_composer_draft_feedback_table',
    ];

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-email-composer')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasAssets()
            ->hasMigrations(self::MIGRATIONS)
            ->hasRoute('web')
            ->hasConsoleCommand(InstallCommand::class);
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->bind(EmailSenderInterface::class, MailableSender::class);
        $this->app->scoped(TemplateRegistry::class, fn () => new TemplateRegistry);

        parent::packageRegistered();
    }
}
