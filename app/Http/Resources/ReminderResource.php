<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'taskId' => $this->task_id,
            'scheduledFor' => $this->scheduled_for?->toIso8601String(),
            'message' => $this->message,
            'repeat' => $this->repeat,
            'isEnabled' => (bool) $this->is_enabled,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
