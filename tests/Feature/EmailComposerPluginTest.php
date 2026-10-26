<?php

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use CSeidl\EmailComposer\Filament\EmailComposerPlugin;
use CSeidl\EmailComposer\Filament\Pages\ManageEmailTheme;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages\CreateEmailDraft;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\EmailTemplateResource;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\RecipientListResource;
use CSeidl\EmailComposer\Filament\Resources\Recipients\RecipientResource;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;

use function Pest\Livewire\livewire;

dataset('navigation items', [
    'drafts' => [EmailDraftResource::class, Heroicon::OutlinedEnvelope, 1],
    'templates' => [EmailTemplateResource::class, Heroicon::OutlinedDocumentText, 2],
    'recipients' => [RecipientResource::class, Heroicon::OutlinedUser, 3],
    'recipient lists' => [RecipientListResource::class, Heroicon::OutlinedUserGroup, 4],
    'theme' => [ManageEmailTheme::class, Heroicon::OutlinedSwatch, 5],
]);


it('registers the resources and the theme page on the panel', function () {
    $panel = Filament::getPanel('admin');

    expect($panel->hasPlugin('email-composer'))->toBeTrue()
        ->and($panel->getResources())->toContain(
            RecipientResource::class,
            RecipientListResource::class,
            EmailTemplateResource::class,
            EmailDraftResource::class,
        )
        ->and($panel->getPages())->toContain(ManageEmailTheme::class);
});

it('puts each item in the configured navigation group without an icon', function (string $class) {
    config()->set('email-composer.filament.navigation_group', 'Mail');

    expect($class::getNavigationGroup())->toBe('Mail')
        ->and($class::getNavigationIcon())->toBeNull();
})->with('navigation items');

it('gives each item its own icon when there is no navigation group', function (string $class, Heroicon $icon) {
    config()->set('email-composer.filament.navigation_group', null);

    expect($class::getNavigationGroup())->toBeNull()
        ->and($class::getNavigationIcon())->toBe($icon);
})->with('navigation items');

it('orders the navigation items', function (string $class, Heroicon $icon, int $sort) {
    expect($class::getNavigationSort())->toBe($sort);
})->with('navigation items');

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
