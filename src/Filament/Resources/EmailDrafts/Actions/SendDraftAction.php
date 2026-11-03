<?php

namespace CSeidl\EmailComposer\Filament\Resources\EmailDrafts\Actions;

use CSeidl\EmailComposer\Actions\Drafts\SummarizeSend;
use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Services\DraftDispatcher;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class SendDraftAction
{
    public static function make(): Action
    {
        return Action::make('send')
            ->label(__('email-composer::drafts.actions.send'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn ($record) => static::summary($record))
            ->disabled(fn ($record) => static::lacksAudience($record))
            ->tooltip(fn ($record) => static::lacksAudience($record) ? __('email-composer::drafts.tooltips.lacks_audience') : null)
            ->action(function ($record) {
                try {
                    app(DraftDispatcher::class)->dispatch($record);
                } catch (\DomainException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('email-composer::drafts.notifications.not_sent'))
                        ->body($e->getMessage())
                        ->send();
                }
            })
            ->authorize(fn ($record) => Gate::allows('send', $record));
    }

    // Only approved drafts can be sent, so other rows skip the audience query.
    protected static function lacksAudience(EmailDraft $draft): bool
    {
        return $draft->status === EmailDraftStatus::Approved
            && ! $draft->hasAudience();
    }

    // Filament evaluates the description only while the modal is mounted.
    protected static function summary(EmailDraft $draft): Htmlable|string
    {
        try {
            $summary = app(SummarizeSend::class)($draft);
        } catch (\DomainException $e) {
            return $e->getMessage();
        }

        $locales = collect($summary->perLocale)
            ->map(fn (int $count, string $locale) => "{$locale}: {$count}")
            ->implode(', ');

        $lines = [
            trans_choice('email-composer::drafts.send_summary.sends_to', $summary->total - $summary->skipped, ['locales' => $locales])
                .' '.trans_choice('email-composer::drafts.send_summary.skipped', $summary->skipped),
        ];

        foreach (array_filter($summary->missingPlaceholders) as $locale => $names) {
            $lines[] = trans_choice('email-composer::drafts.send_summary.missing_placeholders', count($names), [
                'locale' => $locale,
                'placeholders' => implode(', ', $names),
            ]);
        }

        return new HtmlString(collect($lines)->map(fn (string $line) => e($line))->implode('<br>'));
    }
}
