<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SportSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exerciseName' => $this->exercise_name,
            'date' => $this->date?->toDateString(),
            'scheduledTime' => $this->scheduled_time,
            'durationMinutes' => $this->duration_minutes,
            'actualDurationMinutes' => $this->actual_duration_minutes,
            'isCompleted' => (bool) $this->is_completed,
            'completedAt' => $this->completed_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
