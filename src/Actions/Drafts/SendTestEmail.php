<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Contracts\EmailSenderInterface;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Support\Facades\Validator;

/**
 * Sends the draft to the given addresses, outside its audience and its
 * delivery records.
 */
class SendTestEmail
{
    public const MAX_EMAILS = 10;

    public function __construct(protected EmailSenderInterface $sender) {}

    /**
     * @param  array<int, string>  $emails
     *
     * @throws \DomainException when the addresses fail validation
     */
    public function __invoke(EmailDraft $draft, array $emails, string $locale): void
    {
        $validator = Validator::make(
            ['emails' => $emails],
            [
                'emails' => ['required', 'array', 'between:1,'.self::MAX_EMAILS],
                'emails.*' => ['email'],
            ],
            ['emails.*.email' => ':input is not a valid email address.'],
            ['emails' => 'addresses'],
        );

        if ($validator->fails()) {
            throw new \DomainException($validator->errors()->first());
        }

        foreach ($emails as $email) {
            $this->sender->send($draft, Recipient::forTestSend($email, $locale), $locale);
        }
    }
}
