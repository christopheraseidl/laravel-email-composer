<?php

namespace CSeidl\EmailComposer\Enums;

enum EmailAudience: string
{
    case All = 'all';
    case List = 'list';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All recipients',
            self::List => 'A recipient list',
            self::Selected => 'Selected recipients',
        };
    }
}
