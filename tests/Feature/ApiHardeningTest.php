<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_arabic_text_is_not_unicode_escaped_in_responses(): void
    {
        $user = User::factory()->create(['name' => 'أحمد']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

        $response->assertOk()->assertJsonPath('data.name', 'أحمد');
        $this->assertStringContainsString('أحمد', $response->getContent());
        $this->assertStringNotContainsString('\u0623', $response->getContent());
    }

    public function test_arabic_task_fields_are_stored_and_returned_readable(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/tasks', [
            'title' => 'مراجعة الفصل الثالث',
            'description' => 'الفصل الرابع',
            'type' => 'study',
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'مراجعة الفصل الثالث');
        $this->assertStringContainsString('مراجعة الفصل الثالث', $response->getContent());
        $this->assertDatabaseHas('tasks', ['title' => 'مراجعة الفصل الثالث']);
    }

    public function test_validation_details_are_returned_in_arabic(): void
    {
        $response = $this->withHeaders(['Accept-Language' => 'ar'])
            ->postJson('/api/v1/auth/register', [
                'name' => 'أحمد',
                'email' => 'not-an-email',
                'password' => 'weak',
            ]);

        $response->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $details = collect($response->json('error.details'))->keyBy('field');

        $this->assertStringContainsString('البريد الإلكتروني', $details['email']['issue']);
        $this->assertStringContainsString('كلمة المرور', $details['password']['issue']);
    }

    public function test_malformed_json_body_returns_400_invalid_json(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/auth/register',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{"name": "broken"',
        );

        $response->assertStatus(400)->assertJsonPath('error.code', 'INVALID_JSON');
    }

    public function test_non_uuid_category_id_returns_documented_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task',
                'type' => 'study',
                'categoryId' => 'cat_01',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'CATEGORY_NOT_FOUND');
    }

    public function test_non_uuid_parent_plan_id_returns_documented_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/tasks', [
                'title' => 'Task',
                'type' => 'study',
                'parentPlanId' => 'plan_05',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'STUDY_PLAN_NOT_FOUND');
    }

    public function test_non_uuid_reminder_task_id_returns_documented_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reminders', [
                'taskId' => 'tsk_101',
                'scheduledFor' => now()->addHour()->toIso8601String(),
                'message' => 'Reminder',
            ])
            ->assertNotFound()
            ->assertJsonPath('error.code', 'TASK_NOT_FOUND');
    }

    public function test_category_can_be_partially_updated_without_name(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create(['name' => 'Study', 'color_value' => '#000000']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/categories/'.$category->id, ['colorValue' => '#FF9800'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Study')
            ->assertJsonPath('data.colorValue', '#FF9800');
    }

    public function test_partial_category_update_still_rejects_duplicate_name(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Study']);
        $category = Category::factory()->for($user)->create(['name' => 'Sport']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/categories/'.$category->id, ['name' => 'Study'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_CATEGORY');
    }

    public function test_existing_task_can_still_be_updated_without_revalidating_ids(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Old']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/tasks/'.$task->id, ['title' => 'مراجعة محدثة'])
            ->assertOk()
            ->assertJsonPath('data.title', 'مراجعة محدثة');
    }
}
