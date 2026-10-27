<?php

namespace Database\Factories;

use App\Models\ClubType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubType>
 */
class ClubTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Masonic Lodge',
            'code' => 'masonic-'.fake()->unique()->numberBetween(1, 999999),
            'available_modules' => [],
            'default_settings' => [],
        ];
    }
}
