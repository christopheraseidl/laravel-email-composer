<?php

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use CSeidl\EmailComposer\Services\DraftPublisher;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake(config('email-composer.storage.disk')));

/** @param array<string, mixed> $attributes */
function publishableDraft(array $attributes = []): EmailDraft
{
    $template = EmailTemplate::factory()->create([
        'body' => '<div>[[ body ]]</div>',
        'placeholders' => ['body'],
    ]);

    return EmailDraft::factory()->create([
        'email_template_id' => $template->id,
        ...$attributes,
    ]);
}

function expectedFolder(EmailDraft $draft): string
{
    return hash_hmac('sha256', (string) $draft->getKey(), (string) config('app.key'));
}

it('writes one file per content locale plus the default', function () {
    // es only in subject, fr only in placeholders, en only as the default.
    $draft = publishableDraft([
        'subject' => ['es' => 'Bienvenido'],
        'placeholders' => ['fr' => ['body' => 'Corps français.']],
    ]);

    $paths = app(DraftPublisher::class)->publish($draft);

    $base = 'email-composer/drafts/'.expectedFolder($draft);
    expect($paths)->toBe([
        'es' => "{$base}/es.txt",
        'fr' => "{$base}/fr.txt",
        'en' => "{$base}/en.txt",
    ]);

    $disk = Storage::disk(config('email-composer.storage.disk'));
    foreach ($paths as $path) {
        $disk->assertExists($path);
    }
    expect($disk->allFiles($base))->toHaveCount(3);
});

it('writes the files private', function () {
    $draft = publishableDraft();

    $path = app(DraftPublisher::class)->publish($draft)['en'];

    expect(Storage::disk(config('email-composer.storage.disk'))->getVisibility($path))->toBe('private');
});

it('saves the locale to path map on the draft', function () {
    $draft = publishableDraft();

    $paths = app(DraftPublisher::class)->publish($draft);

    expect($draft->fresh()->public_files)->toBe($paths);
});

it('stores the rendered body for each locale', function () {
    $draft = publishableDraft([
        'subject' => ['en' => 'Welcome', 'es' => 'Bienvenido'],
        'placeholders' => [
            'en' => ['body' => 'English body.'],
            'es' => ['body' => 'Cuerpo español.'],
        ],
    ]);

    $paths = app(DraftPublisher::class)->publish($draft);
    $disk = Storage::disk(config('email-composer.storage.disk'));

    expect($disk->get($paths['en']))
        ->toBe($draft->renderFor('en'))
        ->toContain('English body.')
        ->not->toContain('Cuerpo español.');

    expect($disk->get($paths['es']))
        ->toBe($draft->renderFor('es'))
        ->toContain('Cuerpo español.')
        ->not->toContain('English body.');
});

it('trims slashes from the configured storage path', function () {
    config()->set('email-composer.storage.path', '/custom/root/');
    $draft = publishableDraft(['subject' => ['en' => 'Welcome'], 'placeholders' => []]);

    $paths = app(DraftPublisher::class)->publish($draft);

    expect($paths)->toBe(['en' => 'custom/root/drafts/'.expectedFolder($draft).'/en.txt']);
});

it('stores each draft under a folder that does not reveal its id', function () {
    $first = publishableDraft();
    $second = publishableDraft();

    $publisher = app(DraftPublisher::class);
    $firstFolder = dirname($publisher->publish($first)['en']);
    $secondFolder = dirname($publisher->publish($second)['en']);

    expect(basename($firstFolder))
        ->toMatch('/^[0-9a-f]{64}$/')
        ->not->toBe((string) $first->id);
    expect($firstFolder)->not->toBe($secondFolder);
});

it('overwrites the same files when a draft is published again', function () {
    $draft = publishableDraft();
    $publisher = app(DraftPublisher::class);

    $paths = $publisher->publish($draft);

    expect($publisher->publish($draft))->toBe($paths);
    expect(Storage::disk(config('email-composer.storage.disk'))->allFiles('email-composer/drafts'))
        ->toHaveCount(count($paths));
});

it('deletes the published files and clears them from the draft', function () {
    $draft = publishableDraft(['subject' => ['en' => 'Welcome', 'es' => 'Bienvenido']]);
    $other = publishableDraft();
    $publisher = app(DraftPublisher::class);

    $paths = $publisher->publish($draft);
    $otherPaths = $publisher->publish($other);

    $publisher->unpublish($draft);

    $disk = Storage::disk(config('email-composer.storage.disk'));
    foreach ($paths as $path) {
        $disk->assertMissing($path);
    }
    foreach ($otherPaths as $path) {
        $disk->assertExists($path);
    }
    expect($draft->fresh()->public_files)->toBeNull();
});

it('deletes files from locales the draft no longer has', function () {
    $draft = publishableDraft(['subject' => ['en' => 'Welcome', 'es' => 'Bienvenido']]);
    $publisher = app(DraftPublisher::class);
    $publisher->publish($draft);

    $draft->forceFill(['public_files' => ['en' => 'email-composer/drafts/'.expectedFolder($draft).'/en.txt']])->save();

    $publisher->unpublish($draft);

    expect(Storage::disk(config('email-composer.storage.disk'))->allFiles('email-composer/drafts'))->toBeEmpty();
});

it('unpublishes a draft that was never published', function () {
    $draft = publishableDraft();

    app(DraftPublisher::class)->unpublish($draft);

    expect($draft->fresh()->public_files)->toBeNull();
});
