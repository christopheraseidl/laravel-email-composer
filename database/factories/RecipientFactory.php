<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipient>
 */
#[UseModel((Recipient::class))]
class RecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'locale' => fake()->randomElement(['en', 'es', null]),
            'metadata' => null,
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => ['unsubscribed_at' => now()]);
    }
}
