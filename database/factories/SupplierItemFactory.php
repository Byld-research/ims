<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\SupplierItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierItem>
 */
class SupplierItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'item_id' => Item::factory(),
            'supplier_sku' => fake()->bothify('S-####'),
            'last_price' => fake()->randomFloat(4, 1, 500),
            'pack_size' => 1,
        ];
    }
}
