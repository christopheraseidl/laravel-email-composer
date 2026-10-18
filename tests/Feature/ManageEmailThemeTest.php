<?php

use CSeidl\EmailComposer\Filament\Pages\ManageEmailTheme;
use CSeidl\EmailComposer\Models\EmailTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

pest()->use(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('public'));

it('saves the logo url and alt text', function () {
    livewire(ManageEmailTheme::class)
        ->set('data.logo_url', 'https://example.com/logo.png')
        ->set('data.logo_alt', 'Acme')
        ->call('save')
        ->assertHasNoErrors();

    expect(EmailTheme::current()->refresh())
        ->logo_url->toBe('https://example.com/logo.png')
        ->logo_alt->toBe('Acme');
});

it('rejects a logo url that is not a url', function () {
    livewire(ManageEmailTheme::class)
        ->set('data.logo_url', 'logo.png')
        ->call('save')
        ->assertHasErrors(['data.logo_url' => 'url']);

    expect(EmailTheme::current()->refresh()->logo_url)->toBeNull();
});

it('uploads a logo to the public disk and clears the logo url', function () {
    EmailTheme::current()->update(['logo_url' => 'https://example.com/old.png']);

    livewire(ManageEmailTheme::class)
        ->fillForm(['logo_url' => null, 'logo_path' => UploadedFile::fake()->image('logo.png')], 'content')
        ->call('save')
        ->assertHasNoErrors();

    $theme = EmailTheme::current()->refresh();

    expect($theme->logo_url)->toBeNull()
        ->and($theme->logo_path)->toStartWith('email-composer/logos/');
    Storage::disk('public')->assertExists($theme->logo_path);
});

it('deletes the uploaded logo when a url replaces it', function () {
    Storage::disk('public')->put('email-composer/logos/old.png', 'old');
    EmailTheme::current()->update(['logo_path' => 'email-composer/logos/old.png']);

    livewire(ManageEmailTheme::class)
        ->fillForm(['logo_path' => null, 'logo_url' => 'https://example.com/logo.png'], 'content')
        ->call('save')
        ->assertHasNoErrors();

    expect(EmailTheme::current()->refresh())
        ->logo_path->toBeNull()
        ->logo_url->toBe('https://example.com/logo.png');
    Storage::disk('public')->assertMissing('email-composer/logos/old.png');
});
