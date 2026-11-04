<?php

namespace CSeidl\EmailComposer\Enums;

enum EmailDraftStatus: string
{
    case Draft = 'draft';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('email-composer::drafts.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::UnderReview => 'warning',
            self::Approved => 'info',
            self::Sending => 'primary',
            self::Sent => 'success',
            self::Cancelled => 'gray',
        };
    }
}
