<?php

namespace Database\Factories;

use App\Models\ClubType;
use App\Models\Lodge;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lodge>
 */
class LodgeFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $number = (string) fake()->unique()->numberBetween(1, 9999);

        return [
            'club_type_id' => fn () => ClubType::firstOrCreate(
                ['code' => 'craft_lodge'],
                ['name' => 'Craft Lodge', 'available_modules' => [], 'default_settings' => []],
            )->id,
            'name' => Str::title($name).' Lodge',
            'number' => $number,
            'slug' => Str::slug($name.' lodge '.$number),
            'status' => 'active',
        ];
    }
}
