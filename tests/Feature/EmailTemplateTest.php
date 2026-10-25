<?php

use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Database\QueryException;

it('persists key, name, body, and placeholders', function () {
    $template = EmailTemplate::create([
        'key' => 'greeting-and-body',
        'name' => 'Greeting + body',
        'body' => '<p>[[ greeting ]]</p><p>[[ body ]]</p>',
        'placeholders' => ['greeting', 'body'],
    ]);

    expect($template->fresh())
        ->key->toBe('greeting-and-body')
        ->body->toBe('<p>[[ greeting ]]</p><p>[[ body ]]</p>')
        ->placeholders->toBe(['greeting', 'body']);
});

it('enforces unique key', function () {
    EmailTemplate::create(['key' => 'x', 'name' => 'X', 'body' => '<p>[[ body ]]</p>', 'placeholders' => ['body']]);
    expect(fn () => EmailTemplate::create(['key' => 'x', 'name' => 'Y', 'body' => '<p>[[ body ]]</p>', 'placeholders' => ['body']]))
        ->toThrow(QueryException::class);
});

it('exposes declared placeholder names', function () {
    $template = EmailTemplate::factory()->create(['placeholders' => ['heading', 'body', 'footer']]);

    expect($template->placeholderNames())->toBe(['heading', 'body', 'footer']);
});

it('returns an empty placeholder list when none are declared', function () {
    $template = EmailTemplate::factory()->create(['placeholders' => null]);

    expect($template->placeholderNames())->toBe([]);
});
