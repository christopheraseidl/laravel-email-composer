<?php

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use CSeidl\EmailComposer\Filament\EmailComposerPlugin;
use CSeidl\EmailComposer\Filament\Pages\ManageEmailTheme;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\EmailTemplateResource;
use CSeidl\EmailComposer\Filament\Resources\Recipients\RecipientResource;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

use function Pest\Livewire\livewire;

pest()->use(RefreshDatabase::class);

it('registers the resources and the theme page on the panel', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->hasPlugin('email-composer'))->toBeTrue()
        ->and($panel->getResources())->toContain(
            RecipientResource::class,
            EmailTemplateResource::class,
            EmailDraftResource::class,
        )
        ->and($panel->getPages())->toContain(ManageEmailTheme::class);
});

it('registers spatie-translatable with the package locales', function () {
    $plugin = Filament::getPanel('admin')->getPlugin('spatie-translatable');

    expect($plugin)->toBeInstanceOf(SpatieTranslatablePlugin::class)
        ->and($plugin->getDefaultLocales())->toBe(['en', 'es']);
});

it('keeps a spatie-translatable plugin the host registered first', function () {
    $hostPlugin = SpatieTranslatablePlugin::make()->defaultLocales(['de']);

    $panel = Panel::make()->id('host')
        ->plugin($hostPlugin)
        ->plugin(EmailComposerPlugin::make());

    expect($panel->getPlugin('spatie-translatable'))->toBe($hostPlugin);
});

it('configures the language switch with the package locales', function () {
    config()->set('email-composer.locales', ['en', 'es', 'de']);

    EmailComposerPlugin::make()->boot(Filament::getPanel('admin'));

    expect(LanguageSwitch::make()->getLocales())->toBe(['en', 'es', 'de']);
});

it('opens the draft form in the active locale', function () {
    app()->setLocale('es');

    livewire(CreateEmailDraft::class)
        ->assertSet('activeLocale', 'es');
});

it('falls back to the first package locale when the active one is not supported', function () {
    app()->setLocale('fr');

    livewire(CreateEmailDraft::class)
        ->assertSet('activeLocale', 'en');
});

it('keeps language switch locales the host configured', function () {
    LanguageSwitch::configureUsing(fn (LanguageSwitch $switch) => $switch->locales(['de']));

    EmailComposerPlugin::make()->boot(Filament::getPanel('admin'));

    expect(LanguageSwitch::make()->getLocales())->toBe(['de']);
});
