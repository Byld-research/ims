<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\MachineType;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    /**
     * The SKU is derived from the type, so it always matches the type letter.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'machine_type_id' => MachineType::factory(),
            'sku' => fn (array $attributes) => MachineType::query()->findOrFail($attributes['machine_type_id'])->nextSku(),
            'name' => fn (array $attributes) => MachineType::query()->findOrFail($attributes['machine_type_id'])->name,
            'revision' => '1.0',
            'site_id' => Site::factory(),
            'is_active' => true,
        ];
    }
}
