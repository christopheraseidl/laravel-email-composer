<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

/**
 * Sends a cancelled draft to the recipients it did not reach, cancelled or failed, and not to
 * those already sent to. The draft is sent once they are.
 *
 * @final
 */
class ResumeSend
{
    /** @internal */
    public function __construct(protected RetryDeliveries $retryDeliveries) {}

    /**
     * @return int the recipients sent to
     *
     * @throws \DomainException when the draft is not cancelled or every recipient was sent to or skipped
     */
    public function __invoke(EmailDraft $draft): int
    {
        if ($draft->status !== EmailDraftStatus::Cancelled) {
            throw new \DomainException(__('email-composer::drafts.notifications.cannot_resume', [
                'id' => $draft->id,
                'status' => $draft->status->label(),
                'expected' => EmailDraftStatus::Cancelled->label(),
            ]));
        }

        $recipientIds = $draft->recipients()->newPivotQuery()
            ->whereIn('delivery_status', [EmailDeliveryStatus::Cancelled->value, EmailDeliveryStatus::Failed->value])
            ->pluck($draft->recipients()->getRelatedPivotKeyName())
            ->all();

        if ($recipientIds === []) {
            throw new \DomainException(__('email-composer::drafts.notifications.nothing_to_resume', ['id' => $draft->id]));
        }

        return ($this->retryDeliveries)($draft, $recipientIds);
    }
}
