<?php

namespace Database\Factories;

use App\Models\ClubEmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubEmailTemplate>
 */
class ClubEmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_key' => 'event_payment_reminder',
            'subject' => fake()->sentence(4),
            'body_html' => '<p>'.fake()->sentence().'</p>',
        ];
    }
}
