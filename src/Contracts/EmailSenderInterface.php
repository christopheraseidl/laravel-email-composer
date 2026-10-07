<?php

namespace CSeidl\EmailComposer\Contracts;

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;

interface EmailSenderInterface
{
    public function send(EmailDraft $draft, Recipient $recipient, string $locale): void;
}
