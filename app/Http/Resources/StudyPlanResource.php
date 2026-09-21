<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudyPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $total = $this->tasks_count ?? $this->tasks->count();
        $completed = $this->completed_tasks_count ?? $this->tasks->where('is_completed', true)->count();

        return [
            'id' => $this->id,
            'subjectName' => $this->subject_name,
            'goal' => $this->goal,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'totalTasks' => $total,
            'completedTasks' => $completed,
            'progressPercentage' => $total > 0 ? round($completed / $total * 100, 1) : 0,
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
