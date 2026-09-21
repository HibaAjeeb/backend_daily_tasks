<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\ReminderController;
use App\Http\Controllers\Api\V1\SportSessionController;
use App\Http\Controllers\Api\V1\StudyPlanController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('auth/register', [AuthController::class, 'register']);
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);

    Route::middleware(['auth:sanctum', 'throttle:api-user'])->group(function (): void {
        Route::get('me', fn (Request $request) => response()->json([
            'success' => true,
            'data' => $request->user(),
            'meta' => ['timestamp' => now()->toIso8601String()],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::patch('tasks/{task}/complete', [TaskController::class, 'complete']);
        Route::apiResource('tasks', TaskController::class);

        Route::apiResource('categories', CategoryController::class)->except(['show']);

        Route::get('study-plans/{studyPlan}/progress', [StudyPlanController::class, 'progress']);
        Route::apiResource('study-plans', StudyPlanController::class);

        Route::patch('sport-sessions/{sportSession}/complete', [SportSessionController::class, 'complete']);
        Route::apiResource('sport-sessions', SportSessionController::class);

        Route::get('tasks/{task}/reminders', [ReminderController::class, 'forTask']);
        Route::post('reminders', [ReminderController::class, 'store']);
        Route::patch('reminders/{reminder}', [ReminderController::class, 'update']);
        Route::delete('reminders/{reminder}', [ReminderController::class, 'destroy']);

        Route::get('progress/study/{period}', [ProgressController::class, 'study'])
            ->whereIn('period', ['weekly', 'monthly', 'range']);
        Route::get('progress/sport/{period}', [ProgressController::class, 'sport'])
            ->whereIn('period', ['weekly', 'monthly', 'range']);

        Route::post('sync/push', [SyncController::class, 'push']);
        Route::get('sync/pull', [SyncController::class, 'pull']);
        Route::get('sync/status', [SyncController::class, 'status']);
        Route::get('sync/conflicts', [SyncController::class, 'conflicts']);
        Route::post('sync/conflicts/resolve-all', [SyncController::class, 'resolveAll']);
        Route::post('sync/conflicts/{conflict}/resolve', [SyncController::class, 'resolve']);

        Route::post('devices', [DeviceController::class, 'store']);
    });
});
