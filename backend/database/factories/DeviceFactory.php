<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use App\Services\Operations\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'device_uuid' => (string) Str::uuid(),
            'device_code' => DocumentNumberGenerator::nextDeviceCode(),
            'name' => 'Device '.$this->faker->numberBetween(1, 100),
            'device_type' => $this->faker->randomElement(['PC', 'MOBILE', 'TABLET', 'POS_TERMINAL']),
            'status' => 'ACTIVE',
            'is_active' => true,
            'registered_by' => User::factory(),
            'assigned_user_id' => User::factory(),
            'current_lease_epoch' => 1,
            'allow_new_offline_customer_debt' => false,
            'new_customer_debt_budget' => 0,
            'new_customer_debt_consumed' => 0,
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
            'last_ip' => '127.0.0.1',
            'notes' => null,
        ];
    }

    public function mobile(): static
    {
        return $this->state(fn () => [
            'device_type' => 'MOBILE',
            'name' => 'Mobile Device',
        ]);
    }

    public function pc(): static
    {
        return $this->state(fn () => [
            'device_type' => 'PC',
            'name' => 'PC Cashier',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => 'REVOKED',
            'is_active' => false,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn () => [
            'status' => 'LOST',
            'is_active' => false,
        ]);
    }
}
