<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use Closure;
use Filament\Notifications\Notification;

/**
 * @internal
 */
class NotifiesDomainErrors
{
    /**
     * Run the action, showing a \DomainException it throws as a danger notification.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $action
     * @param  (Closure(TResult): mixed)|null  $then  runs with the action's result when it succeeds
     */
    public static function run(Closure $action, string $failureTitle, ?Closure $then = null): void
    {
        try {
            $result = $action();
        } catch (\DomainException $e) {
            Notification::make()
                ->danger()
                ->title($failureTitle)
                ->body($e->getMessage())
                ->send();

            return;
        }

        if ($then !== null) {
            $then($result);
        }
    }
}
