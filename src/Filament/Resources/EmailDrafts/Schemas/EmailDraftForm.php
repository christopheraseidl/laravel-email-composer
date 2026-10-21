<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Schemas;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmailDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('template')
                    ->required()
                    ->live()
                    ->dehydrated(false)
                    ->options(fn () => [
                        'Database' => EmailTemplate::pluck('name', 'id')
                            ->mapWithKeys(fn ($name, $id) => ["db:{$id}" => $name])
                            ->all(),
                        'Files' => EmailComposer::templates()->fileTemplates()
                            ->mapWithKeys(fn ($template, $key) => ["file:{$key}" => $template->name])
                            ->all(),
                    ])
                    ->afterStateHydrated(fn (Select $component, Get $get) => $component->state(match (true) {
                        filled($get('email_template_id')) => "db:{$get('email_template_id')}",
                        filled($get('template_key')) => "file:{$get('template_key')}",
                        default => null,
                    }))
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        [$source, $value] = array_pad(explode(':', $state ?? '', 2), 2, null);

                        $set('email_template_id', $source === 'db' ? (int) $value : null);
                        $set('template_key', $source === 'file' ? $value : null);
                    }),
                Hidden::make('email_template_id'),
                Hidden::make('template_key'),
                TextInput::make('subject'),
                Section::make('Placeholders')
                    ->schema(function (Get $get) {
                        $templateId = $get('email_template_id');
                        $template = EmailComposer::templates()->resolve(
                            filled($templateId) ? (int) $templateId : null,
                            $get('template_key'),
                        );

                        if (! $template) {
                            return [
                                Text::make('Pick a template to see its placeholders.'),
                            ];
                        }

                        return collect($template->placeholderNames())->map(
                            fn ($name) => Textarea::make("placeholders.{$name}")
                                ->label(mb_strtoupper($name))
                        )->all();
                    }),
                Radio::make('audience')
                    ->options(collect(EmailAudience::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    ))
                    ->default(EmailAudience::All->value)
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        if (static::audience($get) !== EmailAudience::List) {
                            $set('recipient_list_id', null);
                        }
                    }),
                Select::make('recipient_list_id')
                    ->label('Recipient list')
                    ->relationship('recipientList', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => static::audience($get) === EmailAudience::List)
                    ->required(fn (Get $get) => static::audience($get) === EmailAudience::List)
                    ->dehydratedWhenHidden(),
                Select::make('recipients')
                    ->multiple()
                    ->relationship('recipients', 'email')
                    ->searchable(['email', 'name'])
                    ->visible(fn (Get $get) => static::audience($get) === EmailAudience::Selected),
                Text::make(fn (?EmailDraft $record) => static::status($record)->label())
                    ->color(fn (?EmailDraft $record) => static::status($record)->color())
                    ->icon(fn (?EmailDraft $record) => match (static::status($record)) {
                        EmailDraftStatus::Draft => Heroicon::OutlinedPencil,
                        EmailDraftStatus::UnderReview => Heroicon::OutlinedEye,
                        EmailDraftStatus::Approved => Heroicon::OutlinedCheckCircle,
                        EmailDraftStatus::Sending => Heroicon::OutlinedArrowPath,
                        EmailDraftStatus::Sent => Heroicon::OutlinedPaperAirplane,
                    }),
            ])
            ->disabled(fn (?EmailDraft $record) => static::status($record) !== EmailDraftStatus::Draft);
    }

    protected static function audience(Get $get): ?EmailAudience
    {
        $state = $get('audience');

        return $state instanceof EmailAudience ? $state : EmailAudience::tryFrom((string) $state);
    }

    protected static function status(?EmailDraft $record): EmailDraftStatus
    {
        return $record->status ?? EmailDraftStatus::Draft;
    }
}
