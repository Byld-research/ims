<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * A manager at a site, unless a state says otherwise.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Manager,
            'site_id' => Site::factory(),
            'is_active' => true,
            'notify_low_stock' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => Role::Admin, 'site_id' => null]);
    }

    public function manager(?Site $site = null): static
    {
        return $this->state(fn () => array_filter(['role' => Role::Manager, 'site_id' => $site?->id]));
    }

    public function operator(?Site $site = null): static
    {
        return $this->state(fn () => array_filter(['role' => Role::Operator, 'site_id' => $site?->id]));
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
