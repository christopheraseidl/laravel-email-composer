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
                Text::make(fn (Get $get) => $get('status'))
                    ->color(fn (Get $get) => EmailDraftStatus::from($get('status'))->color())
                    ->icon(function (Get $get) {
                        $status = $get('status');

                        return match ($status) {
                            EmailDraftStatus::Draft => Heroicon::OutlinedPencil,
                            EmailDraftStatus::UnderReview => Heroicon::OutlinedEye,
                            EmailDraftStatus::Approved => Heroicon::OutlinedCheckCircle,
                            EmailDraftStatus::Sent => Heroicon::OutlinedPaperAirplane,
                            default => throw new \InvalidArgumentException(
                                "The provided status '{$status}' is not an available option."
                            )
                        };
                    }),
            ])
            ->disabled(fn (Get $get) => EmailDraftStatus::from($get('status')) !== EmailDraftStatus::Draft);
    }
}
