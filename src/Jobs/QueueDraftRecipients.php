<?php

namespace CSeidl\EmailComposer\Jobs;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Adds one SendEmailJob per recipient to its batch, bulk.chunk_size at a time.
 * The batch stays pending while this job runs, so its finally callback cannot
 * fire between chunks.
 */
class QueueDraftRecipients implements ShouldQueue
{
    use Batchable;
    use Queueable;

    // A retry would queue duplicate sends for the chunks already added.
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailDraft $draft,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $this->draft->recipients()->chunkById(
            EmailComposer::config()->bulkChunkSize(),
            fn (Collection $recipients) => $this->batch()?->add(
                $recipients->map(fn (Recipient $recipient) => new SendEmailJob($this->draft, $recipient))->all(),
            ),
        );
    }
}
