<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SP-#####'),
            'name' => ucfirst(fake()->words(3, true)),
            'category_id' => Category::factory(),
            'uom' => 'pc',
            'manufacturer' => fake()->company(),
            'mpn' => fake()->bothify('??-####'),
            'criticality' => fake()->randomElement(['A', 'B', 'C', null]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
