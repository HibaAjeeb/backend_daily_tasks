<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('task_id');
            $table->timestamp('scheduled_for');
            $table->string('message', 255);
            $table->enum('repeat', ['none', 'daily', 'weekly'])->default('none');
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_dispatched')->default(false);
            $table->timestamp('dispatched_at')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('tasks')->cascadeOnDelete();
            $table->index(['is_enabled', 'is_dispatched', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
