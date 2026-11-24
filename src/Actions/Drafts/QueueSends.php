<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Jobs\SendEmailJob;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\SendRateLimiter;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Queues a sending draft's unqueued recipients, as many as the rate limits have room for,
 * and once none is pending, marks the draft sent, or cancelled while any recipient is. SendDraft runs it on Send, and the
 * scheduled email-composer:queue-sends command runs it again for the rest. Nothing waits
 * in the queue: a recipient not queued yet waits in its pivot row.
 */
class QueueSends
{
    public function __construct(
        protected SendRateLimiter $limiter,
        protected QueueFactory $queue,
        protected MarkDraftSent $markDraftSent,
        protected MarkDraftCancelled $markDraftCancelled,
    ) {}

    public function __invoke(EmailDraft $draft): void
    {
        // A dispatch and a scheduled run, or two servers, must not queue the same rows.
        Cache::lock("email-composer:queue-sends:{$draft->getKey()}", 60)->get(fn () => $this->queue($draft));
    }

    protected function queue(EmailDraft $draft): void
    {
        $draft->refresh();

        if ($draft->status !== EmailDraftStatus::Sending) {
            return;
        }

        // A job may retry for a day, so a row queued longer ago than that lost its job.
        $lostBefore = now()->subDay();
        $remaining = $this->limiter->remaining();
        $room = $remaining === null
            ? PHP_INT_MAX
            : $remaining - $this->pending($draft)->where('queued_at', '>=', $lostBefore)->count();

        while ($room > 0) {
            $ids = $this->pending($draft)
                ->where(fn (Builder $query) => $query->whereNull('queued_at')->orWhere('queued_at', '<', $lostBefore))
                ->orderBy($this->recipientKey($draft))
                ->limit(min($room, EmailComposer::config()->bulkChunkSize()))
                ->pluck($this->recipientKey($draft));

            if ($ids->isEmpty()) {
                break;
            }

            $queuedAt = now();
            $this->pending($draft)->whereIn($this->recipientKey($draft), $ids)->update(['queued_at' => $queuedAt]);
            // Trashed recipients too: their jobs mark them skipped, which ends their rows.
            $jobs = Recipient::withTrashed()->whereKey($ids)->get()
                ->map(fn (Recipient $recipient) => new SendEmailJob($draft, $recipient, $queuedAt));

            $this->queue->connection(EmailComposer::config()->queueConnection())
                ->bulk($jobs->all(), queue: EmailComposer::config()->queueName());
            $room -= $ids->count();
        }

        // A cancel may have landed while these were queued, and a retry may be adding rows.
        $draft->getConnection()->transaction(function () use ($draft) {
            if ($draft->refreshForUpdate()->status !== EmailDraftStatus::Sending || $this->pending($draft)->exists()) {
                return;
            }

            // A cancelled draft retried in part stays cancelled.
            $this->rows($draft)->where('delivery_status', EmailDeliveryStatus::Cancelled->value)->exists()
                ? ($this->markDraftCancelled)($draft)
                : ($this->markDraftSent)($draft);
        });
    }

    protected function pending(EmailDraft $draft): Builder
    {
        return $this->rows($draft)->where('delivery_status', EmailDeliveryStatus::Pending->value);
    }

    protected function rows(EmailDraft $draft): Builder
    {
        return $draft->recipients()->newPivotQuery();
    }

    protected function recipientKey(EmailDraft $draft): string
    {
        return $draft->recipients()->getRelatedPivotKeyName();
    }
}
