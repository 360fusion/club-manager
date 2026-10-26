<?php

namespace Database\Factories;

use App\Models\Lodge;
use App\Models\LodgeCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LodgeCorrection>
 */
class LodgeCorrectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lodge_id' => Lodge::factory(),
            'user_id' => User::factory(),
            'changes' => ['website_url' => 'https://example.org'],
            'note' => null,
        ];
    }
}
