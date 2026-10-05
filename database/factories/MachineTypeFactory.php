<?php

namespace Database\Factories;

use App\Models\MachineType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineType>
 */
class MachineTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('TYPE_????')),
            'name' => ucfirst(fake()->words(2, true)),
            'is_active' => true,
        ];
    }
}
