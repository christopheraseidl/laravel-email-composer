<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Facades\Bus;

/**
 * Cancels the batch sending the draft. Its finally callback marks the draft
 * cancelled once the jobs already queued have run.
 */
class CancelSend
{
    /** @throws \DomainException when the draft is not sending or its batch is gone */
    public function __invoke(EmailDraft $draft): void
    {
        if ($draft->status !== EmailDraftStatus::Sending) {
            throw new \DomainException(__('email-composer::drafts.notifications.cannot_cancel', [
                'id' => $draft->id,
                'status' => $draft->status->label(),
                'expected' => EmailDraftStatus::Sending->label(),
            ]));
        }

        $batch = $draft->batch_id ? Bus::findBatch($draft->batch_id) : null;

        if (! $batch) {
            throw new \DomainException(__('email-composer::drafts.notifications.batch_missing', ['id' => $draft->id]));
        }

        $batch->cancel();
    }
}
