<?php

namespace CSeidl\EmailComposer\Database\Factories;

use CSeidl\EmailComposer\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
#[UseModel(EmailTemplate::class)]
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'body' => "<p>[[ greeting ]]</p>\n<p>[[ body ]]</p>\n<p>[[ signature ]]</p>",
            'placeholders' => ['greeting', 'body', 'signature'],
        ];
    }
}
