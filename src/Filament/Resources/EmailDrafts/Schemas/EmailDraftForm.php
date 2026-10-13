<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Schemas;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class EmailDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('email_template_id')
                    ->required()
                    ->options(EmailTemplate::pluck('name', 'id'))
                    ->live(),
                TextInput::make('subject'),
                Section::make('Placeholders')
                    ->schema(function (Get $get) {
                        $templateId = $get('email_template_id');

                        if (! $templateId) {
                            return [
                                Text::make('Pick a template to see its placeholders.'),
                            ];
                        }

                        $template = EmailTemplate::find($templateId);

                        return collect($template->placeholderNames())->map(
                            fn ($name) => Textarea::make("placeholders.{$name}")
                                ->label(mb_strtoupper($name))
                        )->all();
                    }),
                Select::make('recipients')
                    ->multiple()
                    ->relationship('recipients', 'email')
                    ->preload(),
                Text::make(fn (?EmailDraft $record) => static::status($record)->label())
                    ->color(fn (?EmailDraft $record) => static::status($record)->color())
                    ->icon(fn (?EmailDraft $record) => match (static::status($record)) {
                        EmailDraftStatus::Draft => Heroicon::OutlinedPencil,
                        EmailDraftStatus::UnderReview => Heroicon::OutlinedEye,
                        EmailDraftStatus::Approved => Heroicon::OutlinedCheckCircle,
                        EmailDraftStatus::Sent => Heroicon::OutlinedPaperAirplane,
                    }),
            ])
            ->disabled(fn (?EmailDraft $record) => static::status($record) !== EmailDraftStatus::Draft);
    }

    // Status is not a form field; the create page has no record yet.
    protected static function status(?EmailDraft $record): EmailDraftStatus
    {
        return $record->status ?? EmailDraftStatus::Draft;
    }
}
