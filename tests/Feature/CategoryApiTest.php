<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_are_listed_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Mine']);
        Category::factory()->for($other)->create(['name' => 'Theirs']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');
    }

    public function test_category_can_be_created_updated_and_deleted(): void
    {
        $user = User::factory()->create();

        $created = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => 'Study', 'colorValue' => '#4CAF50', 'icon' => 'book'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Study');

        $id = $created->json('data.id');

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/categories/'.$id, ['name' => 'Studying'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Studying');

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/categories/'.$id)->assertNoContent();
        $this->assertSoftDeleted('categories', ['id' => $id]);
    }

    public function test_deleting_a_category_detaches_it_from_tasks(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        $task = Task::factory()->for($user)->create(['category_id' => $category->id]);

        $this->actingAs($user, 'sanctum')->deleteJson('/api/v1/categories/'.$category->id)->assertNoContent();

        $this->assertNull($task->fresh()->category_id);
    }

    public function test_duplicate_category_name_is_rejected_on_create(): void
    {
        $user = User::factory()->create();
        Category::factory()->for($user)->create(['name' => 'Study']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/categories', ['name' => 'Study'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'DUPLICATE_CATEGORY');
    }

    public function test_category_of_another_user_is_not_accessible(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $category = Category::factory()->for($owner)->create();

        $this->actingAs($intruder, 'sanctum')
            ->putJson('/api/v1/categories/'.$category->id, ['name' => 'Hijacked'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }
}
