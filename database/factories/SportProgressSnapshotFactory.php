<?php

namespace Database\Factories;

use App\Models\SportProgressSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SportProgressSnapshotFactory extends Factory
{
    protected $model = SportProgressSnapshot::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => today()->toDateString(),
            'sessions_planned' => 0,
            'sessions_completed' => 0,
            'sport_minutes' => 0,
        ];
    }
}
