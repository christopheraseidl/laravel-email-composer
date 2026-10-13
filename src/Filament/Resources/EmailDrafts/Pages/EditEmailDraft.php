<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages;

use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
use Override;

class EditEmailDraft extends EditRecord
{
    use Translatable;

    protected static string $resource = EmailDraftResource::class;

    // Drafts that have left draft status open read-only, so reviewers can
    // reach the feedback relation manager. Saving still needs `update`.
    #[Override]
    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canView($this->getRecord()), 403);
    }

    #[Override]
    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    #[Override]
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->visible(fn () => static::getResource()::canEdit($this->getRecord()));
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            LocaleSwitcher::make(),
        ];
    }
}
