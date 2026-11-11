<?php

namespace CSeidl\EmailComposer;

use CSeidl\EmailComposer\Commands\ImportRecipientsCommand;
use CSeidl\EmailComposer\Commands\InstallCommand;
use CSeidl\EmailComposer\Commands\QueueSendsCommand;
use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Contracts\ImportNotifierInterface;
use CSeidl\EmailComposer\Filament\Notifications\FilamentImportNotifier;
use CSeidl\EmailComposer\Mail\MailableSender;
use CSeidl\EmailComposer\Notifications\NullImportNotifier;
use CSeidl\EmailComposer\Services\SendRateLimiter;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\RateLimiter;
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
            ->hasCommands([InstallCommand::class, ImportRecipientsCommand::class, QueueSendsCommand::class]);
    }

    #[Override]
    public function packageRegistered(): void
    {
        $this->app->bind(EmailSenderInterface::class, MailableSender::class);
        $this->app->bind(ImportNotifierInterface::class, class_exists('Filament\Notifications\Notification')
            ? FilamentImportNotifier::class
            : NullImportNotifier::class);
        $this->app->scoped(TemplateRegistry::class, fn () => new TemplateRegistry);

        parent::packageRegistered();
    }

    #[Override]
    public function packageBooted(): void
    {
        // One limiter holding every limit, read per send rather than at boot. An app may
        // register its own under the same name.
        RateLimiter::for(SendRateLimiter::NAME, function () {
            $limits = [];

            foreach (EmailComposer::config()->rateLimits() as $name => $limit) {
                $limits[] = new Limit($name, $limit['amount'], $limit['seconds']);
            }

            return $limits === [] ? Limit::none() : $limits;
        });

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->everyShortestWindow(
            $schedule->command(QueueSendsCommand::class),
        ));
    }

    /**
     * Queues sends as often as the shortest rate limit window allows them, using the longest
     * frequency the scheduler offers that fits. Every minute without a limit, to finish sends.
     */
    protected function everyShortestWindow(Event $event): Event
    {
        $window = min([60, ...array_column(EmailComposer::config()->rateLimits(), 'seconds')]);

        return match (true) {
            $window >= 60 => $event->everyMinute(),
            $window >= 30 => $event->everyThirtySeconds(),
            $window >= 20 => $event->everyTwentySeconds(),
            $window >= 15 => $event->everyFifteenSeconds(),
            $window >= 10 => $event->everyTenSeconds(),
            $window >= 5 => $event->everyFiveSeconds(),
            $window >= 2 => $event->everyTwoSeconds(),
            default => $event->everySecond(),
        };
    }
}
