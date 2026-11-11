<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\Actions\Drafts\QueueSends;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Support\Collection;

class DraftDispatcher
{
    public function __construct(protected QueueSends $queueSends) {}

    /**
     * Mark the draft sending and queue its first sends. QueueSends marks it sent once every
     * recipient has a result.
     *
     * @throws \DomainException when the draft is not approved, its audience is empty or its list is gone, or another dispatch claimed it
     */
    public function dispatch(EmailDraft $draft): void
    {
        if ($draft->status !== EmailDraftStatus::Approved) {
            throw new \DomainException(__('email-composer::drafts.notifications.cannot_send', [
                'id' => $draft->id,
                'status' => $draft->status->label(),
                'expected' => EmailDraftStatus::Approved->label(),
            ]));
        }

        $recipientIds = $this->audienceIds($draft);

        if ($recipientIds->isEmpty()) {
            throw new \DomainException(__('email-composer::drafts.notifications.no_recipients', ['id' => $draft->id]));
        }

        $claimed = EmailDraft::whereKey($draft->getKey())
            ->where('status', EmailDraftStatus::Approved)
            ->update(['status' => EmailDraftStatus::Sending]);

        if ($claimed === 0) {
            throw new \DomainException(__('email-composer::drafts.notifications.already_sending', ['id' => $draft->id]));
        }

        $draft->status = EmailDraftStatus::Sending;
        $draft->syncOriginalAttribute('status');
        $draft->recipients()->sync($recipientIds);
        // A draft resent after a cancel goes to its whole audience again.
        $draft->recipients()->newPivotQuery()->update([
            'delivery_status' => EmailDeliveryStatus::Pending->value,
            'locale' => null,
            'sent_at' => null,
            'error' => null,
            'attempts' => 0,
            'queued_at' => null,
        ]);
        ($this->queueSends)($draft);
    }

    /** @return Collection<int, int> */
    protected function audienceIds(EmailDraft $draft): Collection
    {
        $query = $draft->audienceQuery()
            ?? throw new \DomainException(__('email-composer::drafts.notifications.list_missing', ['id' => $draft->id]));

        return $query->pluck((new Recipient)->getQualifiedKeyName());
    }
}
