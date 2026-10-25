<?php

use CSeidl\EmailComposer\EmailComposerServiceProvider;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

it('successfully boots the service provider', function () {
    expect(config('email-composer.default_locale'))->toBe('en');
});

it('publishes the bundled views to the folder the templates are read from', function () {
    $paths = ServiceProvider::pathsToPublish(EmailComposerServiceProvider::class, 'email-composer-views');

    expect(array_values($paths))->toBe([base_path('resources/views/vendor/email-composer')])
        ->and(config('email-composer.templates.path'))->toBe(resource_path('views/vendor/email-composer/templates'));
});

it('reads templates from the app folder over the bundled ones', function () {
    $dir = sys_get_temp_dir().'/email-composer-templates-'.(getenv('TEST_TOKEN') ?: '0');
    config()->set('email-composer.templates.path', $dir);

    try {
        File::ensureDirectoryExists($dir);
        File::put("{$dir}/default.html", "---\nkey: default\nname: Published default\n---\n\n<p>[[ body ]]</p>");

        expect((new TemplateRegistry)->all()->get('default')->name)->toBe('Published default');
    } finally {
        File::deleteDirectory($dir);
    }
});
