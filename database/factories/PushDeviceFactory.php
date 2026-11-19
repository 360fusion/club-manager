<?php

namespace Database\Factories;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushDevice>
 */
class PushDeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'platform' => 'ios',
            'token' => bin2hex(random_bytes(32)),
            'last_seen_at' => now(),
        ];
    }

    public function android(): static
    {
        return $this->state(['platform' => 'android']);
    }
}
