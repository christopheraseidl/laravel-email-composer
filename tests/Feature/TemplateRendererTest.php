<?php

use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Templates\FileTemplate;
use CSeidl\EmailComposer\Templates\TemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

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
    // than an exact match — the point is the markup is preserved, not escaped.
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
        ->toContain('Hi')   // legitimate content survives
        ->not->toContain('<script')
        ->not->toContain('alert(1)')
        ->not->toContain('onerror');
});

it('leaves nothing behind for a missing value', function () {
    $html = renderTemplate(template(), ['greeting' => 'Hi']);

    expect($html)
        ->not->toContain('[[')
        ->not->toContain(']]')
        ->not->toContain('body');
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

    expect($html)
        ->not->toContain('font-size:16px;')
        ->toContain('font-size:12px;')
        // A theme rule the template does not touch still applies.
        ->toContain('#2b2b2b');
});

it('sanitizes a script payload smuggled through CSS', function () {
    $html = renderTemplate(
        template(['css' => 'body {font-size: 12px;}</style><script>malicious.js</script>']),
        ['greeting' => '<h1>Greetings</h1>', 'body' => '<p>Hello world!</p>'],
    );

    expect($html)->not->toContain('malicious.js');
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
