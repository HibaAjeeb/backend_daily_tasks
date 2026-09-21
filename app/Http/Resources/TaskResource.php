<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type,
            'dueDate' => $this->due_date?->toDateString(),
            'scheduledTime' => $this->scheduled_time,
            'durationMinutes' => $this->duration_minutes,
            'priority' => $this->priority,
            'isCompleted' => (bool) $this->is_completed,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'isRecurring' => (bool) $this->is_recurring,
            'recurrenceRule' => $this->recurrence_rule,
            'categoryId' => $this->category_id,
            'parentPlanId' => $this->parent_plan_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
