<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Category;
use App\Models\StudyPlan;
use App\Models\Task;
use App\Services\ProgressService;
use App\Traits\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController
{
    use ApiResponse, AuthorizesRequests;

    public function __construct(private readonly ProgressService $progressService) {}

    public function index(Request $request)
    {
        $tasks = Task::query()
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->has('isCompleted'), fn ($q) => $q->where('is_completed', $request->boolean('isCompleted')))
            ->when($request->categoryId, fn ($q) => $q->where('category_id', $request->categoryId))
            ->when($request->dueDateFrom, fn ($q) => $q->whereDate('due_date', '>=', $request->dueDateFrom))
            ->when($request->dueDateTo, fn ($q) => $q->whereDate('due_date', '<=', $request->dueDateTo))
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->input('pageSize', 20), 100));

        return $this->paginated(TaskResource::collection($tasks));
    }

    public function show(Task $task)
    {
        $this->authorize('view', $task);

        return $this->success(new TaskResource($task));
    }

    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();

        $this->assertCategoryExists($data['category_id'] ?? null);
        $this->assertStudyPlanExists($data['parent_plan_id'] ?? null);
        $this->assertValidRecurrenceRule($data['recurrence_rule'] ?? null);

        $task = Task::create($data);

        return $this->success(new TaskResource($task), 201);
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->authorize('update', $task);

        $data = $request->validated();

        if (array_key_exists('category_id', $data)) {
            $this->assertCategoryExists($data['category_id']);
        }

        if (array_key_exists('parent_plan_id', $data)) {
            $this->assertStudyPlanExists($data['parent_plan_id']);
        }

        if (array_key_exists('recurrence_rule', $data)) {
            $this->assertValidRecurrenceRule($data['recurrence_rule']);
        }

        $task->update($data);

        return $this->success(new TaskResource($task));
    }

    public function complete(Request $request, Task $task)
    {
        $this->authorize('update', $task);
        $request->validate(['isCompleted' => ['required', 'boolean']]);

        DB::transaction(function () use ($task, $request): void {
            $task->is_completed = $request->boolean('isCompleted');
            $task->completed_at = $task->is_completed ? now() : null;
            $task->save();

            if ($task->type === 'study') {
                $this->progressService->recordStudyTaskCompletion($task);
            }
        });

        return $this->success([
            'id' => $task->id,
            'isCompleted' => $task->is_completed,
            'completedAt' => $task->completed_at?->toIso8601String(),
        ]);
    }

    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);
        $task->delete();

        return response()->json(null, 204);
    }

    private function assertCategoryExists(?string $categoryId): void
    {
        if ($categoryId === null) {
            return;
        }

        if (! Category::whereKey($categoryId)->exists()) {
            throw new ApiException('CATEGORY_NOT_FOUND', 'The specified category was not found.', 404);
        }
    }

    private function assertStudyPlanExists(?string $planId): void
    {
        if ($planId === null) {
            return;
        }

        if (! StudyPlan::whereKey($planId)->exists()) {
            throw new ApiException('STUDY_PLAN_NOT_FOUND', 'The specified study plan was not found.', 404);
        }
    }

    private function assertValidRecurrenceRule(?string $rule): void
    {
        if ($rule === null) {
            return;
        }

        if (! preg_match('/^(daily|weekly|monthly|FREQ=(DAILY|WEEKLY|MONTHLY)(;[A-Z]+=[^;]+)*)$/i', $rule)) {
            throw new ApiException('INVALID_RECURRENCE_RULE', 'The recurrence rule is invalid.', 422);
        }
    }
}
