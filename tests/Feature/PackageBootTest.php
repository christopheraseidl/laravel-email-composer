<?php

use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

pest()->use(RefreshDatabase::class);

it('successfully boots the service provider', function () {
    expect(config('email-composer.default_locale'))->toBe('en');
});

it('publishes the bundled views to an app folder that overrides them', function () {
    $path = config('email-composer.templates.path').'/default.html';

    try {
        $this->artisan('vendor:publish', ['--tag' => 'email-composer-views'])->assertSuccessful();

        expect(File::exists($path))->toBeTrue();

        File::put($path, "---\nkey: default\nname: Published default\n---\n\n<p>[[ body ]]</p>");

        expect((new TemplateRegistry)->all()->get('default')->name)->toBe('Published default');
    } finally {
        File::deleteDirectory(resource_path('views/vendor/email-composer'));
    }
});
