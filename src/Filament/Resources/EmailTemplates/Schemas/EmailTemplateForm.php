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
                    ->label(__('email-composer::templates.fields.name'))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $operation, ?string $old, ?string $state, ?Model $record) {
                        if ($operation == 'edit') {
                            return;
                        }

                        if (($get('key') ?? '') !== Str::slug($old ?? '')) {
                            return;
                        }

                        $set('key', Str::slug($state ?? ''));
                    }),
                TextInput::make('key')
                    ->label(__('email-composer::templates.fields.key'))
                    ->required()
                    ->maxLength(255)
                    ->unique(),
                Textarea::make('body')
                    ->label(__('email-composer::templates.fields.body'))
                    ->required()
                    ->autosize()
                    ->helperText(__('email-composer::templates.helpers.body')),
                TagsInput::make('placeholders')
                    ->label(__('email-composer::templates.fields.placeholders'))
                    ->placeholder(__('email-composer::templates.helpers.placeholders_example'))
                    ->helperText(__('email-composer::templates.helpers.placeholders')),
                Textarea::make('css')
                    ->label(__('email-composer::templates.fields.css'))
                    ->autosize(),
            ]);
    }
}
