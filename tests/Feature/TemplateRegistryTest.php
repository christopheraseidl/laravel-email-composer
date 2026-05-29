<?php

use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Templates\FileTemplate;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

pest()->use(RefreshDatabase::class);

/** @param  array<string, string>  $files  filename => contents */
function templateDir(array $files): string
{
    Storage::fake('local');

    foreach ($files as $name => $contents) {
        Storage::disk('local')->put("templates/{$name}", $contents);
    }

    return Storage::disk('local')->path('templates');
}

it('retrieves both file and database templates', function () {
    EmailTemplate::factory()->create(['key' => 'welcome']);
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>[[ body ]]</p>",
    ]);

    $registry = (new TemplateRegistry([$dir]))->all();

    expect($registry)->toHaveCount(2);
    expect($registry['welcome'])->toBeInstanceOf(EmailTemplate::class);
    expect($registry['default'])->toBeInstanceOf(FileTemplate::class);
});

it('discovers shipped templates via the default search paths', function () {
    $default = app(TemplateRegistry::class)->find('default');

    expect($default)->toBeInstanceOf(FileTemplate::class);
    expect($default->key)->toBe('default');
});

it('finds a template by key and returns null when not found', function () {
    EmailTemplate::factory()->create(['key' => 'welcome']);
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>[[ body ]]</p>",
    ]);

    $registry = new TemplateRegistry([$dir]);

    expect($registry->find('welcome')->key)->toBe('welcome');
    expect($registry->find('default')->key)->toBe('default');
    expect($registry->find('greeting'))->toBeNull();
});

it('parses front-matter, body, and css from a template file', function () {
    $css = '* {font-weight: bold;}';
    $body = '<p>Body.</p>';
    $html = "---\nkey: example\nname: Example\n---\n\n<style>{$css}</style>\n\n{$body}";
    $dir = templateDir(['example.html' => $html]);

    $fileTemplate = app(TemplateRegistry::class)->loadFile($dir.'/example.html');

    expect($fileTemplate)
        ->key->toBe('example')
        ->name->toBe('Example')
        ->body->toBe($body)
        ->css->toBe($css);
});

it('defaults css to null and placeholders to an empty array when absent', function () {
    $html = "---\nkey: minimal\nname: Minimal\n---\n\n<p>Body.</p>";
    $dir = templateDir(['minimal.html' => $html]);

    $fileTemplate = app(TemplateRegistry::class)->loadFile($dir.'/minimal.html');

    expect($fileTemplate->css)->toBeNull();
    expect($fileTemplate->placeholderNames())->toBe([]);
});

it('throws InvalidArgumentException on a file missing key or name', function () {
    $dir = templateDir([
        'no-name.html' => "---\nkey: example\n---\n\n<p>Body.</p>",
        'no-key.html' => "---\nname: Example\n---\n\n<p>Body.</p>",
    ]);

    $registry = app(TemplateRegistry::class);

    expect(fn () => $registry->loadFile($dir.'/no-name.html'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $registry->loadFile($dir.'/no-key.html'))->toThrow(InvalidArgumentException::class);
});

it('throws RuntimeException when two files declare the same key', function () {
    $dir = templateDir([
        'original.html' => "---\nkey: example\nname: Original example\n---\n\n<p>Original body.</p>",
        'dupe.html' => "---\nkey: example\nname: Duplicate example\n---\n\n<p>Duplicate body.</p>",
    ]);

    $registry = new TemplateRegistry([$dir]);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class);
});

it('tolerates a search path that does not exist', function () {
    $registry = new TemplateRegistry(['/no/such/template/dir']);

    expect($registry->all())->toHaveCount(0);
});

test('file template wins on a database/file collision', function () {
    EmailTemplate::factory()->create(['key' => 'default', 'body' => '<p>Database body.</p>']);
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>File body.</p>",
    ]);

    $templates = (new TemplateRegistry([$dir]))->all();

    expect($templates)->toHaveCount(1);
    expect($templates['default'])->toBeInstanceOf(FileTemplate::class);
    expect($templates['default']->body)->toBe('<p>File body.</p>');
});

test('placeholderNames is uniform across EmailTemplate and FileTemplate', function () {
    EmailTemplate::factory()->create(['key' => 'greeting-with-cta', 'placeholders' => ['name', 'body', 'cta']]);

    $html = "---\nkey: example\nname: Example\nplaceholders: [name, body]\n---\n\n<p>Hi, [[ name ]]! [[ body ]]</p>";
    $dir = templateDir(['example.html' => $html]);

    $templates = (new TemplateRegistry([$dir]))->all();

    expect($templates)->toHaveCount(2);
    expect($templates['greeting-with-cta']->placeholderNames())->toBe(['name', 'body', 'cta']);
    expect($templates['example']->placeholderNames())->toBe(['name', 'body']);
});
