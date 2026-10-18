<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Models\RecipientList;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecipientList>
 */
#[UseModel(RecipientList::class)]
class RecipientListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
        ];
    }
}
