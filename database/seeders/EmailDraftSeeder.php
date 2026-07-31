<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Database\Seeder;

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

        // 1 sent
        $factory->sent()->create()->each(
            fn ($d) => $d->recipients()->attach(
                $recipients->random(10)->pluck('id'),
                [
                    'delivery_status' => EmailDraftStatus::Sent,
                    'sent_at' => now()->subMinutes(20),
                    'attempts' => 1,
                ]
            )
        );
    }
}
