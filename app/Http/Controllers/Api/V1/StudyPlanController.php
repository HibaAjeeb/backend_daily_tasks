<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudyPlan\StoreStudyPlanRequest;
use App\Http\Requests\StudyPlan\UpdateStudyPlanRequest;
use App\Http\Resources\StudyPlanResource;
use App\Models\StudyPlan;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudyPlanController extends Controller
{
    use ApiResponse, AuthorizesRequests;

    public function index(Request $request)
    {
        $plans = StudyPlan::query()
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query->where('is_completed', true),
            ])
            ->latest()
            ->paginate(min((int) $request->input('pageSize', 20), 100));

        return $this->paginated(StudyPlanResource::collection($plans));
    }

    public function store(StoreStudyPlanRequest $request)
    {
        $data = $request->validated();

        $this->assertValidDateRange($data['start_date'], $data['end_date']);

        $plan = StudyPlan::create($data);

        return $this->success(new StudyPlanResource($plan), 201);
    }

    public function show(StudyPlan $studyPlan)
    {
        $this->authorize('view', $studyPlan);

        $studyPlan->load('tasks')->loadCount([
            'tasks',
            'tasks as completed_tasks_count' => fn ($query) => $query->where('is_completed', true),
        ]);

        return $this->success(new StudyPlanResource($studyPlan));
    }

    public function update(UpdateStudyPlanRequest $request, StudyPlan $studyPlan)
    {
        $this->authorize('update', $studyPlan);

        $data = $request->validated();

        $startDate = $data['start_date'] ?? $studyPlan->start_date?->toDateString();
        $endDate = $data['end_date'] ?? $studyPlan->end_date?->toDateString();

        $this->assertValidDateRange($startDate, $endDate);

        $studyPlan->update($data);

        return $this->success(new StudyPlanResource($studyPlan->fresh()));
    }

    public function destroy(Request $request, StudyPlan $studyPlan)
    {
        $this->authorize('delete', $studyPlan);

        $cascade = $request->boolean('cascade');

        DB::transaction(function () use ($studyPlan, $cascade): void {
            if ($cascade) {
                $studyPlan->tasks()->delete();
            } else {
                $studyPlan->tasks()->update(['parent_plan_id' => null]);
            }

            $studyPlan->delete();
        });

        return response()->json(null, 204);
    }

    public function progress(Request $request, StudyPlan $studyPlan)
    {
        $this->authorize('view', $studyPlan);

        $request->validate(['range' => ['nullable', 'in:week,month']]);
        $range = $request->input('range', 'week');

        [$from, $to] = $range === 'month'
            ? [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]
            : [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()];

        $points = $studyPlan->tasks()
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->selectRaw('due_date, COUNT(*) as tasks_planned, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as tasks_completed')
            ->groupBy('due_date')
            ->orderBy('due_date')
            ->get()
            ->map(fn ($row) => [
                'date' => Carbon::parse((string) $row->getAttribute('due_date'))->toDateString(),
                'tasksCompleted' => (int) $row->tasks_completed,
                'tasksPlanned' => (int) $row->tasks_planned,
            ]);

        return $this->success([
            'planId' => $studyPlan->id,
            'range' => $range,
            'points' => $points,
        ]);
    }

    private function assertValidDateRange(?string $startDate, ?string $endDate): void
    {
        if ($startDate && $endDate && $endDate < $startDate) {
            throw new ApiException('INVALID_DATE_RANGE', 'The end date must be after the start date.', 422);
        }
    }
}
