<?php

namespace CSeidl\EmailComposer\Mail;

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Unsubscribe\UnsubscribeLink;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

class ComposedEmail extends Mailable
{
    public function __construct(
        public EmailDraft $draft,
        public Recipient $recipient,
        string $locale,
    ) {
        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->draft->subjectFor($this->locale));
    }

    public function content(): Content
    {
        $disk = Storage::disk(config('email-composer.storage.disk'));
        $files = $this->draft->public_files ?? [];
        $path = $files[$this->locale]
            ?? $files[config('email-composer.default_locale', 'en')]
            ?? null;
        $body = $path && $disk->exists($path) ? $disk->get($path) : $this->draft->renderFor($this->locale);

        return new Content(htmlString: $this->appendFooter($body));
    }

    protected function appendFooter(string $html): string
    {
        $footer = view('email-composer::mail.footer', [
            'recipient' => $this->recipient,
            'unsubscribeUrl' => UnsubscribeLink::for($this->recipient),
            'viewInBrowserUrl' => $this->draft->viewInBrowserUrl($this->locale),
        ])->render();

        // str_ireplace returns the input unchanged when there is no </body>.
        if (stripos($html, '</body>') === false) {
            return $html.$footer;
        }

        return str_ireplace('</body>', $footer.'</body>', $html);
    }
}
