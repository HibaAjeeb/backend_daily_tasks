<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Category;
use App\Models\Device;
use App\Models\Reminder;
use App\Models\SportSession;
use App\Models\StudyPlan;
use App\Models\SyncConflict;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SyncService
{
    private const ENTITY_MODELS = [
        'task' => Task::class,
        'study_plan' => StudyPlan::class,
        'sport_session' => SportSession::class,
        'category' => Category::class,
        'reminder' => Reminder::class,
    ];

    public function push(int $userId, string $deviceId, array $changes): array
    {
        $accepted = [];
        $conflicts = [];

        DB::transaction(function () use ($userId, $deviceId, $changes, &$accepted, &$conflicts): void {
            foreach ($changes as $change) {
                $modelClass = self::ENTITY_MODELS[$change['entity']] ?? null;

                if (! $modelClass) {
                    throw new ApiException('INVALID_ENTITY_TYPE', 'The entity type is not supported.', 400);
                }

                $incomingUpdatedAt = Carbon::parse($change['updatedAt']);
                $clientData = $change['data'] ?? [];

                $record = $modelClass::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('id', $change['id'])
                    ->where('user_id', $userId)
                    ->first();

                if ($record && $record->updated_at?->gt($incomingUpdatedAt)) {
                    $this->recordConflict($userId, $deviceId, $change, $clientData, $this->payload($change['entity'], $record));

                    $conflicts[] = [
                        'id' => $change['id'],
                        'entity' => $change['entity'],
                        'reason' => 'SERVER_VERSION_NEWER',
                        'serverData' => $this->payload($change['entity'], $record),
                    ];

                    continue;
                }

                if ($change['operation'] === 'delete') {
                    $record?->delete();
                    $accepted[] = $change['id'];

                    continue;
                }

                $data = $this->fillableData($modelClass, $this->normalizeData($clientData));

                if ($record) {
                    if ($record->trashed()) {
                        $record->deleted_at = null;
                    }

                    $record->fill($data);
                    $record->updated_at = $incomingUpdatedAt;
                    $record->save();

                    $accepted[] = $change['id'];

                    continue;
                }

                $ownedByAnotherUser = $modelClass::withoutGlobalScopes()
                    ->withTrashed()
                    ->where('id', $change['id'])
                    ->exists();

                if ($ownedByAnotherUser) {
                    $this->recordConflict($userId, $deviceId, $change, $clientData, []);

                    $conflicts[] = [
                        'id' => $change['id'],
                        'entity' => $change['entity'],
                        'reason' => 'ID_OWNED_BY_ANOTHER_USER',
                    ];

                    continue;
                }

                $newRecord = new $modelClass;
                $newRecord->fill($data);
                $newRecord->id = $change['id'];
                $newRecord->user_id = $userId;
                $newRecord->updated_at = $incomingUpdatedAt;
                $newRecord->save();

                $accepted[] = $change['id'];
            }
        });

        Device::updateOrCreate(
            ['user_id' => $userId, 'device_id' => $deviceId],
            ['last_synced_at' => now()],
        );

        return [
            'accepted' => $accepted,
            'conflicts' => $conflicts,
            'syncedAt' => now()->toIso8601String(),
        ];
    }

    public function pull(int $userId, ?string $since, int $pageSize = 200, ?string $deviceId = null, ?string $sinceId = null): array
    {
        $pageSize = max(1, min($pageSize, 500));
        $sinceDate = $since ? Carbon::parse($since)->utc()->format('Y-m-d H:i:s') : null;
        $changes = [];

        foreach (self::ENTITY_MODELS as $entity => $modelClass) {
            $records = $modelClass::withoutGlobalScopes()
                ->withTrashed()
                ->where('user_id', $userId)
                ->when($sinceDate, function ($query) use ($sinceDate, $sinceId) {
                    $query->where(function ($group) use ($sinceDate, $sinceId) {
                        $group->where('updated_at', '>', $sinceDate);

                        if ($sinceId !== null && $sinceId !== '') {
                            $group->orWhere(function ($tie) use ($sinceDate, $sinceId) {
                                $tie->where('updated_at', '=', $sinceDate)->where('id', '>', $sinceId);
                            });
                        }
                    });
                })
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit($pageSize + 1)
                ->get();

            foreach ($records as $record) {
                $changes[] = [
                    'entity' => $entity,
                    'operation' => $record->trashed() ? 'delete' : 'update',
                    'id' => $record->id,
                    'data' => $this->payload($entity, $record),
                    'updatedAt' => $record->updated_at->toIso8601String(),
                ];
            }
        }

        usort($changes, fn (array $a, array $b) => [$a['updatedAt'], $a['id']] <=> [$b['updatedAt'], $b['id']]);

        $hasMore = count($changes) > $pageSize;
        $changes = array_slice($changes, 0, $pageSize);

        $last = $changes !== [] ? $changes[count($changes) - 1] : null;

        $syncedAt = $last['updatedAt'] ?? ($since ?: now()->toIso8601String());
        $syncedAtId = $last['id'] ?? $sinceId;

        if ($deviceId !== null && $deviceId !== '') {
            Device::updateOrCreate(
                ['user_id' => $userId, 'device_id' => $deviceId],
                ['last_synced_at' => now()],
            );
        }

        return [
            'changes' => $changes,
            'syncedAt' => $syncedAt,
            'syncedAtId' => $syncedAtId,
            'hasMore' => $hasMore,
        ];
    }

    public function status(int $userId, string $deviceId): array
    {
        $device = Device::where('user_id', $userId)->where('device_id', $deviceId)->first();

        return [
            'lastSyncedAt' => $device?->last_synced_at?->toIso8601String(),
            'pendingConflicts' => SyncConflict::where('user_id', $userId)
                ->where('device_id', $deviceId)
                ->where('status', 'pending')
                ->count(),
        ];
    }

    public function resolveConflict(int $userId, SyncConflict $conflict, string $resolution, array $mergedData = []): array
    {
        if ($conflict->status === 'resolved') {
            throw new ApiException('CONFLICT_ALREADY_RESOLVED', 'This conflict has already been resolved.', 409);
        }

        $modelClass = self::ENTITY_MODELS[$conflict->entity] ?? null;

        if (! $modelClass) {
            throw new ApiException('INVALID_ENTITY_TYPE', 'The entity type is not supported.', 400);
        }

        $finalData = match ($resolution) {
            'keep_client' => $this->applyConflictData($modelClass, $userId, $conflict->entity_id, $conflict->client_data ?? []),
            'merge' => $this->applyConflictData($modelClass, $userId, $conflict->entity_id, $mergedData),
            'keep_server' => $conflict->server_data ?? [],
        };

        $conflict->update([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolved_at' => now(),
        ]);

        return [
            'id' => $conflict->id,
            'status' => $conflict->status,
            'resolution' => $conflict->resolution,
            'finalData' => $finalData,
            'resolvedAt' => $conflict->resolved_at->toIso8601String(),
        ];
    }

    public function resolveAll(int $userId, string $deviceId, string $resolution): int
    {
        $conflicts = SyncConflict::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('status', 'pending')
            ->get();

        foreach ($conflicts as $conflict) {
            $this->resolveConflict($userId, $conflict, $resolution);
        }

        return $conflicts->count();
    }

    private function applyConflictData(string $modelClass, int $userId, string $entityId, array $data): array
    {
        $record = $modelClass::withoutGlobalScopes()
            ->withTrashed()
            ->where('id', $entityId)
            ->where('user_id', $userId)
            ->first();

        $attributes = $this->fillableData($modelClass, $this->normalizeData($data));

        if (! $record) {
            $record = new $modelClass;
            $record->fill($attributes);
            $record->id = $entityId;
            $record->user_id = $userId;
            $record->save();

            return $this->payload($this->entityFor($modelClass), $record);
        }

        if ($record->trashed()) {
            $record->deleted_at = null;
        }

        $record->fill($attributes);
        $record->updated_at = now();
        $record->save();

        return $this->payload($this->entityFor($modelClass), $record);
    }

    private function recordConflict(int $userId, string $deviceId, array $change, array $clientData, array $serverData): void
    {
        SyncConflict::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'device_id' => $deviceId,
            'entity' => $change['entity'],
            'entity_id' => $change['id'],
            'client_data' => $clientData,
            'server_data' => $serverData,
            'status' => 'pending',
            'detected_at' => now(),
        ]);
    }

    private function normalizeData(array $data): array
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            $normalized[Str::snake($key)] = $value;
        }

        unset($normalized['user_id'], $normalized['id']);

        return $normalized;
    }

    private function fillableData(string $modelClass, array $data): array
    {
        $model = new $modelClass;

        return array_intersect_key($data, array_flip($model->getFillable()));
    }

    private function entityFor(string $modelClass): string
    {
        return array_search($modelClass, self::ENTITY_MODELS, true) ?: 'task';
    }

    private function payload(string $entity, Model $record): array
    {
        return match ($entity) {
            'task' => [
                'id' => $record->id,
                'title' => $record->title,
                'description' => $record->description,
                'type' => $record->type,
                'dueDate' => $record->due_date?->toDateString(),
                'scheduledTime' => $record->scheduled_time,
                'durationMinutes' => $record->duration_minutes,
                'priority' => $record->priority,
                'isCompleted' => (bool) $record->is_completed,
                'completedAt' => $record->completed_at?->toIso8601String(),
                'isRecurring' => (bool) $record->is_recurring,
                'recurrenceRule' => $record->recurrence_rule,
                'categoryId' => $record->category_id,
                'parentPlanId' => $record->parent_plan_id,
                'createdAt' => $record->created_at?->toIso8601String(),
                'updatedAt' => $record->updated_at?->toIso8601String(),
                'deletedAt' => $record->deleted_at?->toIso8601String(),
            ],
            'study_plan' => [
                'id' => $record->id,
                'subjectName' => $record->subject_name,
                'goal' => $record->goal,
                'startDate' => $record->start_date?->toDateString(),
                'endDate' => $record->end_date?->toDateString(),
                'createdAt' => $record->created_at?->toIso8601String(),
                'updatedAt' => $record->updated_at?->toIso8601String(),
                'deletedAt' => $record->deleted_at?->toIso8601String(),
            ],
            'sport_session' => [
                'id' => $record->id,
                'exerciseName' => $record->exercise_name,
                'date' => $record->date?->toDateString(),
                'scheduledTime' => $record->scheduled_time,
                'durationMinutes' => $record->duration_minutes,
                'actualDurationMinutes' => $record->actual_duration_minutes,
                'isCompleted' => (bool) $record->is_completed,
                'completedAt' => $record->completed_at?->toIso8601String(),
                'createdAt' => $record->created_at?->toIso8601String(),
                'updatedAt' => $record->updated_at?->toIso8601String(),
                'deletedAt' => $record->deleted_at?->toIso8601String(),
            ],
            'category' => [
                'id' => $record->id,
                'name' => $record->name,
                'colorValue' => $record->color_value,
                'icon' => $record->icon,
                'createdAt' => $record->created_at?->toIso8601String(),
                'updatedAt' => $record->updated_at?->toIso8601String(),
                'deletedAt' => $record->deleted_at?->toIso8601String(),
            ],
            'reminder' => [
                'id' => $record->id,
                'taskId' => $record->task_id,
                'scheduledFor' => $record->scheduled_for?->toIso8601String(),
                'message' => $record->message,
                'repeat' => $record->repeat,
                'isEnabled' => (bool) $record->is_enabled,
                'createdAt' => $record->created_at?->toIso8601String(),
                'updatedAt' => $record->updated_at?->toIso8601String(),
                'deletedAt' => $record->deleted_at?->toIso8601String(),
            ],
            default => throw new ApiException('INVALID_ENTITY_TYPE', 'The entity type is not supported.', 400),
        };
    }
}
