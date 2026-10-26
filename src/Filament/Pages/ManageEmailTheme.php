<?php

namespace CSeidl\EmailComposer\Filament\Pages;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailTheme;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/** @property-read Schema $form */
class ManageEmailTheme extends Page
{
    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public EmailTheme $record;

    public function mount(): void
    {
        $this->record = EmailTheme::current();
        $this->form->fill($this->record->only(['css', 'logo_url', 'logo_path', 'logo_alt']));
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s']),
                        ])->key('form-actions'),
                    ]),
            ]);
    }

    public function form(Schema $schema): Schema
    {
        $readOnly = ! EmailComposer::userCan(Filament::auth()->user(), 'update-theme', $this->record);

        return $schema
            ->components([
                TextInput::make('logo_url')
                    ->label('Logo URL')
                    ->url()
                    ->helperText('An absolute URL. Email clients cannot load relative paths. Clear it to upload a file instead.')
                    ->live(onBlur: true)
                    ->hidden(fn (Get $get): bool => filled($get('logo_path')))
                    ->disabled($readOnly),
                FileUpload::make('logo_path')
                    ->label('Logo upload')
                    ->image()
                    ->disk(EmailComposer::config()->publicDisk())
                    ->directory(EmailComposer::config()->storagePath().'/logos')
                    ->visibility(EmailComposer::config()->publicDiskVisibility())
                    ->helperText('Remove the upload to enter a URL instead.')
                    ->live()
                    ->hidden(fn (Get $get): bool => filled($get('logo_url')))
                    ->disabled($readOnly),
                TextInput::make('logo_alt')
                    ->label('Logo alt text')
                    ->disabled($readOnly),
                Textarea::make('css')
                    ->label('CSS')
                    ->required()
                    ->autosize()
                    ->rows(20)
                    ->extraInputAttributes(['style' => 'font-family: monospace;'])
                    ->disabled($readOnly),
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

        $state = [...$this->form->getState()];
        $state['logo_url'] ??= null;
        $state['logo_path'] ??= null;

        $previousPath = $this->record->logo_path;
        $this->record->update($state);

        if ($previousPath && $previousPath !== $this->record->logo_path) {
            Storage::disk(EmailComposer::config()->publicDisk())->delete($previousPath);
        }

        Notification::make()->success()->title('Saved')->send();
    }

    public static function getNavigationGroup(): ?string
    {
        return EmailComposer::config()->navigationGroup();
    }

    public static function canAccess(): bool
    {
        return EmailComposer::userCan(Filament::auth()->user(), 'view-theme', EmailTheme::current());
    }
}
