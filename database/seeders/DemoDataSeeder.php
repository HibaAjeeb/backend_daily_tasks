<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Reminder;
use App\Models\SportSession;
use App\Models\StudyPlan;
use App\Models\Task;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@example.com',
            'password' => Hash::make('Password1'),
        ]);

        $studyCategory = Category::factory()->for($user)->create([
            'name' => 'Study',
            'color_value' => '#4CAF50',
            'icon' => 'book',
        ]);

        Category::factory()->for($user)->create([
            'name' => 'Sport',
            'color_value' => '#2196F3',
            'icon' => 'run',
        ]);

        $plan = StudyPlan::factory()->for($user)->create([
            'subject_name' => 'Mathematics',
            'goal' => 'Finish the curriculum before the exam',
            'start_date' => today()->subWeek()->toDateString(),
            'end_date' => today()->addMonth()->toDateString(),
        ]);

        $progress = app(ProgressService::class);

        foreach (range(6, 0) as $daysAgo) {
            $date = today()->subDays($daysAgo);

            $tasks = Task::factory()->for($user)->study()->count(2)->create([
                'parent_plan_id' => $plan->id,
                'category_id' => $studyCategory->id,
                'due_date' => $date->toDateString(),
                'duration_minutes' => 45,
            ]);

            foreach ($tasks as $index => $task) {
                $completed = $index === 0 || $daysAgo > 2;

                $task->update([
                    'is_completed' => $completed,
                    'completed_at' => $completed ? $date->copy()->setTime(18, 0) : null,
                ]);

                $progress->recordStudyTaskCompletion($task->fresh());
            }

            $sessionCompleted = $daysAgo % 2 === 0;

            $session = SportSession::factory()->for($user)->create([
                'exercise_name' => ['Running', 'Cycling', 'Swimming'][$daysAgo % 3],
                'date' => $date->toDateString(),
                'duration_minutes' => 30,
                'actual_duration_minutes' => $sessionCompleted ? 28 : null,
                'is_completed' => $sessionCompleted,
                'completed_at' => $sessionCompleted ? $date->copy()->setTime(7, 30) : null,
            ]);

            $progress->recordSportSessionCompletion($session->fresh());
        }

        Task::factory()->for($user)->create([
            'title' => 'Review chapter 3',
            'type' => 'study',
            'priority' => 'high',
            'due_date' => today()->addDay()->toDateString(),
            'scheduled_time' => '16:00',
            'duration_minutes' => 45,
            'category_id' => $studyCategory->id,
            'parent_plan_id' => $plan->id,
        ]);

        Task::factory()->for($user)->create([
            'title' => 'Buy groceries',
            'type' => 'other',
            'priority' => 'low',
            'due_date' => today()->addDays(2)->toDateString(),
        ]);

        $reminderTask = Task::factory()->for($user)->create([
            'title' => 'Read 10 pages',
            'type' => 'study',
            'due_date' => today()->toDateString(),
            'duration_minutes' => 20,
        ]);

        Reminder::factory()->for($user)->create([
            'task_id' => $reminderTask->id,
            'scheduled_for' => now()->addHours(2),
            'message' => 'Time to read 10 pages',
            'repeat' => 'daily',
        ]);
    }
}
