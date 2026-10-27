<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ViewInBrowserController
{
    /**
     * Show the published draft HTML.
     */
    public function show(EmailDraft $draft, string $locale): Response
    {
        $path = $draft->publicFileFor($locale);

        abort_if($path === null, 404);

        $disk = Storage::disk(EmailComposer::config()->storageDisk());
        $html = ($disk->exists($path) ? $disk->get($path) : null)
            ?? rescue(fn () => $draft->renderFor(EmailComposer::config()->localeOrDefault($locale)), null, false);

        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex',
            'Content-Security-Policy' => "script-src 'none'",
        ]);
    }
}
