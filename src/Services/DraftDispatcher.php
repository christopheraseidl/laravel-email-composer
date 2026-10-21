<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailAudience;
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
            throw new \DomainException(
                "Cannot send draft #{$draft->id}: status is '{$draft->status->value}', expected 'approved'."
            );
        }

        $recipientIds = $this->audienceIds($draft);

        if ($recipientIds->isEmpty()) {
            throw new \DomainException("Cannot send draft #{$draft->id}: it has no recipients.");
        }

        $claimed = EmailDraft::whereKey($draft->getKey())
            ->where('status', EmailDraftStatus::Approved)
            ->update(['status' => EmailDraftStatus::Sending]);

        if ($claimed === 0) {
            throw new \DomainException("Cannot send draft #{$draft->id}: it is already sending or sent.");
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
        $id = (new Recipient)->getQualifiedKeyName();

        return match ($draft->audience) {
            EmailAudience::All => Recipient::query()->pluck('id'),
            EmailAudience::List => ($draft->recipientList
                ?? throw new \DomainException("Cannot send draft #{$draft->id}: its recipient list no longer exists."))
                ->recipients()->pluck($id),
            EmailAudience::Selected => $draft->recipients()->pluck($id),
        };
    }
}
