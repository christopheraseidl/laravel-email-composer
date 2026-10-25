<?php

namespace CSeidl\EmailComposer\Mail;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Unsubscribe\UnsubscribeLink;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\Storage;

class ComposedEmail extends Mailable
{
    protected string $unsubscribeUrl;

    public function __construct(
        public EmailDraft $draft,
        public Recipient $recipient,
        string $locale,
    ) {
        $this->locale($locale);
        // A test recipient is not stored, so it has nothing to unsubscribe.
        $this->unsubscribeUrl = $recipient->isTest() ? '#' : UnsubscribeLink::for($recipient);
    }

    public function envelope(): Envelope
    {
        $subject = $this->draft->subjectFor($this->locale);

        return new Envelope(subject: $this->recipient->isTest() ? "[Test] {$subject}" : $subject);
    }

    public function content(): Content
    {
        $disk = Storage::disk(EmailComposer::config()->storageDisk());
        $path = $this->draft->publicFileFor($this->locale);
        $body = ($path && $disk->exists($path) ? $disk->get($path) : null)
            ?? $this->draft->renderFor($this->locale);

        return new Content(htmlString: $this->appendFooter($body));
    }

    public function headers(): Headers
    {
        if ($this->recipient->isTest()) {
            return new Headers;
        }

        return new Headers(text: [
            'List-Unsubscribe' => "<{$this->unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    protected function appendFooter(string $html): string
    {
        $footer = view('email-composer::mail.footer', [
            'recipient' => $this->recipient,
            'unsubscribeUrl' => $this->unsubscribeUrl,
            'viewInBrowserUrl' => $this->draft->viewInBrowserUrl($this->locale),
        ])->render();

        // str_ireplace returns the input unchanged when there is no </body>.
        if (stripos($html, '</body>') === false) {
            return $html.$footer;
        }

        return str_ireplace('</body>', $footer.'</body>', $html);
    }
}
