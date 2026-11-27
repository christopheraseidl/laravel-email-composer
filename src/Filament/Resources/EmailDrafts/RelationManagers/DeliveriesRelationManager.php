<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\RelationManagers;

use CSeidl\EmailComposer\Actions\Drafts\RetryDeliveries;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions\NotifiesDomainErrors;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\SendFailure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * @internal
 */
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
                EmailDraftStatus::Cancelled,
            ], true)
            && Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('email-composer::drafts.deliveries.label'))
            ->pluralModelLabel(__('email-composer::drafts.deliveries.plural_label'))
            ->description(fn () => $this->summary())
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
                TextColumn::make('failed_attempts')
                    ->label(__('email-composer::drafts.deliveries.columns.failed_attempts')),
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
            ->recordActions([
                Action::make('failures')
                    ->label(__('email-composer::drafts.deliveries.actions.failures'))
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('gray')
                    ->visible(fn (Recipient $record) => $record->getAttribute('failed_attempts') > 0)
                    ->modalHeading(fn (Recipient $record) => __('email-composer::drafts.deliveries.failures.heading', ['email' => $record->email]))
                    ->modalContent(fn (Recipient $record) => $this->failures($record))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('email-composer::drafts.actions.close')),
                Action::make('retry')
                    ->label(__('email-composer::drafts.deliveries.actions.retry'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Recipient $record) => __('email-composer::drafts.deliveries.retry_heading', ['email' => $record->email]))
                    ->modalDescription(fn (Recipient $record) => __('email-composer::drafts.confirmations.retry_one', ['email' => $record->email]))
                    ->visible(fn (Recipient $record) => $this->failed($record))
                    ->authorize(fn () => Gate::allows('retry', $this->getOwnerRecord()))
                    ->action(fn (Recipient $record) => $this->retry([$record->getKey()], $record->email)),
            ])
            ->headerActions([
                Action::make('retry_failed')
                    ->label(fn () => __('email-composer::drafts.deliveries.actions.retry_failed', ['count' => $this->failedQuery()->count()]))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription(fn () => trans_choice('email-composer::drafts.confirmations.retry_failed', $this->failedQuery()->count()))
                    ->visible(fn () => $this->failedQuery()->exists())
                    ->authorize(fn () => Gate::allows('retry', $this->getOwnerRecord()))
                    ->action(fn () => $this->retry($this->failedQuery()->pluck($this->draft()->recipients()->getRelatedPivotKeyName())->all())),
            ])
            ->toolbarActions([
                BulkAction::make('retry_selected')
                    ->label(__('email-composer::drafts.deliveries.actions.retry_selected'))
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Collection $records) => trans_choice('email-composer::drafts.confirmations.retry_selected', $records->count()))
                    ->authorize(fn () => Gate::allows('retry', $this->getOwnerRecord()))
                    ->action(fn (Collection $records) => $this->retry($records->modelKeys()))
                    ->deselectRecordsAfterCompletion(),
            ])
            // Retry selected is the only bulk action, and only a failed row can be retried.
            ->checkIfRecordIsSelectableUsing(fn (Recipient $record) => $this->failed($record))
            // A recipient deleted after the send still has a result.
            ->modifyQueryUsing(fn ($query) => $query->withTrashed()->addSelect([
                'failed_attempts' => SendFailure::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('recipient_id', (new Recipient)->getQualifiedKeyName())
                    ->where('email_draft_id', $this->getOwnerRecord()->getKey()),
            ]));
    }

    protected function draft(): EmailDraft
    {
        $draft = $this->getOwnerRecord();

        if (! $draft instanceof EmailDraft) {
            throw new \LogicException('Deliveries belong to an email draft.');
        }

        return $draft;
    }

    protected function failed(Recipient $recipient): bool
    {
        // Filament checks selectability on some rows without hydrating the pivot, which leaves the alias column.
        $status = $recipient->relationLoaded('pivot')
            ? $recipient->getRelation('pivot')->getAttribute('delivery_status')
            : $recipient->getAttribute('pivot_delivery_status');

        return $status === EmailDeliveryStatus::Failed->value;
    }

    protected function failedQuery(): Builder
    {
        return $this->draft()->recipients()->newPivotQuery()->where('delivery_status', EmailDeliveryStatus::Failed->value);
    }

    /**
     * @param  array<int, int|string>  $recipientIds
     * @param  string|null  $email  the one recipient's, to name in the notification
     */
    protected function retry(array $recipientIds, ?string $email = null): void
    {
        NotifiesDomainErrors::run(
            fn () => app(RetryDeliveries::class)($this->draft(), $recipientIds),
            __('email-composer::drafts.notifications.not_retried'),
            fn (int $count) => Notification::make()
                ->success()
                ->title(__('email-composer::drafts.notifications.retried'))
                ->body($email === null
                    ? trans_choice('email-composer::drafts.notifications.retried_many', $count)
                    : __('email-composer::drafts.notifications.retried_one', ['email' => $email]))
                ->send(),
        );
    }

    protected function failures(Recipient $recipient): View
    {
        return view('email-composer::filament.send-failures', [
            'failures' => SendFailure::query()
                ->where('email_draft_id', $this->getOwnerRecord()->getKey())
                ->where('recipient_id', $recipient->getKey())
                ->latest('failed_at')
                ->latest('id')
                ->get(),
        ]);
    }

    /** The count per delivery status, the failed attempts, and while sending, what is queued. */
    protected function summary(): string
    {
        $draft = $this->draft();
        $counts = $draft->recipients()->newPivotQuery()
            ->groupBy('delivery_status')
            ->selectRaw('delivery_status, count(*) as aggregate')
            ->pluck('aggregate', 'delivery_status');

        $parts = array_map(
            fn (EmailDeliveryStatus $status) => __('email-composer::drafts.deliveries.summary.count', [
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ]),
            EmailDeliveryStatus::cases(),
        );
        $parts[] = __('email-composer::drafts.deliveries.summary.failed_attempts', ['count' => $draft->sendFailures()->count()]);

        if ($draft->status === EmailDraftStatus::Sending) {
            $queued = $draft->recipients()->newPivotQuery()
                ->where('delivery_status', EmailDeliveryStatus::Pending->value)
                ->whereNotNull('queued_at')
                ->count();
            $parts[] = __('email-composer::drafts.deliveries.summary.queued', ['count' => $queued]);
            $parts[] = __('email-composer::drafts.deliveries.summary.waiting', [
                'count' => max(0, (int) ($counts[EmailDeliveryStatus::Pending->value] ?? 0) - $queued),
            ]);
        }

        return implode(' · ', $parts);
    }
}
