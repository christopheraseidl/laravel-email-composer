<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Database\Factories\Concerns\BuildsHostUsers;
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
    use BuildsHostUsers;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => $this->userFactory(),
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
        return $this->state(fn () => [
            'status' => EmailDraftStatus::Approved,
            'submitted_at' => now()->subHours(4),
            'approved_at' => now()->subHour(),
            'approved_by' => $this->userFactory(),
        ]);
    }

    public function sending(): static
    {
        return $this->approved()->state(fn () => [
            'status' => EmailDraftStatus::Sending,
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
