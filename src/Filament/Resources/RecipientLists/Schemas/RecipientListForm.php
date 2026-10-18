<?php

namespace CSeidl\EmailComposer\Filament\Resources\RecipientLists\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RecipientListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->unique()
                    ->required(),
                Select::make('recipients')
                    ->multiple()
                    ->relationship('recipients', 'email')
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ]);
    }
}
