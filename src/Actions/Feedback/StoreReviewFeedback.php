<?php

namespace CSeidl\EmailComposer\Actions\Feedback;

use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;

class StoreReviewFeedback
{
    /** @param  array<string, mixed>  $validated  reviewer_name, reviewer_email and comment */
    public function __invoke(EmailDraft $draft, array $validated): DraftFeedback
    {
        return $draft->feedback()->create($validated);
    }
}
