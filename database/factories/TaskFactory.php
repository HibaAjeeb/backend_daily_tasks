<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => null,
            'type' => 'other',
            'due_date' => null,
            'scheduled_time' => null,
            'duration_minutes' => null,
            'priority' => 'medium',
            'is_completed' => false,
            'completed_at' => null,
            'is_recurring' => false,
            'recurrence_rule' => null,
            'category_id' => null,
            'parent_plan_id' => null,
        ];
    }

    public function study(): static
    {
        return $this->state(fn () => [
            'type' => 'study',
            'due_date' => today()->toDateString(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'is_completed' => true,
            'completed_at' => now(),
        ]);
    }
}
