<?php

namespace CSeidl\EmailComposer\Http\Controllers;

use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Support\Facades\Storage;

class ViewInBrowserController
{
    /**
     * Show the published draft HTML.
     */
    public function show(EmailDraft $draft, string $locale)
    {
        $files = $draft->public_files ?? [];
        $path = $files[$locale]
            ?? $files[config('email-composer.default_locale', 'en')]
            ?? null;

        abort_if($path === null, 404);

        $disk = Storage::disk(config('email-composer.storage.disk'));
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
