<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers;

use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('email-composer::drafts.deliveries.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof EmailDraft
            && in_array($ownerRecord->status, [
                EmailDraftStatus::Sending,
                EmailDraftStatus::Sent,
            ], true)
            && Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('email-composer::drafts.deliveries.label'))
            ->pluralModelLabel(__('email-composer::drafts.deliveries.plural_label'))
            ->columns([
                TextColumn::make('email')
                    ->label(__('email-composer::drafts.deliveries.columns.email')),
                TextColumn::make('pivot.locale')
                    ->label(__('email-composer::drafts.deliveries.columns.locale')),
                TextColumn::make('pivot.delivery_status')
                    ->label(__('email-composer::drafts.deliveries.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmailDeliveryStatus::from($state)->label())
                    ->color(fn (string $state) => EmailDeliveryStatus::from($state)->color()),
                TextColumn::make('pivot.attempts')
                    ->label(__('email-composer::drafts.deliveries.columns.attempts')),
                TextColumn::make('pivot.sent_at')
                    ->label(__('email-composer::drafts.deliveries.columns.sent_at'))
                    ->dateTime(),
                TextColumn::make('pivot.error')
                    ->label(__('email-composer::drafts.deliveries.columns.error'))
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('delivery_status')
                    ->label(__('email-composer::drafts.deliveries.filters.status'))
                    ->attribute('email_composer_draft_recipient.delivery_status')
                    ->options(collect(EmailDeliveryStatus::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),
            ])
            // A recipient deleted after the send still has a result.
            ->modifyQueryUsing(fn ($query) => $query->withTrashed());
    }
}
