<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Actions\Drafts\Concerns\AssertsDraftStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

class MarkDraftSent
{
    use AssertsDraftStatus;

    /** @throws \DomainException when the draft is not sending */
    public function __invoke(EmailDraft $draft): void
    {
        $this->assertStatus($draft, EmailDraftStatus::Sending, 'cannot_mark_sent');
        // A retry ends sent again and keeps the first send's time.
        $draft->update([
            'status' => EmailDraftStatus::Sent,
            'sent_at' => $draft->sent_at ?? now(),
        ]);
    }
}
