<?php

namespace CSeidl\EmailComposer\Http\Controllers;

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

        $disk = Storage::disk(config('email-composer.storage.disk'));
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
