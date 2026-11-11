<?php

namespace CSeidl\EmailComposer\Jobs;

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\SendRateLimiter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;

/**
 * Sends one recipient's email. It acts only on the pivot row it was queued for, matched by
 * queued_at: a row that was cancelled, finished or queued again since belongs to no job or
 * to a newer one.
 */
class SendEmailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Handing a row back to the rate limiter is not an exception. A row queued again gets a
     * new job and a new count, so handle() also checks the recipient's attempts.
     */
    public int $maxExceptions = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailDraft $draft,
        public Recipient $recipient,
        public Carbon $queuedAt,
    ) {}

    public function retryUntil(): Carbon
    {
        return now()->addDay();
    }

    /**
     * Execute the job.
     */
    public function handle(EmailSenderInterface $sender, SendRateLimiter $limiter): void
    {
        $row = $this->pivot()->first();

        if ($row === null || $row->delivery_status !== EmailDeliveryStatus::Pending->value) {
            return;
        }

        if ($this->recipient->trashed() || ! is_null($this->recipient->unsubscribed_at)) {
            $this->updatePivot(['delivery_status' => EmailDeliveryStatus::Skipped->value]);

            return;
        }

        if ($row->attempts >= $this->maxExceptions) {
            $this->fail(new RuntimeException(__('email-composer::drafts.deliveries.gave_up', ['count' => $this->maxExceptions])));

            return;
        }

        // A full limit hands the row back to QueueSends, which queues it again when there is room.
        if (! $limiter->attempt()) {
            $this->updatePivot(['queued_at' => null]);

            return;
        }

        $this->pivot()->increment('attempts');

        $locale = $this->draft->deliveryLocaleFor($this->recipient->locale);
        $sender->send($this->draft, $this->recipient, $locale);

        $this->updatePivot([
            'delivery_status' => EmailDeliveryStatus::Sent->value,
            'locale' => $locale,
            'sent_at' => now(),
        ]);
    }

    /**
     * Handle a failure after the final try.
     */
    public function failed(Throwable $e): void
    {
        $this->updatePivot([
            'delivery_status' => EmailDeliveryStatus::Failed->value,
            'error' => $e->getMessage(),
        ]);
    }

    private function pivot(): Builder
    {
        return $this->draft->recipients()
            ->newPivotStatementForId($this->recipient->getKey())
            ->where('queued_at', $this->queuedAt);
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function updatePivot(array $columns): void
    {
        $this->pivot()->update([...$columns, 'updated_at' => now()]);
    }
}
