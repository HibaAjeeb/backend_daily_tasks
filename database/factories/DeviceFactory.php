<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_id' => 'dev_'.fake()->unique()->numerify('######'),
            'fcm_token' => fake()->sha1(),
            'platform' => 'android',
            'last_synced_at' => null,
        ];
    }
}
