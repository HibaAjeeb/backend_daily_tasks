<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_can_be_listed_for_a_task(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        Reminder::factory()->for($user)->create(['task_id' => $task->id, 'message' => 'Ping']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/tasks/'.$task->id.'/reminders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Ping');
    }

    public function test_reminder_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/reminders', [
            'taskId' => $task->id,
            'scheduledFor' => now()->addHour()->toIso8601String(),
            'message' => 'Reminder',
            'repeat' => 'daily',
        ])
            ->assertCreated()
            ->assertJsonPath('data.repeat', 'daily')
            ->assertJsonPath('data.taskId', $task->id);

        $id = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/reminders/'.$id, ['isEnabled' => false])
            ->assertOk()
            ->assertJsonPath('data.isEnabled', false);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/reminders/'.$id)->assertNoContent();
        $this->assertSoftDeleted('reminders', ['id' => $id]);
    }

    public function test_reminder_of_another_user_is_not_accessible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $reminder = Reminder::factory()->for($owner)->create();

        $this->actingAs($intruder, 'sanctum')
            ->patchJson('/api/v1/reminders/'.$reminder->id, ['isEnabled' => false])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
