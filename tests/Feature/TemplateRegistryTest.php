<?php

use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Templates\FileTemplate;
use CSeidl\EmailComposer\Templates\TemplateRegistry;
use Illuminate\Support\Facades\Storage;

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

it('reads template files once per instance', function () {
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>[[ body ]]</p>",
    ]);
    $registry = new TemplateRegistry([$dir]);

    expect($registry->fileTemplates()->keys()->all())->toBe(['default']);

    Storage::disk('local')->put('templates/late.html', "---\nkey: late\nname: Late\n---\n\n<p>Late.</p>");

    expect($registry->fileTemplates()->keys()->all())->toBe(['default']);
    expect($registry->find('late'))->toBeNull();
});

it('rereads template files after the container clears scoped instances', function () {
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>[[ body ]]</p>",
    ]);
    config()->set('email-composer.templates.path', $dir);

    $first = app(TemplateRegistry::class);
    expect($first->find('late'))->toBeNull();

    Storage::disk('local')->put('templates/late.html', "---\nkey: late\nname: Late\n---\n\n<p>Late.</p>");

    // A queue worker does this before each job.
    app()->forgetScopedInstances();

    $second = app(TemplateRegistry::class);
    expect($second)->not->toBe($first);
    expect($second->find('late'))->toBeInstanceOf(FileTemplate::class);
});

it('finds a database template when no file has the key', function () {
    $template = EmailTemplate::factory()->create(['key' => 'welcome']);
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>[[ body ]]</p>",
    ]);

    $found = (new TemplateRegistry([$dir]))->find('welcome');

    expect($found)->toBeInstanceOf(EmailTemplate::class);
    expect($found->is($template))->toBeTrue();
});

it('resolves a template by id', function () {
    $template = EmailTemplate::factory()->create(['key' => 'welcome']);

    $found = (new TemplateRegistry([]))->resolve($template->id, null);

    expect($found)->toBeInstanceOf(EmailTemplate::class);
    expect($found->is($template))->toBeTrue();
});

it('resolves a template by key', function () {
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: Default\n---\n\n<p>[[ body ]]</p>",
    ]);

    $found = (new TemplateRegistry([$dir]))->resolve(null, 'default');

    expect($found)->toBeInstanceOf(FileTemplate::class);
    expect($found->key)->toBe('default');
});

it('resolves to null with neither an id nor a key', function () {
    EmailTemplate::factory()->create(['key' => 'welcome']);

    expect((new TemplateRegistry([]))->resolve(null, null))->toBeNull();
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

it('throws RuntimeException when two files in one folder share a key alongside another folder', function () {
    $dir = templateDir([
        'original.html' => "---\nkey: example\nname: Original example\n---\n\n<p>Original body.</p>",
        'dupe.html' => "---\nkey: example\nname: Duplicate example\n---\n\n<p>Duplicate body.</p>",
    ]);

    $registry = new TemplateRegistry([$dir, __DIR__.'/../../resources/views/templates']);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class);
});

it('lets an app template with key default replace the bundled one', function () {
    $dir = templateDir([
        'default.html' => "---\nkey: default\nname: App default\n---\n\n<p>App body.</p>",
    ]);

    $templates = (new TemplateRegistry([$dir, __DIR__.'/../../resources/views/templates']))->all();

    expect($templates)->toHaveCount(1);
    expect($templates['default']->name)->toBe('App default');
    expect($templates['default']->body)->toBe('<p>App body.</p>');
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
