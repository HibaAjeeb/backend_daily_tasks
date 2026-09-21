<?php

namespace Database\Factories;

use App\Models\StudyProgressSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudyProgressSnapshotFactory extends Factory
{
    protected $model = StudyProgressSnapshot::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => today()->toDateString(),
            'tasks_planned' => 0,
            'tasks_completed' => 0,
            'study_minutes' => 0,
        ];
    }
}
