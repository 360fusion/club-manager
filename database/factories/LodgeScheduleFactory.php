<?php

namespace Database\Factories;

use App\Models\Lodge;
use App\Models\LodgeSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LodgeSchedule>
 */
class LodgeScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lodge_id' => Lodge::factory(),
            'kind' => 'regular',
            'occurrence' => '1st',
            'day_of_week' => 'Tuesday',
            'months' => range(1, 12),
            'start_time' => '19:00',
            'source' => 'import',
        ];
    }
}
