<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\ConfigService;
use Illuminate\Http\Response;

class ViewInBrowserController
{
    public function __construct(protected ConfigService $config) {}

    /**
     * Show the published draft HTML.
     */
    public function show(EmailDraft $draft, string $locale): Response
    {
        abort_if($draft->publicFileFor($locale) === null, 404);

        $html = $draft->publishedHtml($locale)
            ?? rescue(fn () => $draft->renderFor($this->config->localeOrDefault($locale)), null, false);

        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
            'Content-Security-Policy' => "script-src 'none'",
        ]);
    }
}
