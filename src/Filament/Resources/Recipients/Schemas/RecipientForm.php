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
                    ->label(__('email-composer::recipients.fields.name'))
                    ->required(),
                TextInput::make('email')
                    ->label(__('email-composer::recipients.fields.email'))
                    ->email()
                    ->unique()
                    ->required(),
                Select::make('locale')
                    ->label(__('email-composer::recipients.fields.locale'))
                    ->options(array_combine($locales, $locales)),
                Select::make('lists')
                    ->label(__('email-composer::recipients.fields.lists'))
                    ->multiple()
                    ->relationship('lists', 'name')
                    ->preload(),
                KeyValue::make('metadata')
                    ->label(__('email-composer::recipients.fields.metadata')),
                DateTimePicker::make('unsubscribed_at')
                    ->label(__('email-composer::recipients.fields.unsubscribed_at')),
            ])
            ->columns(1);
    }
}
