<?php

use CSeidl\EmailComposer\Mail\ComposedEmail;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Models\EmailTheme;
use CSeidl\EmailComposer\Templates\FileTemplate;
use CSeidl\EmailComposer\Templates\TemplateRenderer;
use Illuminate\Support\Facades\Storage;

/**
 * Create a database-backed template, overriding any of the defaults.
 *
 * @param  array<string, mixed>  $attributes
 */
function template(array $attributes = []): EmailTemplate
{
    return EmailTemplate::create([
        'key' => 'greeting-and-body',
        'name' => 'Greeting + body',
        'body' => '<div>[[ greeting ]]</div><div>[[ body ]]</div>',
        'placeholders' => ['greeting', 'body'],
        ...$attributes,
    ]);
}

/**
 * Render a template through a fresh renderer in the default locale.
 *
 * @param  array<string, string>  $values
 */
function renderTemplate(EmailTemplate|FileTemplate $template, array $values = []): string
{
    return (new TemplateRenderer)->render(
        $template,
        $values,
        config('email-composer.default_locale', 'en'),
    );
}

it('interpolates tokens and preserves rich-text HTML', function () {
    $html = renderTemplate(template(), [
        'greeting' => '<h1>Greetings</h1>',
        'body' => '<p>Hello world!</p>',
    ]);

    // The CSS inliner adds style attributes, so assert on tag + content rather
    // than an exact match. The point is the markup is preserved, not escaped.
    expect($html)
        ->not->toContain('[[ greeting ]]')
        ->toContain('<h1')->toContain('Greetings</h1>')
        ->toContain('Hello world!');
});

it('strips XSS smuggled through an injected value', function () {
    $html = renderTemplate(template(), [
        'greeting' => '<h1>Hi</h1><script>alert(1)</script>',
        'body' => '<img src=x onerror="alert(2)">',
    ]);

    expect($html)
        ->toContain('Hi')
        ->not->toContain('<script')
        ->not->toContain('alert(1)')
        ->not->toContain('onerror');
});

it('leaves nothing behind for a missing value', function () {
    $html = renderTemplate(template(), ['greeting' => 'Hi']);

    expect($html)
        ->not->toContain('[[')
        ->not->toContain(']]')
        ->toMatch('/<div[^>]*><\/div>/');
});

it('inlines CSS into style attributes', function () {
    $html = renderTemplate(template(), [
        'greeting' => '<h1>Greetings</h1>',
        'body' => '<p>Hello world!</p>',
    ]);

    expect($html)->toContain('style="');
});

it('lets template CSS override the theme', function () {
    // 12px replaces the theme default of 16px.
    $html = renderTemplate(
        template(['css' => 'body, td, p, div {font-size: 12px;}']),
        ['greeting' => '<h1>Greetings</h1>', 'body' => '<p>Hello world!</p>'],
    );

    // Assert on inlined style attributes; the <style> block keeps both rules.
    expect($html)
        ->not->toMatch('/style="[^"]*font-size: 16px;/')
        ->toMatch('/<div style="[^"]*font-size: 12px;/')
        // A theme rule the template does not touch still applies.
        ->toContain('#3f3f46');
});

it('sanitizes a script payload smuggled through CSS', function () {
    $html = renderTemplate(
        template(['css' => 'body {font-size: 12px;}</style><script>malicious.js</script>']),
        ['greeting' => '<h1>Greetings</h1>', 'body' => '<p>Hello world!</p>'],
    );

    // The payload stays inside the <style> block as inert text.
    expect($html)
        ->not->toContain('<script')
        ->not->toMatch('/<\/style>.*<\/style>/s');
});

it('keeps the default theme media queries in the head', function () {
    expect(renderTemplate(template()))
        ->toContain('<html lang="en"')
        ->toMatch('/<head>.*<style>.*@media.*<\/style>.*<\/head>/s');
});

it('declares light and dark support in the head', function () {
    expect(renderTemplate(template()))
        ->toContain('<meta name="color-scheme" content="light dark">')
        ->toContain('<meta name="supported-color-schemes" content="light dark">');
});

it('wraps the body in the email layout tables', function () {
    $html = renderTemplate(template(), ['greeting' => 'Hi']);

    expect($html)
        ->toMatch('/<body class="email-body"/')
        ->toMatch('/<table role="presentation" class="email-wrapper" width="100%"/')
        ->toMatch('/<!--\[if mso\]>\s*<table role="presentation" width="600"/')
        ->toMatch('/<table role="presentation" class="email-container"/')
        ->toMatch('/<td class="email-content"[^>]*>\s*<div[^>]*>Hi<\/div>/')
        ->toMatch('/<td class="email-footer"[^>]*>'.preg_quote(ComposedEmail::FOOTER_MARKER, '/').'<\/td>/');
});

it('ships dark mode rules for every client in the default theme', function () {
    $css = EmailTheme::defaultCss();

    expect($css)
        ->toContain(':root {')
        ->toContain('color-scheme: light dark;')
        ->toContain('@media (prefers-color-scheme: dark)')
        ->toContain('[data-ogsc] h1')
        ->toContain('[data-ogsb] .email-container')
        // Many clients drop CSS variables.
        ->not->toContain('var(');
});

it('keeps the dark mode rules out of the inlined styles', function () {
    expect(renderTemplate(template()))
        ->toMatch('/<style>.*#09090b.*<\/style>/s')
        ->not->toMatch('/style="[^"]*#09090b/');
});

it('renders a FileTemplate, not just a database template', function () {
    $template = new FileTemplate(
        key: 'file-template',
        name: 'File template',
        body: '<p>Hello, [[ name ]]!</p>',
        placeholders: ['name'],
        css: null,
        sourcePath: '/tmp/template.html',
    );

    expect(renderTemplate($template, ['name' => 'John']))->toContain('Hello, John!');
});

it('renders the theme logo when set', function () {
    EmailTheme::current()->update(['logo_url' => 'https://example.com/logo.png', 'logo_alt' => 'Acme']);

    expect(renderTemplate(template()))
        ->toMatch('/<td class="email-header" align="center"[^>]*>\s*<img src="https:\/\/example.com\/logo.png" alt="Acme" width="160"/');
});

it('renders no logo when the theme has none', function () {
    expect(renderTemplate(template()))
        ->not->toContain('<img')
        ->not->toContain('class="email-header"');
});

it('falls back to the app name for the logo alt text', function () {
    config(['app.name' => 'Acme App']);
    EmailTheme::current()->update(['logo_url' => 'https://example.com/logo.png']);

    expect(renderTemplate(template()))->toContain('alt="Acme App"');
});

it('renders an uploaded logo from the public disk', function () {
    EmailTheme::current()->update(['logo_path' => 'email-composer/logos/logo.png']);

    expect(renderTemplate(template()))
        ->toContain('src="'.Storage::disk('public')->url('email-composer/logos/logo.png').'"');
});
