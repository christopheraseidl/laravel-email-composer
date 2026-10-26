<?php

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Pages\ManageEmailTheme;
use CSeidl\EmailComposer\Models\EmailTheme;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\FileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

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
        ->fillForm(['logo_url' => null, 'logo_path' => UploadedFile::fake()->image('logo.png')])
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
        ->fillForm(['logo_path' => null, 'logo_url' => 'https://example.com/logo.png'])
        ->call('save')
        ->assertHasNoErrors();

    expect(EmailTheme::current()->refresh())
        ->logo_path->toBeNull()
        ->logo_url->toBe('https://example.com/logo.png');
    Storage::disk('public')->assertMissing('email-composer/logos/old.png');
});

it('submits the form to save', function () {
    livewire(ManageEmailTheme::class)
        ->assertSeeHtml('wire:submit="save"');
});

it('submits the form from the save action', function () {
    livewire(ManageEmailTheme::class)
        ->assertActionExists(
            TestAction::make('save')->schemaComponent('form-actions', schema: 'content'),
            fn (Action $action): bool => $action->canSubmitForm() && $action->getFormToSubmit() === 'save',
        );
});

it('uploads the logo with the public disk visibility', function (?string $diskVisibility, string $expected) {
    config(['filesystems.disks.public.visibility' => $diskVisibility]);

    $upload = livewire(ManageEmailTheme::class)->instance()->form->getFlatFields()['logo_path'];

    expect($upload)->toBeInstanceOf(FileUpload::class)
        ->and($upload->getVisibility())->toBe($expected);
})->with([
    ['public', 'public'],
    [null, 'private'],
]);

it('restores the default css and refills the form', function () {
    EmailTheme::current()->update(['css' => 'body {color: red;}', 'logo_url' => 'https://example.com/logo.png']);

    livewire(ManageEmailTheme::class)
        ->assertSchemaStateSet(['css' => 'body {color: red;}'])
        ->callAction('restoreDefault')
        ->assertNotified(__('email-composer::theme.notifications.default_restored'))
        ->assertSchemaStateSet(['css' => EmailTheme::defaultCss(), 'logo_url' => 'https://example.com/logo.png']);

    expect(EmailTheme::current()->refresh())
        ->css->toBe(EmailTheme::defaultCss())
        ->logo_url->toBe('https://example.com/logo.png');
});

it('asks for confirmation before restoring the default css', function () {
    livewire(ManageEmailTheme::class)
        ->assertActionExists('restoreDefault', fn (Action $action): bool => $action->isConfirmationRequired());
});

it('refuses to restore the default css to a user who cannot update the theme', function () {
    EmailTheme::current()->update(['css' => 'body {color: red;}']);
    EmailComposer::resolveAbilityUsing(fn ($user, string $ability) => $ability !== 'update-theme');

    livewire(ManageEmailTheme::class)
        ->assertActionHidden('restoreDefault')
        // Bypass the test helper, which refuses a hidden action, as a crafted request would.
        ->call('mountAction', 'restoreDefault')
        ->call('callMountedAction');

    expect(EmailTheme::current()->refresh()->css)->toBe('body {color: red;}');
});
