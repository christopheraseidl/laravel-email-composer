<?php

namespace CSeidl\EmailComposer\Services;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
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

        // An empty batch never runs its finally callback, so the draft would stay approved.
        if ($draft->recipients->isEmpty()) {
            throw new \DomainException("Cannot send draft #{$draft->id}: it has no recipients.");
        }

        $jobs = $draft->recipients->map(fn ($recipient) => new SendEmailJob($draft, $recipient));

        // A batch pushes its jobs on its own connection and queue, ignoring each job's.
        return Bus::batch($jobs->all())
            ->name("email-composer draft #{$draft->id}")
            // null means the app's default connection, which onConnection() doesn't accept.
            ->when(config('email-composer.queue.connection'), fn ($batch, $connection) => $batch->onConnection($connection))
            ->onQueue(config('email-composer.queue.queue'))
            ->allowFailures()
            ->finally(fn () => $draft->markSent())
            ->dispatch();
    }
}
