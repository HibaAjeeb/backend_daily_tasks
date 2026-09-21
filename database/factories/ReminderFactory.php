<?php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'task_id' => Task::factory(),
            'scheduled_for' => now()->addHour(),
            'message' => fake()->sentence(4),
            'repeat' => 'none',
            'is_enabled' => true,
            'is_dispatched' => false,
        ];
    }
}
