<?php

namespace Tests\Feature;

use App\Models\SportProgressSnapshot;
use App\Models\StudyProgressSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_sport_progress_returns_points_and_summary(): void
    {
        $user = User::factory()->create();
        SportProgressSnapshot::factory()->for($user)->create([
            'date' => today()->toDateString(),
            'sessions_planned' => 2,
            'sessions_completed' => 1,
            'sport_minutes' => 30,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/sport/weekly?weekOffset=0')
            ->assertOk()
            ->assertJsonPath('data.points.0.sessionsCompleted', 1)
            ->assertJsonPath('data.summary.completionRate', 50.0)
            ->assertJsonPath('data.summary.totalSportMinutes', 30);
    }

    public function test_study_progress_only_includes_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        StudyProgressSnapshot::factory()->for($user)->create(['study_minutes' => 10]);
        StudyProgressSnapshot::factory()->for($other)->create(['study_minutes' => 999]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/range?from='.today()->toDateString().'&to='.today()->toDateString())
            ->assertOk()
            ->assertJsonPath('data.summary.totalStudyMinutes', 10);
    }

    public function test_progress_monthly_rejects_an_invalid_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/progress/study/monthly?month=not-a-month')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }
}
