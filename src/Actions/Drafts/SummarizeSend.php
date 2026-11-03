<?php

namespace CSeidl\EmailComposer\Actions\Drafts;

use CSeidl\EmailComposer\Data\SendSummary;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;

/**
 * Counts who a send would reach and in which locale, without queueing or
 * writing anything.
 */
class SummarizeSend
{
    /** @throws \DomainException when the draft's list no longer exists */
    public function __invoke(EmailDraft $draft): SendSummary
    {
        $query = $draft->audienceQuery()
            ?? throw new \DomainException(__('email-composer::drafts.notifications.summary_list_missing', ['id' => $draft->id]));

        $recipient = new Recipient;
        $unsubscribedAt = $recipient->qualifyColumn('unsubscribed_at');
        $locale = $recipient->qualifyColumn('locale');

        $total = (clone $query)->count();
        $skipped = (clone $query)->whereNotNull($unsubscribedAt)->count();

        $rows = (clone $query)->toBase()
            ->whereNull($unsubscribedAt)
            ->groupBy($locale)
            ->select($locale)
            ->selectRaw('count(*) as aggregate')
            ->get();

        $perLocale = [];
        foreach ($rows as $row) {
            $deliveryLocale = $draft->deliveryLocaleFor($row->locale);
            $perLocale[$deliveryLocale] = ($perLocale[$deliveryLocale] ?? 0) + (int) $row->aggregate;
        }
        ksort($perLocale);

        $missingPlaceholders = [];
        foreach (array_keys($perLocale) as $deliveryLocale) {
            $missingPlaceholders[$deliveryLocale] = $draft->missingPlaceholders($deliveryLocale);
        }

        return new SendSummary($total, $skipped, $perLocale, $missingPlaceholders);
    }
}
