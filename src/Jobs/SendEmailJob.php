<?php

namespace CSeidl\EmailComposer\Jobs;

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendEmailJob implements ShouldQueue
{
    use Batchable;
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public EmailDraft $draft,
        public Recipient $recipient,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmailSenderInterface $sender): void
    {
        if ($this->batch()?->cancelled()) {
            $this->updatePivotTable(['delivery_status' => EmailDeliveryStatus::Cancelled->value]);

            return;
        }

        if ($this->recipient->trashed() || ! is_null($this->recipient->unsubscribed_at)) {
            $this->updatePivotTable(['delivery_status' => EmailDeliveryStatus::Skipped->value]);

            return;
        }

        $this->draft->recipients()->newPivotStatementForId($this->recipient->getKey())->increment('attempts');

        $locale = $this->draft->deliveryLocaleFor($this->recipient->locale);
        $sender->send($this->draft, $this->recipient, $locale);

        $this->updatePivotTable([
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
        $this->updatePivotTable([
            'delivery_status' => EmailDeliveryStatus::Failed->value,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $columns
     */
    private function updatePivotTable(array $columns): void
    {
        $this->draft->recipients()->updateExistingPivot($this->recipient->getKey(), $columns);
    }
}
