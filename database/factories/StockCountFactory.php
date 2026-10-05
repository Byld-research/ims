<?php

namespace Database\Factories;

use App\Enums\StockCountStatus;
use App\Models\Site;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Raw rows for fixtures. Real counts are created through StockCountService.
 *
 * @extends Factory<StockCount>
 */
class StockCountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'reference' => 'SC-TEST-'.fake()->unique()->numerify('#####'),
            'status' => StockCountStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
