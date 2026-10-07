<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' reports',
            'site_id' => null,
            'is_active' => true,
            'created_by' => User::factory()->admin(),
        ];
    }
}
