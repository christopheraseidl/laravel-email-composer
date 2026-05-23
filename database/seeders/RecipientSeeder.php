<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Database\Seeder;

class RecipientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Recipient::factory()->count(45)->create();
        Recipient::factory()->count(5)->unsubscribed()->create();
    }
}
