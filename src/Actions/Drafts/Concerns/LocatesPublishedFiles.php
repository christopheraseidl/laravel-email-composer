<?php

namespace CSeidl\EmailComposer\Actions\Drafts\Concerns;

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\ConfigService;

/** @property ConfigService $config */
trait LocatesPublishedFiles
{
    protected function directoryFor(EmailDraft $draft): string
    {
        $base = trim($this->config->storagePath(), '/');

        return "{$base}/drafts/{$this->folderFor($draft)}";
    }

    /**
     * Unguessable without the app key, so files on a public disk cannot be
     * found by walking draft IDs. Stable per draft, so republishing overwrites.
     */
    protected function folderFor(EmailDraft $draft): string
    {
        return hash_hmac('sha256', (string) $draft->getKey(), (string) config('app.key'));
    }
}
