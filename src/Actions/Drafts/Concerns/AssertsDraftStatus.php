<?php

namespace CSeidl\EmailComposer\Actions\Drafts\Concerns;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;

/**
 * @internal
 */
trait AssertsDraftStatus
{
    /**
     * @param  string  $message  a key in the drafts notifications group
     *
     * @throws \DomainException when the draft is not in the expected status
     */
    protected function assertStatus(EmailDraft $draft, EmailDraftStatus $expected, string $message): void
    {
        if ($draft->status !== $expected) {
            throw new \DomainException(__("email-composer::drafts.notifications.{$message}", [
                'id' => $draft->id,
                'status' => $draft->status->label(),
                'expected' => $expected->label(),
            ]));
        }
    }
}
