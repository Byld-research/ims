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
     * Letters outside the real register (A B C D H S T W), so factories never collide with seeded types.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(str_split('EFGJKLMNPQRUVXYZ')),
            'name' => ucfirst(fake()->words(2, true)),
            'last_serial' => 0,
            'is_active' => true,
        ];
    }
}
