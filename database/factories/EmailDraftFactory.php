<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Enums\EmailDraftStatus;
use CSeidl\EmailComposer\Models\EmailDraft;
use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDraft>
 */
#[UseModel(EmailDraft::class)]
class EmailDraftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $userModel = config('auth.providers.users.model');

        return [
            'author_id' => $userModel::factory(),
            'email_template_id' => EmailTemplate::factory(),
            'template_key' => null,
            'subject' => ['en' => fake()->sentence(), 'es' => fake()->sentence()],
            'placeholders' => [
                'en' => [
                    'greeting' => 'Hello',
                    'body' => fake()->paragraph(),
                    'signature' => '— The team',
                ],
                'es' => [
                    'greeting' => 'Hola',
                    'body' => fake()->paragraph(),
                    'signature' => '— El equipo',
                ],
            ],
            'status' => EmailDraftStatus::Draft,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status' => EmailDraftStatus::UnderReview,
            'submitted_at' => now()->subHours(2),
        ]);
    }

    public function approved(): static
    {
        $userModel = config('auth.providers.users.model');

        return $this->state(fn () => [
            'status' => EmailDraftStatus::Approved,
            'submitted_at' => now()->subHours(4),
            'approved_at' => now()->subHour(),
            'approved_by' => $userModel::factory(),
        ]);
    }

    public function sent(): static
    {
        return $this->approved()->state(fn () => [
            'status' => EmailDraftStatus::Sent,
            'sent_at' => now()->subMinutes(30),
        ]);
    }
}
