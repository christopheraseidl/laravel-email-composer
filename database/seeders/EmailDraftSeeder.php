<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class EmailDraftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recipients = Recipient::subscribed()->take(10)->get();

        $factory = EmailDraft::factory();

        // 3 in draft
        $factory->count(3)->create()->each(
            fn ($d) => $d->recipients()->attach($recipients->random(3)->pluck('id'))
        );

        // 2 under review
        $factory->count(2)->underReview()->create()->each(
            fn ($d) => $d->recipients()->attach($recipients->random(5)->pluck('id'))
        );

        // 2 approved (ready to send)
        $factory->count(2)->approved()->create()->each(
            fn ($d) => $d->recipients()->attach($recipients->random(7)->pluck('id'))
        );

        // 1 approved for the Newsletter list, so no recipients attached
        $factory->approved()->create([
            'audience' => EmailAudience::List,
            'recipient_list_id' => RecipientList::where('name', 'Newsletter')->value('id'),
        ]);

        // 1 sent: most delivered, two failed, one unsubscribed before the send,
        // and one deleted after it
        $sent = $factory->sent()->create();
        $picked = $recipients->shuffle();
        $this->attachDeliveries($sent, $picked->take(7), EmailDeliveryStatus::Sent);
        $this->attachDeliveries($sent, $picked->slice(7, 2), EmailDeliveryStatus::Failed);
        $this->attachDeliveries($sent, Recipient::unsubscribed()->take(1)->get(), EmailDeliveryStatus::Skipped);

        $deleted = Recipient::factory()->create();
        $this->attachDeliveries($sent, collect([$deleted]), EmailDeliveryStatus::Sent);
        $deleted->delete();

        // 1 sending: half delivered, half still queued
        $sending = $factory->sending()->create();
        $picked = $recipients->random(6);
        $this->attachDeliveries($sending, $picked->take(3), EmailDeliveryStatus::Sent);
        $this->attachDeliveries($sending, $picked->slice(3), EmailDeliveryStatus::Pending);
    }

    /**
     * Attach recipients with the pivot values SendEmailJob leaves for the status.
     *
     * @param  Collection<int, Recipient>  $recipients
     */
    protected function attachDeliveries(EmailDraft $draft, Collection $recipients, EmailDeliveryStatus $status): void
    {
        $recipients->each(fn (Recipient $recipient) => $draft->recipients()->attach($recipient->id, match ($status) {
            EmailDeliveryStatus::Sent => [
                'delivery_status' => $status->value,
                'locale' => EmailComposer::config()->localeOrDefault($recipient->locale),
                'sent_at' => now()->subMinutes(20),
                'attempts' => 1,
            ],
            EmailDeliveryStatus::Failed => [
                'delivery_status' => $status->value,
                'error' => 'Expected response code "250" but got code "550", with message "550 5.1.1 Mailbox unavailable".',
                'attempts' => 3,
            ],
            default => ['delivery_status' => $status->value],
        }));
    }
}
