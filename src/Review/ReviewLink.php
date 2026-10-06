<?php

namespace CSeidl\EmailComposer\Review;

use CSeidl\EmailComposer\Models\EmailDraft;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

class ReviewLink
{
    public static function for(EmailDraft $draft, ?string $locale = null, ?DateTimeInterface $expires = null): string
    {
        return URL::temporarySignedRoute(
            'email-composer.review.show',
            $expires ?? now()->addDays((int) config('email-composer.review.link_ttl_days')),
            array_filter(['draft' => $draft->getKey(), 'locale' => $locale]),
        );
    }
}
