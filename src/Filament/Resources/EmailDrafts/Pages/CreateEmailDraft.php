<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Pages;

use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\EmailDraftResource;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;
use Override;

class CreateEmailDraft extends CreateRecord
{
    use Translatable;

    protected static string $resource = EmailDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }

    // lara-zeus 2.0.1 passes the form data to the model constructor, where
    // spatie reads an associative value (placeholders) as a locale map.
    // This is its method with lara-zeus/spatie-translatable#64 applied.
    // Remove once a release includes it.
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $record = new EmailDraft;

        $translatableAttributes = static::getResource()::getTranslatableAttributes();

        $record->fill(Arr::except($data, $translatableAttributes));

        foreach (Arr::only($data, $translatableAttributes) as $key => $value) {
            $record->setTranslation($key, $this->activeLocale, $value);
        }

        foreach ($this->otherLocaleData as $locale => $localeData) {
            $localeData = $this->mutateFormDataBeforeCreate($localeData);

            foreach (Arr::only($localeData, $translatableAttributes) as $key => $value) {
                $record->setTranslation($key, $locale, $value);
            }
        }

        if ($parentRecord = $this->getParentRecord()) {
            return $this->associateRecordWithParent($record, $parentRecord);
        }

        $record->save();

        return $record;
    }
}
