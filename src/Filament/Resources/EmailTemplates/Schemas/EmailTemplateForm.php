<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailTemplates\Schemas;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $operation, ?string $old, ?string $state, ?Model $record) {
                        if ($operation == 'edit') {
                            return;
                        }

                        if (($get('key') ?? '') !== Str::slug($old)) {
                            return;
                        }

                        $set('key', Str::slug($state));
                    }),
                TextInput::make('key')
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                Textarea::make('body')
                    ->required()
                    ->rows(16)
                    ->helperText('Use {{ placeholder_name }} tokens where draft content should be inserted. Declare every placeholder you use in the Placeholders field below.'),
                TagsInput::make('placeholders')
                    ->placeholder('Add a placeholder name (e.g. greeting)')->helperText('The list of {{ placeholder_name }} tokens used in the body. The draft composer will render one input per placeholder, per locale.'),
            ]);
    }
}
