<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->enum('type', ['study', 'sport', 'other']);
            $table->date('due_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_rule', 100)->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('parent_plan_id')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->foreign('parent_plan_id')->references('id')->on('study_plans')->nullOnDelete();

            $table->index(['user_id', 'type', 'is_completed']);
            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'parent_plan_id']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
