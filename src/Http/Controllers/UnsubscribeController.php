<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use CSeidl\EmailComposer\Actions\Recipients\UnsubscribeRecipient;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Services\ConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

class UnsubscribeController
{
    public function __construct(protected ConfigService $config) {}

    public function show(Request $request, Recipient $recipient): View
    {
        $this->useRecipientLocale($recipient);

        return view('email-composer::unsubscribe.show', [
            'email' => $recipient->email,
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Recipient $recipient): View
    {
        app(UnsubscribeRecipient::class)($recipient);

        $this->useRecipientLocale($recipient);

        return view('email-composer::unsubscribe.done', [
            'email' => $recipient->email,
        ]);
    }

    protected function useRecipientLocale(Recipient $recipient): void
    {
        $locale = $this->config->localeOrDefault($recipient->locale);

        App::setLocale($locale);
    }
}
