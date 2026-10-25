<?php

namespace CSeidl\EmailComposer\Tests;

use CSeidl\EmailComposer\Filament\EmailComposerPlugin;
use Filament\Panel;
use Filament\PanelProvider;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugin(EmailComposerPlugin::make());
    }
}
