<?php

namespace CSeidl\EmailComposer\Filament;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Filament\Pages\ManageEmailTheme;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Filament\Resources\EmailTemplates\EmailTemplateResource;
use CSeidl\EmailComposer\Filament\Resources\RecipientLists\RecipientListResource;
use CSeidl\EmailComposer\Filament\Resources\Recipients\RecipientResource;
use Filament\Contracts\Plugin;
use Filament\Panel;
use LaraZeus\SpatieTranslatable\SpatieTranslatablePlugin;
use LogicException;
use Override;

class EmailComposerPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    #[Override]
    public function getId(): string
    {
        return 'email-composer';
    }

    #[Override]
    public function register(Panel $panel): void
    {
        if (! class_exists(SpatieTranslatablePlugin::class)) {
            throw new LogicException('EmailComposerPlugin requires lara-zeus/spatie-translatable.');
        }

        if (! class_exists(LanguageSwitch::class)) {
            throw new LogicException('EmailComposerPlugin requires bezhansalleh/filament-language-switch.');
        }

        $panel
            ->resources([
                RecipientResource::class,
                RecipientListResource::class,
                EmailTemplateResource::class,
                EmailDraftResource::class,
            ])
            ->pages([
                ManageEmailTheme::class,
            ]);

        // A host that registered it first keeps its own configuration.
        if (! $panel->hasPlugin('spatie-translatable')) {
            $panel->plugin(
                SpatieTranslatablePlugin::make()->defaultLocales(EmailComposer::config()->locales()),
            );
        }
    }

    #[Override]
    public function boot(Panel $panel): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            if ($switch->getLocales() === []) {
                $switch->locales(EmailComposer::config()->locales());
            }
        });
    }
}
