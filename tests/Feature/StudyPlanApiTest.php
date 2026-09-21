<?php

namespace Tests\Feature;

use App\Models\StudyPlan;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudyPlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_plans_are_paginated(): void
    {
        $user = User::factory()->create();
        StudyPlan::factory()->for($user)->count(3)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/study-plans?pageSize=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.totalItems', 3)
            ->assertJsonPath('meta.totalPages', 2);
    }

    public function test_study_plan_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')->postJson('/api/v1/study-plans', [
            'subjectName' => 'Physics',
            'goal' => 'Pass',
            'startDate' => today()->toDateString(),
            'endDate' => today()->addMonth()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.subjectName', 'Physics');

        $id = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/study-plans/'.$id, ['goal' => 'Pass with distinction'])
            ->assertOk()
            ->assertJsonPath('data.goal', 'Pass with distinction');

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/study-plans/'.$id)->assertNoContent();
        $this->assertSoftDeleted('study_plans', ['id' => $id]);
    }

    public function test_study_plan_show_includes_its_tasks(): void
    {
        $user = User::factory()->create();
        $plan = StudyPlan::factory()->for($user)->create();
        Task::factory()->for($user)->study()->create(['parent_plan_id' => $plan->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/study-plans/'.$plan->id)
            ->assertOk()
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.totalTasks', 1);
    }

    public function test_study_plan_of_another_user_is_not_accessible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $plan = StudyPlan::factory()->for($owner)->create();

        $this->actingAs($intruder, 'sanctum')
            ->getJson('/api/v1/study-plans/'.$plan->id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
