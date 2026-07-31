<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RecipientSeeder::class,
            EmailTemplateSeeder::class,
            EmailDraftSeeder::class,
        ]);
    }
}
