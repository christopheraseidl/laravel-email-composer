<?php

namespace CSeidl\EmailComposer\Support;

class NameWithEmail
{
    /** "Name <email>", or whichever part is present, or '' when neither is. */
    public static function format(?string $name, ?string $email): string
    {
        return trim(($name ?? '').($email ? " <{$email}>" : ''));
    }
}
