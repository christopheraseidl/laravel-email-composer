<?php

namespace CSeidl\EmailComposer\Unsubscribe;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Support\Facades\URL;

class UnsubscribeLink
{
    public static function for(Recipient $recipient): string
    {
        $ttl = EmailComposer::config()->unsubscribeLinkTtlDays();
        $parameters = ['recipient' => $recipient->getKey()];

        if ($ttl === null) {
            return URL::signedRoute('email-composer.unsubscribe.show', $parameters);
        }

        return URL::temporarySignedRoute('email-composer.unsubscribe.show', now()->addDays((int) $ttl), $parameters);
    }
}
