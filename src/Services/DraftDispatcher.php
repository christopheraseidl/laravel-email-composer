<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Jobs\QueueDraftRecipients;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class DraftDispatcher
{
    /**
     * Queue one SendEmailJob per recipient and mark the draft sent once all have run.
     */
    public function dispatch(EmailDraft $draft): Batch
    {
        if ($draft->status !== EmailDraftStatus::Approved) {
            throw new \DomainException(
                "Cannot send draft #{$draft->id}: status is '{$draft->status->value}', expected 'approved'."
            );
        }

        if ($draft->recipients()->doesntExist()) {
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

        return Bus::batch([new QueueDraftRecipients($draft)])
            ->name("email-composer draft #{$draft->id}")
            ->when(EmailComposer::config()->queueConnection(), fn ($batch, $connection) => $batch->onConnection($connection))
            ->onQueue(EmailComposer::config()->queueName())
            ->allowFailures()
            ->finally(fn () => $draft->markSent())
            ->dispatch();
    }
}
