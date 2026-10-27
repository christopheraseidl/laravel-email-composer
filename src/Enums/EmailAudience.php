<?php

namespace CSeidl\EmailComposer\Enums;

enum EmailAudience: string
{
    case All = 'all';
    case List = 'list';
    case Selected = 'selected';

    public function label(): string
    {
        return __('email-composer::drafts.audience.'.$this->value);
    }
}
