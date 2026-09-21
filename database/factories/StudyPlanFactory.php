<?php

namespace Database\Factories;

use App\Models\StudyPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyPlanFactory extends Factory
{
    protected $model = StudyPlan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_name' => fake()->word(),
            'goal' => fake()->sentence(4),
            'start_date' => today()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
        ];
    }
}
