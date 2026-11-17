<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\Recipient;
use CSeidl\EmailComposer\Models\SendFailure;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SendFailure>
 */
#[UseModel(SendFailure::class)]
class SendFailureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_draft_id' => EmailDraft::factory()->sent(),
            'recipient_id' => Recipient::factory(),
            'error' => fake()->sentence(),
            'failed_at' => now(),
        ];
    }
}
