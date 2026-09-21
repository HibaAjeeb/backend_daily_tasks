<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SportSession;
use App\Models\StudyPlan;
use App\Models\StudyProgressSnapshot;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_a_study_task_aggregates_study_minutes(): void
    {
        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Study',
            'type' => 'study',
            'due_date' => today()->toDateString(),
            'duration_minutes' => 45,
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/tasks/'.$task->id.'/complete', ['isCompleted' => true])
            ->assertOk();

        $this->assertDatabaseHas('study_progress_snapshots', [
            'user_id' => $user->id,
            'tasks_completed' => 1,
            'study_minutes' => 45,
        ]);
    }

    public function test_progress_supports_weekly_monthly_and_custom_ranges(): void
    {
        $user = User::factory()->create();

        StudyProgressSnapshot::create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'tasks_planned' => 4,
            'tasks_completed' => 3,
            'study_minutes' => 90,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/weekly?weekOffset=0')
            ->assertOk()
            ->assertJsonPath('data.summary.totalStudyMinutes', 90)
            ->assertJsonPath('data.summary.completionRate', 75.0);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/monthly?month='.now()->format('Y-m'))
            ->assertOk()
            ->assertJsonPath('data.summary.totalStudyMinutes', 90);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/range?from='.now()->startOfWeek()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.totalStudyMinutes', 90);
    }

    public function test_study_plan_exposes_progress_fields(): void
    {
        $user = User::factory()->create();
        $plan = StudyPlan::create(['user_id' => $user->id, 'subject_name' => 'Math']);

        Task::create(['user_id' => $user->id, 'title' => 'A', 'type' => 'study', 'parent_plan_id' => $plan->id, 'is_completed' => true]);
        Task::create(['user_id' => $user->id, 'title' => 'B', 'type' => 'study', 'parent_plan_id' => $plan->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/study-plans/'.$plan->id)
            ->assertOk()
            ->assertJsonPath('data.totalTasks', 2)
            ->assertJsonPath('data.completedTasks', 1)
            ->assertJsonPath('data.progressPercentage', 50.0);
    }

    public function test_study_plan_progress_points_are_grouped_by_due_date(): void
    {
        $user = User::factory()->create();
        $plan = StudyPlan::create(['user_id' => $user->id, 'subject_name' => 'Math']);

        Task::create(['user_id' => $user->id, 'title' => 'A', 'type' => 'study', 'parent_plan_id' => $plan->id, 'due_date' => today()->toDateString(), 'is_completed' => true]);
        Task::create(['user_id' => $user->id, 'title' => 'B', 'type' => 'study', 'parent_plan_id' => $plan->id, 'due_date' => today()->toDateString()]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/study-plans/'.$plan->id.'/progress?range=week')
            ->assertOk()
            ->assertJsonPath('data.points.0.tasksPlanned', 2)
            ->assertJsonPath('data.points.0.tasksCompleted', 1);
    }

    public function test_deleting_a_study_plan_detaches_tasks_by_default_and_cascades_when_asked(): void
    {
        $user = User::factory()->create();

        $plan = StudyPlan::create(['user_id' => $user->id, 'subject_name' => 'Math']);
        $task = Task::create(['user_id' => $user->id, 'title' => 'A', 'type' => 'study', 'parent_plan_id' => $plan->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/study-plans/'.$plan->id)->assertNoContent();
        $this->assertNull($task->fresh()->parent_plan_id);

        $planWithCascade = StudyPlan::create(['user_id' => $user->id, 'subject_name' => 'Physics']);
        $cascadedTask = Task::create(['user_id' => $user->id, 'title' => 'B', 'type' => 'study', 'parent_plan_id' => $planWithCascade->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/study-plans/'.$planWithCascade->id.'?cascade=true')->assertNoContent();
        $this->assertSoftDeleted('tasks', ['id' => $cascadedTask->id]);
    }

    public function test_study_plan_rejects_invalid_date_range(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/study-plans', [
                'subjectName' => 'Math',
                'startDate' => '2026-09-10',
                'endDate' => '2026-09-01',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_DATE_RANGE');
    }

    public function test_sport_sessions_can_be_filtered(): void
    {
        $user = User::factory()->create();

        SportSession::create(['user_id' => $user->id, 'exercise_name' => 'Run', 'date' => today()->toDateString(), 'duration_minutes' => 30, 'is_completed' => true]);
        SportSession::create(['user_id' => $user->id, 'exercise_name' => 'Swim', 'date' => today()->addDay()->toDateString(), 'duration_minutes' => 45]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sport-sessions?isCompleted=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.exerciseName', 'Run');
    }

    public function test_task_rejects_invalid_recurrence_rule(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task',
                'type' => 'other',
                'isRecurring' => true,
                'recurrenceRule' => 'not-a-rule',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_RECURRENCE_RULE');
    }

    public function test_authenticated_routes_are_rate_limited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '100');
    }

    public function test_error_messages_follow_accept_language(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(['Accept-Language' => 'ar'])
            ->getJson('/api/v1/tasks/'.Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('error.message', 'المهمة المطلوبة غير موجودة');
    }

    public function test_registering_a_duplicate_email_returns_conflict(): void
    {
        User::factory()->create(['email' => 'amina@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'password' => 'StrongPass123',
        ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EMAIL_ALREADY_EXISTS');
    }

    public function test_updating_a_category_to_a_duplicate_name_returns_conflict(): void
    {
        $user = User::factory()->create();
        Category::create(['user_id' => $user->id, 'name' => 'Study']);
        $category = Category::create(['user_id' => $user->id, 'name' => 'Sport']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/categories/'.$category->id, ['name' => 'Study'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_CATEGORY');
    }
}
