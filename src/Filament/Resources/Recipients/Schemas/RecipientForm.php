<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients\Schemas;

use CSeidl\EmailComposer\EmailComposer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecipientForm
{
    public static function configure(Schema $schema): Schema
    {
        $locales = EmailComposer::config()->locales();

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
                    ->options(array_combine($locales, $locales)),
                Select::make('lists')
                    ->multiple()
                    ->relationship('lists', 'name')
                    ->preload(),
                KeyValue::make('metadata')
                    ->columnSpanFull(),
                DateTimePicker::make('unsubscribed_at'),
            ]);
    }
}
