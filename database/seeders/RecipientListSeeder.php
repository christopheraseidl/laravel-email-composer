<?php

namespace CSeidl\EmailComposer\Database\Seeders;

use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Database\Seeder;

class RecipientListSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subscribed = Recipient::subscribed()->get()->shuffle();
        $newsletterMembers = $subscribed->take(20);

        // Unsubscribed members stay on the list but get no mail
        $newsletter = RecipientList::factory()->create(['name' => 'Newsletter']);
        $newsletter->recipients()->attach($newsletterMembers->pluck('id'));
        $newsletter->recipients()->attach(Recipient::unsubscribed()->take(1)->pluck('id'));

        // Overlaps the newsletter
        $customers = RecipientList::factory()->create(['name' => 'Customers']);
        $customers->recipients()->attach($newsletterMembers->random(10)->pluck('id'));
        $customers->recipients()->attach($subscribed->slice(20)->random(5)->pluck('id'));

        $beta = RecipientList::factory()->create(['name' => 'Beta testers']);
        $beta->recipients()->attach($subscribed->random(5)->pluck('id'));

        $old = RecipientList::factory()->create(['name' => 'Old newsletter']);
        $old->recipients()->attach($subscribed->random(3)->pluck('id'));
        $old->delete();
    }
}
