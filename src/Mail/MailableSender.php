<?php

namespace CSeidl\EmailComposer\Mail;

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Support\Facades\Mail;

class MailableSender implements EmailSenderInterface
{
    public function send(EmailDraft $draft, Recipient $recipient, string $locale): void
    {
        Mail::to($recipient->email)->send(new ComposedEmail($draft, $recipient, $locale));
    }
}
