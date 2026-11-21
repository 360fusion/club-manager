<?php

namespace Database\Factories;

use App\Models\PageTiming;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageTiming>
 */
class PageTimingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page' => 'Members/Dashboard',
            'kind' => PageTiming::VISIT,
            'ms' => fake()->numberBetween(80, 900),
            'server_ms' => fake()->numberBetween(50, 400),
            'cached' => false,
            'device' => 'phone',
            'installed' => false,
        ];
    }
}
