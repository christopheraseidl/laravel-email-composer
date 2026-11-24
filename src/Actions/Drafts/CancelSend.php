<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

/**
 * Cancels every recipient not sent yet, and the draft. A job already queued finds its
 * row cancelled and sends nothing; one already sending finishes.
 *
 * A cancelled retry of a sent draft instead fails its recipients again, so they can be
 * retried, and leaves the draft sent.
 */
class CancelSend
{
    public function __construct(
        protected MarkDraftSent $markDraftSent,
        protected MarkDraftCancelled $markDraftCancelled,
    ) {}

    /** @throws \DomainException when the draft is not sending */
    public function __invoke(EmailDraft $draft): void
    {
        // With the draft row locked, so a retry adds no rows while these are cancelled.
        $draft->getConnection()->transaction(function () use ($draft) {
            if ($draft->refreshForUpdate()->status !== EmailDraftStatus::Sending) {
                throw new \DomainException(__('email-composer::drafts.notifications.cannot_cancel', [
                    'id' => $draft->id,
                    'status' => $draft->status->label(),
                    'expected' => EmailDraftStatus::Sending->label(),
                ]));
            }

            $pending = $draft->recipients()->newPivotQuery()
                ->where('delivery_status', EmailDeliveryStatus::Pending->value);

            if ($draft->isRetryingSent()) {
                $pending->update([
                    'delivery_status' => EmailDeliveryStatus::Failed->value,
                    'error' => __('email-composer::drafts.deliveries.retry_cancelled'),
                    'updated_at' => now(),
                ]);
                ($this->markDraftSent)($draft);

                return;
            }

            $pending->update(['delivery_status' => EmailDeliveryStatus::Cancelled->value]);
            ($this->markDraftCancelled)($draft);
        });
    }
}
