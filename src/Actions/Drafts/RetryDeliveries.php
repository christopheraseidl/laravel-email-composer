<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

/**
 * Sends a draft again to the chosen recipients it did not reach: those that failed or were
 * cancelled. The draft is sending until QueueSends finds none pending. Failure rows stay.
 *
 * @final
 */
class RetryDeliveries
{
    /** @internal */
    public function __construct(protected QueueSends $queueSends) {}

    /**
     * @param  array<int, int|string>  $recipientIds  recipients in other statuses are left alone
     * @return int the recipients sent again
     *
     * @throws \DomainException when the draft is not sending, sent or cancelled, or none of the recipients failed or was cancelled
     */
    public function __invoke(EmailDraft $draft, array $recipientIds): int
    {
        // With the draft row locked, so QueueSends never marks it sent while the rows are reset.
        $reset = $draft->getConnection()->transaction(function () use ($draft, $recipientIds) {
            if (! $draft->refreshForUpdate()->status->hasStartedSending()) {
                throw new \DomainException(__('email-composer::drafts.notifications.cannot_retry', [
                    'id' => $draft->id,
                    'status' => $draft->status->label(),
                ]));
            }

            $reset = $draft->recipients()->newPivotQuery()
                ->whereIn($draft->recipients()->getRelatedPivotKeyName(), $recipientIds)
                ->whereIn('delivery_status', [EmailDeliveryStatus::Failed->value, EmailDeliveryStatus::Cancelled->value])
                ->update([
                    'delivery_status' => EmailDeliveryStatus::Pending->value,
                    'error' => null,
                    'sent_at' => null,
                    'attempts' => 0,
                    // A failed job retried by hand would otherwise still match its row.
                    'queued_at' => null,
                    'updated_at' => now(),
                ]);

            if ($reset === 0) {
                throw new \DomainException(__('email-composer::drafts.notifications.nothing_to_retry', ['id' => $draft->id]));
            }

            if ($draft->status !== EmailDraftStatus::Sending) {
                $draft->update(['status' => EmailDraftStatus::Sending]);
            }

            return $reset;
        });

        ($this->queueSends)($draft);

        return $reset;
    }
}
