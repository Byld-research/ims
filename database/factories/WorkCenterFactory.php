<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\WorkCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkCenter>
 */
class WorkCenterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'machine_type_id' => null,
            'code' => fake()->unique()->numerify('0##C'),
            'name' => ucfirst(fake()->words(2, true)),
            'is_active' => true,
        ];
    }
}
