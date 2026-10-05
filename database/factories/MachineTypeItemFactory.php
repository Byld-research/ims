<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\MachineType;
use App\Models\MachineTypeItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineTypeItem>
 */
class MachineTypeItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'machine_type_id' => MachineType::factory(),
            'item_id' => Item::factory(),
            'reference' => fake()->bothify('POS-##'),
            'qty_per_machine' => fake()->numberBetween(1, 4),
            'is_consumable' => false,
        ];
    }
}
