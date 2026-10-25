<?php

namespace CSeidl\EmailComposer\Contracts;

use CSeidl\EmailComposer\Data\ImportResult;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Tells the user who queued a recipient import how it went.
 */
interface ImportNotifierInterface
{
    /**
     * $failedParts of the import's $parts jobs threw. Some of their rows may
     * be imported.
     */
    public function finished(Authenticatable $user, ImportResult $result, int $failedParts, int $parts): void;

    /** The import stopped before importing any rows. */
    public function failed(Authenticatable $user, string $reason): void;
}
