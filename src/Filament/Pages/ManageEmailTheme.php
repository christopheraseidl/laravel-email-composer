<?php

namespace CSeidl\EmailComposer\Filament\Pages;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTheme;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/** @property-read Schema $content */
class ManageEmailTheme extends Page
{
    public ?array $data = [];

    public EmailTheme $record;

    public function mount(): void
    {
        $this->record = EmailTheme::current();
        $this->content->fill($this->record->only('css'));
    }

    // Renders into the default page view's {{ $this->content }} — no blade file needed.
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('css')
                    ->label('CSS')
                    ->required()
                    ->autosize()
                    ->rows(20)
                    ->extraInputAttributes(['style' => 'font-family: monospace;'])
                    ->disabled(! EmailComposer::userCan(Filament::auth()->user(), 'update-theme', $this->record)),
            ])
            ->statePath('data')
            ->model($this->record);
    }

    public function save(): void
    {
        abort_unless(
            EmailComposer::userCan(Filament::auth()->user(), 'update-theme', $this->record),
            403,
        );

        $this->record->update($this->content->getState());

        Notification::make()->success()->title('Saved')->send();
    }

    public static function canAccess(): bool
    {
        return EmailComposer::userCan(Filament::auth()->user(), 'view-theme', EmailTheme::current());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save')->submit('save'),  // or ->action('save')
        ];
    }
}
