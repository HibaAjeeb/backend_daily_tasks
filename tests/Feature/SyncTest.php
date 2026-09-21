<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\SyncConflict;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SyncTest extends TestCase
{
    use RefreshDatabase;

    private function taskFor(User $user, array $attributes = []): Task
    {
        return Task::create([
            'user_id' => $user->id,
            'title' => 'Task',
            'type' => 'other',
            ...$attributes,
        ]);
    }

    private function conflictFor(User $user, Task $task, array $overrides = []): SyncConflict
    {
        return SyncConflict::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_id' => 'dev_a',
            'entity' => 'task',
            'entity_id' => $task->id,
            'client_data' => ['title' => 'Client version', 'isCompleted' => true],
            'server_data' => ['title' => 'Server version', 'isCompleted' => false],
            'status' => 'pending',
            'detected_at' => now(),
            ...$overrides,
        ]);
    }

    public function test_pull_returns_camel_case_payload(): void
    {
        $user = User::factory()->create();
        $this->taskFor($user, ['title' => 'Pull me', 'is_completed' => true]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/sync/pull')->assertOk();

        $response->assertJsonPath('data.changes.0.entity', 'task')
            ->assertJsonPath('data.changes.0.operation', 'update')
            ->assertJsonPath('data.changes.0.data.title', 'Pull me')
            ->assertJsonPath('data.changes.0.data.isCompleted', true)
            ->assertJsonPath('data.hasMore', false);

        $this->assertNotEmpty($response->json('data.syncedAtId'));
    }

    public function test_pull_paginates_without_losing_tied_timestamps(): void
    {
        $user = User::factory()->create();
        $timestamp = now()->subMinute();

        foreach (range(1, 3) as $i) {
            $task = $this->taskFor($user, ['title' => "Task $i"]);
            $task->updated_at = $timestamp;
            $task->saveQuietly();
        }

        $first = $this->actingAs($user, 'sanctum')->getJson('/api/v1/sync/pull?pageSize=2')->assertOk();
        $first->assertJsonCount(2, 'data.changes')->assertJsonPath('data.hasMore', true);

        $second = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/sync/pull?pageSize=2&since='.urlencode($first->json('data.syncedAt')).'&sinceId='.$first->json('data.syncedAtId'))
            ->assertOk();

        $second->assertJsonCount(1, 'data.changes')->assertJsonPath('data.hasMore', false);

        $ids = array_merge(
            array_column($first->json('data.changes'), 'id'),
            array_column($second->json('data.changes'), 'id'),
        );

        $this->assertCount(3, array_unique($ids));
    }

    public function test_push_applies_client_changes_and_updates_the_device(): void
    {
        $user = User::factory()->create();
        $id = (string) Str::uuid();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/sync/push', [
            'deviceId' => 'dev_a',
            'changes' => [[
                'entity' => 'task',
                'operation' => 'create',
                'id' => $id,
                'data' => ['title' => 'Synced', 'type' => 'study', 'isCompleted' => true, 'durationMinutes' => 30],
                'updatedAt' => now()->toIso8601String(),
            ]],
        ])->assertOk()->assertJsonPath('data.accepted.0', $id);

        $this->assertDatabaseHas('tasks', [
            'id' => $id,
            'user_id' => $user->id,
            'title' => 'Synced',
            'is_completed' => true,
            'duration_minutes' => 30,
        ]);

        $device = Device::where('user_id', $user->id)->where('device_id', 'dev_a')->first();
        $this->assertNotNull($device);
        $this->assertNotNull($device->last_synced_at);
    }

    public function test_push_records_a_conflict_when_the_server_version_is_newer(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user, ['title' => 'Server wins']);
        $task->updated_at = now();
        $task->saveQuietly();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/sync/push', [
            'deviceId' => 'dev_a',
            'changes' => [[
                'entity' => 'task',
                'operation' => 'update',
                'id' => $task->id,
                'data' => ['title' => 'Client loses'],
                'updatedAt' => now()->subHour()->toIso8601String(),
            ]],
        ])
            ->assertOk()
            ->assertJsonCount(0, 'data.accepted')
            ->assertJsonPath('data.conflicts.0.reason', 'SERVER_VERSION_NEWER')
            ->assertJsonPath('data.conflicts.0.serverData.title', 'Server wins');

        $this->assertSame('Server wins', $task->fresh()->title);

        $this->assertDatabaseHas('sync_conflicts', [
            'user_id' => $user->id,
            'device_id' => 'dev_a',
            'entity_id' => $task->id,
            'status' => 'pending',
        ]);
    }

    public function test_push_applies_the_client_version_when_it_is_newer(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user, ['title' => 'Old']);
        $task->updated_at = now()->subDay();
        $task->saveQuietly();

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/sync/push', [
            'deviceId' => 'dev_a',
            'changes' => [[
                'entity' => 'task',
                'operation' => 'update',
                'id' => $task->id,
                'data' => ['title' => 'New', 'isCompleted' => true],
                'updatedAt' => now()->toIso8601String(),
            ]],
        ])->assertOk()->assertJsonPath('data.accepted.0', $task->id);

        $this->assertSame('New', $task->fresh()->title);
        $this->assertTrue($task->fresh()->is_completed);
    }

    public function test_push_delete_soft_deletes_and_pull_reports_delete(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user);

        $this->actingAs($user, 'sanctum')->postJson('/api/v1/sync/push', [
            'deviceId' => 'dev_a',
            'changes' => [[
                'entity' => 'task',
                'operation' => 'delete',
                'id' => $task->id,
                'data' => [],
                'updatedAt' => now()->toIso8601String(),
            ]],
        ])->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/sync/pull')
            ->assertOk()
            ->assertJsonPath('data.changes.0.operation', 'delete');
    }

    public function test_sync_status_reports_last_sync_and_pending_conflicts(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user);
        $this->conflictFor($user, $task);

        Device::create(['user_id' => $user->id, 'device_id' => 'dev_a', 'last_synced_at' => now()]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/sync/status?deviceId=dev_a')->assertOk();

        $this->assertSame(1, $response->json('data.pendingConflicts'));
        $this->assertNotNull($response->json('data.lastSyncedAt'));
    }

    public function test_conflicts_endpoint_lists_pending_conflicts_for_the_device(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user);
        $this->conflictFor($user, $task);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/sync/conflicts?deviceId=dev_a')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity', 'task')
            ->assertJsonPath('data.0.entityId', $task->id)
            ->assertJsonPath('data.0.clientData.title', 'Client version')
            ->assertJsonPath('data.0.serverData.title', 'Server version')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('meta.totalItems', 1);
    }

    public function test_resolving_with_keep_client_applies_client_data(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user, ['title' => 'Server version']);
        $conflict = $this->conflictFor($user, $task);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync/conflicts/'.$conflict->id.'/resolve', ['resolution' => 'keep_client'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution', 'keep_client')
            ->assertJsonPath('data.finalData.title', 'Client version')
            ->assertJsonPath('data.finalData.isCompleted', true);

        $this->assertSame('Client version', $task->fresh()->title);
        $this->assertSame('resolved', $conflict->fresh()->status);
    }

    public function test_resolving_with_keep_server_keeps_server_data(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user, ['title' => 'Server version']);
        $conflict = $this->conflictFor($user, $task);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync/conflicts/'.$conflict->id.'/resolve', ['resolution' => 'keep_server'])
            ->assertOk()
            ->assertJsonPath('data.finalData.title', 'Server version');

        $this->assertSame('Server version', $task->fresh()->title);
    }

    public function test_resolving_with_merge_applies_merged_data(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user, ['title' => 'Server version']);
        $conflict = $this->conflictFor($user, $task);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync/conflicts/'.$conflict->id.'/resolve', [
                'resolution' => 'merge',
                'mergedData' => ['title' => 'Merged'],
            ])
            ->assertOk()
            ->assertJsonPath('data.finalData.title', 'Merged');

        $this->assertSame('Merged', $task->fresh()->title);
    }

    public function test_resolving_an_already_resolved_conflict_returns_conflict(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user);
        $conflict = $this->conflictFor($user, $task, [
            'status' => 'resolved',
            'resolution' => 'keep_server',
            'resolved_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync/conflicts/'.$conflict->id.'/resolve', ['resolution' => 'keep_client'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CONFLICT_ALREADY_RESOLVED');
    }

    public function test_conflict_of_another_user_is_not_accessible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conflict = $this->conflictFor($owner, $this->taskFor($owner));

        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v1/sync/conflicts/'.$conflict->id.'/resolve', ['resolution' => 'keep_client'])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CONFLICT_NOT_FOUND');
    }

    public function test_resolve_all_resolves_pending_conflicts_for_the_device(): void
    {
        $user = User::factory()->create();
        $task = $this->taskFor($user, ['title' => 'Server version']);
        $this->conflictFor($user, $task);
        $this->conflictFor($user, $task);
        $otherDeviceConflict = $this->conflictFor($user, $task, ['device_id' => 'dev_b']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/sync/conflicts/resolve-all', [
                'resolution' => 'keep_client',
                'deviceId' => 'dev_a',
            ])
            ->assertOk()
            ->assertJsonPath('data.resolvedCount', 2);

        $this->assertSame('pending', $otherDeviceConflict->fresh()->status);
    }

    public function test_device_registration_upserts_and_updates_the_fcm_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', ['deviceId' => 'dev_a', 'fcmToken' => 'token-1', 'platform' => 'android'])
            ->assertOk()
            ->assertJsonPath('data.deviceId', 'dev_a')
            ->assertJsonPath('data.fcmToken', 'token-1');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', ['deviceId' => 'dev_a', 'fcmToken' => 'token-2'])
            ->assertOk()
            ->assertJsonPath('data.fcmToken', 'token-2')
            ->assertJsonPath('data.platform', 'android');

        $this->assertDatabaseCount('devices', 1);
        $this->assertDatabaseHas('devices', [
            'user_id' => $user->id,
            'device_id' => 'dev_a',
            'fcm_token' => 'token-2',
            'platform' => 'android',
        ]);
    }
}
