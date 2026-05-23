<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecipientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique()
                    ->required(),
                Select::make('locale')
                    ->options(config('email-composer.locales')),
                Textarea::make('metadata')
                    ->columnSpanFull(),
                DateTimePicker::make('unsubscribed_at'),
            ]);
    }
}
