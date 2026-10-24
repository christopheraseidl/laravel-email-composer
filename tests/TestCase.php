<?php

namespace CSeidl\EmailComposer\Tests;

use BezhanSalleh\LanguageSwitch\LanguageSwitchServiceProvider;
use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\EmailComposerServiceProvider;
use CSeidl\EmailComposer\Tests\Fixtures\User;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use LaraZeus\SpatieTranslatable\SpatieTranslatableServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'CSeidl\\EmailComposer\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->loadLaravelMigrations();

        foreach (EmailComposerServiceProvider::MIGRATIONS as $migration) {
            (include __DIR__."/../database/migrations/{$migration}.php.stub")->up();
        }

        // Tests that check denial flush this or register a narrower resolver.
        EmailComposer::resolveAbilityUsing(fn () => true);

        $this->actingAs(User::factory()->create());
    }

    protected function getPackageProviders($app)
    {
        return [
            EmailComposerServiceProvider::class,
            FilamentServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            LivewireServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            LanguageSwitchServiceProvider::class,
            SpatieTranslatableServiceProvider::class,
            TestPanelProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $dir = sys_get_temp_dir().'/email-composer-tests-'.(getenv('TEST_TOKEN') ?: '0');
        $app->useDatabasePath($dir);
        $app->useConfigPath($dir.'/config');
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
        config()->set('auth.providers.users.model', User::class);
        config()->set('email-composer.locales', ['en', 'es']);
        config()->set('email-composer.storage.disk', 'local');
        config()->set('queue.default', 'sync');
        config()->set('queue.batching.database', 'testing');
    }
}
