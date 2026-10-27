<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Jobs\QueueDraftRecipients;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Bus\Batch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class DraftDispatcher
{
    /**
     * Queue one SendEmailJob per recipient and mark the draft sent once all have run.
     *
     * @throws \DomainException when the draft is not approved, its audience is empty or its list is gone, or another dispatch claimed it
     */
    public function dispatch(EmailDraft $draft): Batch
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
        $draft->unsetRelation('recipients');

        return Bus::batch([new QueueDraftRecipients($draft)])
            ->name("email-composer draft #{$draft->id}")
            ->when(EmailComposer::config()->queueConnection(), fn ($batch, $connection) => $batch->onConnection($connection))
            ->onQueue(EmailComposer::config()->queueName())
            ->allowFailures()
            ->finally(fn () => $draft->markSent())
            ->dispatch();
    }

    /** @return Collection<int, int> */
    protected function audienceIds(EmailDraft $draft): Collection
    {
        $query = $draft->audienceQuery()
            ?? throw new \DomainException(__('email-composer::drafts.notifications.list_missing', ['id' => $draft->id]));

        return $query->pluck((new Recipient)->getQualifiedKeyName());
    }
}
