<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

/**
 * Cancels every recipient not sent yet, and the draft. A job already queued finds its
 * row cancelled and sends nothing; one already sending finishes.
 */
class CancelSend
{
    /** @throws \DomainException when the draft is not sending */
    public function __invoke(EmailDraft $draft): void
    {
        if ($draft->status !== EmailDraftStatus::Sending) {
            throw new \DomainException(__('email-composer::drafts.notifications.cannot_cancel', [
                'id' => $draft->id,
                'status' => $draft->status->label(),
                'expected' => EmailDraftStatus::Sending->label(),
            ]));
        }

        $draft->recipients()->newPivotQuery()
            ->where('delivery_status', EmailDeliveryStatus::Pending->value)
            ->update(['delivery_status' => EmailDeliveryStatus::Cancelled->value]);
        $draft->markCancelled();
    }
}
