<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'SALES_MANAGER',
            'status' => 'ACTIVE',
            'is_active' => true,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => [
            'role' => 'OWNER',
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => 'ADMIN',
        ]);
    }

    public function cashier(): static
    {
        return $this->state(fn () => [
            'role' => 'CASHIER',
        ]);
    }

    public function warehouse(): static
    {
        return $this->state(fn () => [
            'role' => 'WAREHOUSE_MANAGER',
        ]);
    }

    public function warehouseManager(): static
    {
        return $this->warehouse();
    }

    public function salesManager(): static
    {
        return $this->state(fn () => [
            'role' => 'SALES_MANAGER',
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'status' => 'BLOCKED',
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
