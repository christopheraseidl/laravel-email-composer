<?php

namespace Workbench\Database\Seeders;

use CSeidl\EmailComposer\Database\Seeders\DatabaseSeeder as EmailComposerSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        UserFactory::new()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(EmailComposerSeeder::class);
    }
}
