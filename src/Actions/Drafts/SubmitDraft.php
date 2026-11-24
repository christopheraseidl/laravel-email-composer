<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Actions\Drafts\Concerns\AssertsDraftStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

class SubmitDraft
{
    use AssertsDraftStatus;

    /** @throws \DomainException when the draft is not a draft */
    public function __invoke(EmailDraft $draft): void
    {
        $this->assertStatus($draft, EmailDraftStatus::Draft, 'cannot_submit');
        $draft->update([
            'status' => EmailDraftStatus::UnderReview,
            'submitted_at' => now(),
        ]);
    }
}
