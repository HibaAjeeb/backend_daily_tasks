<?php

namespace Tests\Feature;

use App\Models\SportSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportSessionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sport_session_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/sport-sessions', [
            'exerciseName' => 'Running',
            'date' => today()->toDateString(),
            'scheduledTime' => '07:00',
            'durationMinutes' => 30,
        ])
            ->assertCreated()
            ->assertJsonPath('data.exerciseName', 'Running');

        $id = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/sport-sessions/'.$id, ['durationMinutes' => 45])
            ->assertOk()
            ->assertJsonPath('data.durationMinutes', 45);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/sport-sessions/'.$id)->assertNoContent();
        $this->assertSoftDeleted('sport_sessions', ['id' => $id]);
    }

    public function test_completing_a_sport_session_updates_the_sport_snapshot(): void
    {
        $user = User::factory()->create();
        $session = SportSession::factory()->for($user)->create(['date' => today()->toDateString()]);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/sport-sessions/'.$session->id.'/complete', [
                'isCompleted' => true,
                'actualDurationMinutes' => 25,
            ])
            ->assertOk()
            ->assertJsonPath('data.isCompleted', true)
            ->assertJsonPath('data.actualDurationMinutes', 25);

        $this->assertDatabaseHas('sport_progress_snapshots', [
            'user_id' => $user->id,
            'sessions_planned' => 1,
            'sessions_completed' => 1,
            'sport_minutes' => 25,
        ]);
    }

    public function test_sport_session_of_another_user_is_not_accessible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $session = SportSession::factory()->for($owner)->create();

        $this->actingAs($intruder, 'sanctum')
            ->patchJson('/api/v1/sport-sessions/'.$session->id.'/complete', ['isCompleted' => true])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
