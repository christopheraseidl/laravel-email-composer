<?php

namespace CSeidl\EmailComposer\Actions\Recipients;

use CSeidl\EmailComposer\Models\Recipient;

class UnsubscribeRecipient
{
    /** Keeps the first unsubscribe time. */
    public function __invoke(Recipient $recipient): void
    {
        if ($recipient->unsubscribed_at === null) {
            $recipient->update(['unsubscribed_at' => now()]);
        }
    }
}
