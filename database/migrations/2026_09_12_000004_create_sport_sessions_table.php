<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exercise_name', 150);
            $table->date('date');
            $table->time('scheduled_time')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->string('sync_status', 20)->default('synced');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date', 'is_completed']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_sessions');
    }
};
