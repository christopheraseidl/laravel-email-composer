<?php

namespace CSeidl\EmailComposer\Actions\Feedback;

use CSeidl\EmailComposer\Models\DraftFeedback;

/**
 * @internal
 */
class ToggleFeedbackResolved
{
    public function __invoke(DraftFeedback $feedback): void
    {
        $feedback->update(['resolved' => ! $feedback->resolved]);
    }
}
