<?php

namespace CSeidl\EmailComposer\Enums;

enum EmailDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('email-composer::drafts.delivery_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'info',
        };
    }
}
