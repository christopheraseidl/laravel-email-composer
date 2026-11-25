<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Actions\Drafts\Concerns\LocatesPublishedFiles;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\ConfigService;

/**
 * Deletes the draft's published files and clears them from the draft.
 */
class UnpublishDraft
{
    use LocatesPublishedFiles;

    public function __construct(protected ConfigService $config) {}

    public function __invoke(EmailDraft $draft): void
    {
        $this->config->storage()->deleteDirectory($this->directoryFor($draft));

        $draft->forceFill(['public_files' => null])->save();
    }
}
