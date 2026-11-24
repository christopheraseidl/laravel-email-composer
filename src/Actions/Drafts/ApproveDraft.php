<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Actions\Drafts\Concerns\AssertsDraftStatus;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Contracts\Auth\Authenticatable;

class ApproveDraft
{
    use AssertsDraftStatus;

    public function __construct(protected PublishDraft $publishDraft) {}

    /** @throws \DomainException when the draft is not under review or has no template */
    public function __invoke(EmailDraft $draft, Authenticatable $approver): void
    {
        $this->assertStatus($draft, EmailDraftStatus::UnderReview, 'cannot_approve');

        if (! $draft->resolveTemplate()) {
            throw new \DomainException(__('email-composer::drafts.notifications.no_template', ['id' => $draft->id]));
        }

        $draft->update([
            'status' => EmailDraftStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approver->getAuthIdentifier(),
        ]);

        ($this->publishDraft)($draft->refresh());
    }
}
