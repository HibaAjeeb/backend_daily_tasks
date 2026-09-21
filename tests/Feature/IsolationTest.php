<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StudyPlan;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_index_returns_only_the_authenticated_users_tasks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Task::create(['user_id' => $owner->id, 'title' => 'Mine', 'type' => 'other']);
        Task::create(['user_id' => $other->id, 'title' => 'Theirs', 'type' => 'other']);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mine');
    }

    public function test_missing_task_returns_task_not_found_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks/'.Str::uuid())
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
    }

    public function test_task_cannot_reference_another_users_category(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = Category::create(['user_id' => $owner->id, 'name' => 'Private']);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task',
                'type' => 'other',
                'categoryId' => $category->id,
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CATEGORY_NOT_FOUND');
    }

    public function test_task_cannot_reference_another_users_study_plan(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $plan = StudyPlan::create(['user_id' => $owner->id, 'subject_name' => 'Math']);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task',
                'type' => 'study',
                'parentPlanId' => $plan->id,
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'STUDY_PLAN_NOT_FOUND');
    }

    public function test_reminder_cannot_target_another_users_task(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Private', 'type' => 'other']);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/reminders', [
                'taskId' => $task->id,
                'scheduledFor' => now()->addHour()->toIso8601String(),
                'message' => 'Reminder',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
    }

    public function test_reminder_requires_a_future_scheduled_for(): void
    {
        $user = User::factory()->create();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Task', 'type' => 'other']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reminders', [
                'taskId' => $task->id,
                'scheduledFor' => now()->subHour()->toIso8601String(),
                'message' => 'Reminder',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_sync_push_cannot_overwrite_another_users_record(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Original', 'type' => 'other']);

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/sync/push', [
                'deviceId' => 'dev_test',
                'changes' => [[
                    'entity' => 'task',
                    'operation' => 'update',
                    'id' => $task->id,
                    'data' => ['title' => 'Hijacked'],
                    'updatedAt' => now()->toIso8601String(),
                ]],
            ])
            ->assertOk()
            ->assertJsonCount(0, 'data.accepted')
            ->assertJsonPath('data.conflicts.0.reason', 'ID_OWNED_BY_ANOTHER_USER');

        $this->assertSame('Original', $task->fresh()->title);
    }

    public function test_sync_push_rejects_unknown_entity_types(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync/push', [
                'deviceId' => 'dev_test',
                'changes' => [[
                    'entity' => 'unknown',
                    'operation' => 'update',
                    'id' => 'x',
                    'data' => [],
                    'updatedAt' => now()->toIso8601String(),
                ]],
            ])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_ENTITY_TYPE');
    }

    public function test_progress_range_requires_valid_dates(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/range')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_DATE_RANGE');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/range?from=2026-09-10&to=2026-09-01')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_DATE_RANGE');
    }
}
