<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\EmailComposer;
use CSeidl\EmailComposer\Enums\EmailAudience;
use CSeidl\EmailComposer\Enums\EmailDeliveryStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use CSeidl\EmailComposer\Models\SendFailure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class EmailDraftSeeder extends Seeder
{
    /** The error of each try in turn. A failed recipient's last one is its pivot error. */
    protected const ERRORS = [
        'Connection could not be established with host "smtp.example.com:587": stream_socket_client(): Unable to connect (Connection timed out)',
        'Expected response code "250" but got code "421", with message "421 4.7.0 Try again later".',
        'Expected response code "250" but got code "550", with message "550 5.1.1 Mailbox unavailable".',
    ];

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

        // 1 sent: most delivered, two of them after failed tries, two failed (Retry),
        // one unsubscribed before the send, and one deleted after it
        $sent = $factory->sent()->create();
        $picked = $recipients->shuffle();
        $this->attachDeliveries($sent, $picked->take(5), EmailDeliveryStatus::Sent);
        $this->attachDeliveries($sent, $picked->slice(5, 1), EmailDeliveryStatus::Sent, failures: 1);
        $this->attachDeliveries($sent, $picked->slice(6, 1), EmailDeliveryStatus::Sent, failures: 2);
        $this->attachDeliveries($sent, $picked->slice(7, 2), EmailDeliveryStatus::Failed);
        $this->attachDeliveries($sent, Recipient::unsubscribed()->take(1)->get(), EmailDeliveryStatus::Skipped);

        $deleted = Recipient::factory()->create();
        $this->attachDeliveries($sent, collect([$deleted]), EmailDeliveryStatus::Sent);
        $deleted->delete();

        // 1 sending: some delivered, one failed, one queued, two waiting for room
        // in the rate limits
        $sending = $factory->sending()->create();
        $picked = $recipients->random(7);
        $this->attachDeliveries($sending, $picked->take(2), EmailDeliveryStatus::Sent);
        $this->attachDeliveries($sending, $picked->slice(2, 1), EmailDeliveryStatus::Sent, failures: 1);
        $this->attachDeliveries($sending, $picked->slice(3, 1), EmailDeliveryStatus::Failed);
        $this->attachDeliveries($sending, $picked->slice(4, 1), EmailDeliveryStatus::Pending, queued: true);
        $this->attachDeliveries($sending, $picked->slice(5), EmailDeliveryStatus::Pending);

        // 1 cancelled partway: some delivered, one failed, the rest cancelled
        $cancelled = $factory->cancelled()->create();
        $picked = $recipients->random(6);
        $this->attachDeliveries($cancelled, $picked->take(2), EmailDeliveryStatus::Sent);
        $this->attachDeliveries($cancelled, $picked->slice(2, 1), EmailDeliveryStatus::Failed);
        $this->attachDeliveries($cancelled, $picked->slice(3), EmailDeliveryStatus::Cancelled);
    }

    /**
     * Attach recipients with the pivot values SendEmailJob leaves for the status, and a
     * SendFailure row for each try that threw: three for a failed recipient, `$failures`
     * before a sent one.
     *
     * @param  Collection<int, Recipient>  $recipients
     */
    protected function attachDeliveries(
        EmailDraft $draft,
        Collection $recipients,
        EmailDeliveryStatus $status,
        int $failures = 0,
        bool $queued = false,
    ): void {
        $failures = $status === EmailDeliveryStatus::Failed ? count(self::ERRORS) : $failures;

        $recipients->each(function (Recipient $recipient) use ($draft, $status, $failures, $queued) {
            $draft->recipients()->attach($recipient->id, match ($status) {
                EmailDeliveryStatus::Sent => [
                    'delivery_status' => $status->value,
                    'locale' => EmailComposer::config()->localeOrDefault($recipient->locale),
                    'sent_at' => now()->subMinutes(20),
                    'attempts' => $failures + 1,
                ],
                EmailDeliveryStatus::Failed => [
                    'delivery_status' => $status->value,
                    'error' => self::ERRORS[$failures - 1],
                    'attempts' => $failures,
                ],
                default => [
                    'delivery_status' => $status->value,
                    'queued_at' => $queued ? now() : null,
                ],
            });

            // 30 seconds apart, as SendEmailJob's backoff spaces them, ending before the send.
            for ($try = 0; $try < $failures; $try++) {
                SendFailure::factory()->create([
                    'email_draft_id' => $draft->id,
                    'recipient_id' => $recipient->id,
                    'error' => self::ERRORS[$try],
                    'failed_at' => now()->subMinutes(20)->subSeconds(30 * ($failures - $try)),
                ]);
            }
        });
    }
}
