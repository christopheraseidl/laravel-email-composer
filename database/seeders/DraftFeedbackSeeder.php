<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Database\Seeder;

class DraftFeedbackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EmailDraft::inStatus(EmailDraftStatus::UnderReview)->get()->each(function (EmailDraft $draft) {
            $factory = DraftFeedback::factory()->for($draft, 'draft');

            $factory->count(2)->create();
            $factory->external()->create();
            $factory->resolved()->create();
        });
    }
}
