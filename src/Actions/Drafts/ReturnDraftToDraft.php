<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

/**
 * @final
 */
class ReturnDraftToDraft
{
    /** @internal */
    public function __construct(protected UnpublishDraft $unpublishDraft) {}

    /** @throws \DomainException when the draft is a draft, sending or sent */
    public function __invoke(EmailDraft $draft): void
    {
        if (! $draft->canReturnToDraft()) {
            throw new \DomainException(__('email-composer::drafts.notifications.cannot_return', [
                'id' => $draft->id,
                'status' => $draft->status->label(),
            ]));
        }

        $draft->update([
            'status' => EmailDraftStatus::Draft,
            'submitted_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);

        ($this->unpublishDraft)($draft);
    }
}
