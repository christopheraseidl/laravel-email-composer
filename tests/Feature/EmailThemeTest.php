<?php

use CSeidl\EmailComposer\Models\EmailTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

it('creates the first row from the shipped default on first call to current', function () {
    $css = (string) file_get_contents(__DIR__.'/../../resources/dist/css/email-default.css');

    expect(EmailTheme::current()->css)->toBe($css);
});

it('reads the current row on each call to current', function () {
    EmailTheme::current();

    EmailTheme::query()->update(['css' => 'body {color: red;}']);

    expect(EmailTheme::current()->css)->toBe('body {color: red;}');
});

it('persists css after edits', function () {
    $theme = EmailTheme::current();

    $css = 'body {font-weight: bold;}';

    $theme->css = $css;
    $theme->save();

    expect(EmailTheme::current()->css)->toBe($css);
});
