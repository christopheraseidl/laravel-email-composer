<?php

namespace CSeidl\EmailComposer\Filament\Resources\Recipients\Actions;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Jobs\ImportRecipientsJob;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;

class ImportRecipientsAction
{
    public static function make(): Action
    {
        return Action::make('import')
            ->label('Import')
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->color('gray')
            ->authorize(fn () => Gate::allows('import', Recipient::class))
            ->schema([
                FileUpload::make('file')
                    ->label('CSV file')
                    ->helperText(fn () => sprintf(
                        'A header row with an email column, and optionally name, locale and any metadata columns. Up to %s KB.',
                        number_format(EmailComposer::config()->importMaxFileSize()),
                    ))
                    ->acceptedFileTypes(['text/csv', 'text/plain'])
                    ->maxSize(fn () => EmailComposer::config()->importMaxFileSize())
                    // Browsers on Windows with Excel installed report .csv as application/vnd.ms-excel.
                    ->mimeTypeMap(['csv' => 'text/csv'])
                    // Filament would default to its public disk; the file holds email addresses.
                    ->disk(EmailComposer::config()->storageDisk() ?? config('filesystems.default'))
                    ->directory(EmailComposer::config()->importsPath())
                    ->visibility('private')
                    ->required(),
                Select::make('list')
                    ->label('Add to list')
                    ->options(fn () => RecipientList::query()->orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
            ])
            ->action(function (array $data) {
                ImportRecipientsJob::dispatch(
                    auth()->user() ?? throw new AuthenticationException,
                    $data['file'],
                    filled($data['list'] ?? null) ? (int) $data['list'] : null,
                );

                Notification::make()
                    ->title('Import queued')
                    ->body('You will get a notification when it finishes.')
                    ->success()
                    ->send();
            });
    }
}
