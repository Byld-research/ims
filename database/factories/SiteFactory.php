<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BPC'.fake()->unique()->numerify('9##'),
            'name' => fake()->city().' Plant',
            'state' => fake()->stateAbbr(),
            'timezone' => 'America/New_York',
            'digest_hour' => 7,
            'is_active' => true,
        ];
    }
}
