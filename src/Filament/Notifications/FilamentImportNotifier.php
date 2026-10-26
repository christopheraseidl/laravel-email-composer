<?php

namespace CSeidl\EmailComposer\Filament\Notifications;

use CSeidl\EmailComposer\Contracts\ImportNotifierInterface;
use CSeidl\EmailComposer\Data\ImportResult;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Auth\Authenticatable;
use Override;

/**
 * Reports imports through Filament database notifications.
 */
class FilamentImportNotifier implements ImportNotifierInterface
{
    /** Rejected lines listed in the notification body. */
    public const REJECTED_SHOWN = 10;

    #[Override]
    public function finished(Authenticatable $user, ImportResult $result, int $failedParts, int $parts): void
    {
        $rejected = count($result->rejected);
        $lines = [__('email-composer::import.notifications.totals', [
            'created' => $result->created,
            'updated' => $result->updated,
            'skipped' => $result->skipped,
            'rejected' => $rejected,
        ])];

        if ($failedParts > 0) {
            $lines[] = __('email-composer::import.notifications.parts_failed', ['failed' => $failedParts, 'parts' => $parts]);
        }

        foreach (array_slice($result->rejected, 0, self::REJECTED_SHOWN, true) as $line => $reason) {
            $lines[] = __('email-composer::import.notifications.rejected_line', ['line' => $line, 'reason' => $reason]);
        }

        Notification::make()
            ->title(__($failedParts > 0 ? 'email-composer::import.notifications.partly_failed' : 'email-composer::import.notifications.imported'))
            ->body(implode('<br>', array_map(e(...), $lines)))
            ->status(match (true) {
                $failedParts > 0 => 'danger',
                $rejected > 0 => 'warning',
                default => 'success',
            })
            ->sendToDatabase($user);
    }

    #[Override]
    public function failed(Authenticatable $user, string $reason): void
    {
        Notification::make()
            ->title(__('email-composer::import.notifications.failed'))
            ->body($reason)
            ->danger()
            ->sendToDatabase($user);
    }
}
