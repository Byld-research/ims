<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * An assignable subcategory under a structural parent.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => Category::factory()->structural(),
            'name' => fake()->unique()->words(2, true),
            'is_structural' => false,
            'default_bin' => null,
        ];
    }

    public function structural(): static
    {
        return $this->state(fn () => ['parent_id' => null, 'is_structural' => true]);
    }
}
