<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\StudyProgressSnapshot;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_complete_a_study_task(): void
    {
        $registration = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina',
            'email' => 'amina@example.com',
            'password' => 'Password1',
            'password_confirmation' => 'Password1',
        ]);
        $registration->assertCreated()->assertJsonPath('success', true);

        $user = User::where('email', 'amina@example.com')->firstOrFail();
        $task = Task::create(['user_id' => $user->id, 'title' => 'Read chapter one', 'type' => 'study', 'due_date' => today(), 'is_completed' => false]);

        $this->actingAs($user, 'sanctum')->patchJson('/api/v1/tasks/'.$task->id.'/complete', ['isCompleted' => true])
            ->assertOk()->assertJsonPath('data.isCompleted', true);

        $this->assertDatabaseHas('study_progress_snapshots', ['user_id' => $user->id, 'tasks_planned' => 1, 'tasks_completed' => 1]);
    }

    public function test_authenticated_users_cannot_see_another_users_task(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $task = Task::create(['user_id' => $owner->id, 'title' => 'Private task', 'type' => 'other']);

        $this->actingAs($otherUser, 'sanctum')->getJson('/api/v1/tasks/'.$task->id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_task_index_supports_filters_and_pagination(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        Task::factory()->for($user)->study()->completed()->create([
            'category_id' => $category->id,
            'due_date' => today()->toDateString(),
        ]);
        Task::factory()->for($user)->create(['type' => 'sport']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=study')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?isCompleted=1')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?categoryId='.$category->id)
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?dueDateFrom='.today()->toDateString().'&dueDateTo='.today()->toDateString())
            ->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?pageSize=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.totalItems', 2)
            ->assertJsonPath('meta.pageSize', 1);
    }

    public function test_task_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', [
            'title' => 'New task',
            'type' => 'study',
            'dueDate' => today()->toDateString(),
            'durationMinutes' => 30,
            'priority' => 'high',
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'New task')
            ->assertJsonPath('data.durationMinutes', 30);

        $id = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/tasks/'.$id, ['title' => 'Updated', 'priority' => 'low'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.priority', 'low');

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/tasks/'.$id)->assertNoContent();
        $this->assertSoftDeleted('tasks', ['id' => $id]);
    }

    public function test_task_store_requires_title_and_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_uncompleting_a_study_task_reduces_the_completed_count(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->study()->create(['duration_minutes' => 30]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/tasks/'.$task->id.'/complete', ['isCompleted' => true])
            ->assertOk();

        $this->assertSame(1, StudyProgressSnapshot::where('user_id', $user->id)->first()->tasks_completed);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/tasks/'.$task->id.'/complete', ['isCompleted' => false])
            ->assertOk();

        $this->assertSame(0, StudyProgressSnapshot::where('user_id', $user->id)->first()->tasks_completed);
    }

    public function test_completing_a_sport_type_task_does_not_touch_study_snapshots(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['type' => 'sport', 'due_date' => today()->toDateString()]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/tasks/'.$task->id.'/complete', ['isCompleted' => true])
            ->assertOk();

        $this->assertDatabaseMissing('study_progress_snapshots', ['user_id' => $user->id]);
    }

    public function test_new_task_appears_in_all_tab_and_its_own_type_tab(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', ['title' => 'جري صباحي', 'type' => 'sport'])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')
            ->assertOk()->assertJsonFragment(['id' => $created]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=sport')
            ->assertOk()->assertJsonFragment(['id' => $created]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=study')
            ->assertOk()->assertJsonMissing(['id' => $created]);
    }

    public function test_changing_task_type_moves_it_between_tabs(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['type' => 'sport']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/tasks/'.$task->id, ['type' => 'other'])
            ->assertOk();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=sport')
            ->assertOk()->assertJsonMissing(['id' => $task->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=other')
            ->assertOk()->assertJsonFragment(['id' => $task->id]);
    }

    public function test_updating_a_task_is_reflected_on_home_and_dedicated_screens(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['type' => 'sport', 'title' => 'جري صباحي']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/tasks/'.$task->id, ['title' => 'جري صباحي — 5 كم', 'durationMinutes' => 35])
            ->assertOk()
            ->assertJsonPath('data.title', 'جري صباحي — 5 كم')
            ->assertJsonPath('data.durationMinutes', 35);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id, 'title' => 'جري صباحي — 5 كم']);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=sport')
            ->assertOk()
            ->assertJsonFragment(['id' => $task->id, 'title' => 'جري صباحي — 5 كم']);
    }

    public function test_task_show_returns_same_structure_as_list_item(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $listItem = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')
            ->assertOk()->json('data.0');

        $showItem = $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/'.$task->id)
            ->assertOk()->json('data');

        $this->assertSame($listItem['id'], $showItem['id']);
        $this->assertSame(array_keys($listItem), array_keys($showItem));
    }

    public function test_deleting_task_removes_it_from_all_tabs_and_details(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['type' => 'study']);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/tasks/'.$task->id)->assertNoContent();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks')
            ->assertOk()->assertJsonMissing(['id' => $task->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks?type=study')
            ->assertOk()->assertJsonMissing(['id' => $task->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/tasks/'.$task->id)
            ->assertNotFound()->assertJsonPath('error.code', 'TASK_NOT_FOUND');
    }
}
