<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Models\DraftFeedback;
use CSeidl\EmailComposer\Models\EmailDraft;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DraftFeedback>
 */
#[UseModel(DraftFeedback::class)]
class DraftFeedbackFactory extends Factory
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
            'email_draft_id' => EmailDraft::factory(),
            'user_id' => $userModel::factory(),
            'reviewer_name' => null,
            'reviewer_email' => null,
            'comment' => fake()->paragraph(),
            'resolved' => false,
        ];
    }

    /** A comment from a reviewer without an account. */
    public function external(): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'reviewer_name' => fake()->name(),
            'reviewer_email' => fake()->safeEmail(),
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['resolved' => true]);
    }
}
