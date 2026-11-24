<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Actions\Drafts\Concerns\AssertsDraftStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

class MarkDraftCancelled
{
    use AssertsDraftStatus;

    /** @throws \DomainException when the draft is not sending */
    public function __invoke(EmailDraft $draft): void
    {
        $this->assertStatus($draft, EmailDraftStatus::Sending, 'cannot_mark_cancelled');
        $draft->update([
            'status' => EmailDraftStatus::Cancelled,
        ]);
    }
}
