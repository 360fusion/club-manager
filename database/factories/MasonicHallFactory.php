<?php

namespace Database\Factories;

use App\Models\MasonicHall;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MasonicHall>
 */
class MasonicHallFactory extends Factory
{
    public function definition(): array
    {
        $town = fake()->city();
        $name = $town.' Masonic Hall';

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'address_line_1' => fake()->streetAddress(),
            'town' => $town,
            'postcode' => fake()->postcode(),
            'country' => 'England',
        ];
    }
}
