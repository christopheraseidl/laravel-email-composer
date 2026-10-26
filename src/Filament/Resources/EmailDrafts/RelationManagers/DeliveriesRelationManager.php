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

    protected static ?string $title = 'Deliveries';

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
            ->columns([
                TextColumn::make('email'),
                TextColumn::make('pivot.locale')
                    ->label('Locale'),
                TextColumn::make('pivot.delivery_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => EmailDeliveryStatus::from($state)->label())
                    ->color(fn (string $state) => EmailDeliveryStatus::from($state)->color()),
                TextColumn::make('pivot.attempts')
                    ->label('Attempts'),
                TextColumn::make('pivot.sent_at')
                    ->label('Sent at')
                    ->dateTime(),
                TextColumn::make('pivot.error')
                    ->label('Error')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('delivery_status')
                    ->label('Status')
                    ->attribute('email_composer_draft_recipient.delivery_status')
                    ->options(collect(EmailDeliveryStatus::cases())->mapWithKeys(
                        fn ($case) => [$case->value => $case->label()]
                    )),
            ])
            // A recipient deleted after the send still has a result.
            ->modifyQueryUsing(fn ($query) => $query->withTrashed());
    }
}
