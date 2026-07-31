<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Schemas;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
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
                Text::make(fn (Get $get) => static::status($get)->label())
                    ->color(fn (Get $get) => static::status($get)->color())
                    ->icon(fn (Get $get) => match (static::status($get)) {
                        EmailDraftStatus::Draft => Heroicon::OutlinedPencil,
                        EmailDraftStatus::UnderReview => Heroicon::OutlinedEye,
                        EmailDraftStatus::Approved => Heroicon::OutlinedCheckCircle,
                        EmailDraftStatus::Sent => Heroicon::OutlinedPaperAirplane,
                    }),
            ])
            ->disabled(fn (Get $get) => static::status($get) !== EmailDraftStatus::Draft);
    }

    /**
     * Read the status out of the form state.
     *
     * The state is the backed value on an existing record and absent
     * altogether on the create page, so neither an enum comparison nor
     * EmailDraftStatus::from() is safe on the raw value.
     */
    protected static function status(Get $get): EmailDraftStatus
    {
        $status = $get('status');

        if ($status instanceof EmailDraftStatus) {
            return $status;
        }

        return EmailDraftStatus::tryFrom((string) $status) ?? EmailDraftStatus::Draft;
    }
}
