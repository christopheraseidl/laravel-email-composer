<?php

namespace CSeidl\EmailComposer\Notifications;

use CSeidl\EmailComposer\Contracts\ImportNotifierInterface;
use CSeidl\EmailComposer\Data\ImportResult;
use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * The default without Filament: results go nowhere until the app binds its
 * own ImportNotifierInterface.
 */
class NullImportNotifier implements ImportNotifierInterface
{
    #[Override]
    public function finished(Authenticatable $user, ImportResult $result, int $failedParts, int $parts): void {}

    #[Override]
    public function failed(Authenticatable $user, string $reason): void {}
}
