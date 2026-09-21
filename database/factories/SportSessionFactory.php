<?php

namespace Database\Factories;

use App\Models\SportSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SportSessionFactory extends Factory
{
    protected $model = SportSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exercise_name' => fake()->word(),
            'date' => today()->toDateString(),
            'scheduled_time' => '07:00',
            'duration_minutes' => 30,
            'actual_duration_minutes' => null,
            'is_completed' => false,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
            'actual_duration_minutes' => 28,
            'completed_at' => now(),
        ]);
    }
}
