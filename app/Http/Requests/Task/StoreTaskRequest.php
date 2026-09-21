<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:study,sport,other'],
            'dueDate' => ['nullable', 'date'],
            'scheduledTime' => ['nullable', 'date_format:H:i'],
            'durationMinutes' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'isRecurring' => ['nullable', 'boolean'],
            'recurrenceRule' => ['nullable', 'string', 'max:100'],
            'categoryId' => ['nullable', 'string', 'max:64'],
            'parentPlanId' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'due_date' => $data['dueDate'] ?? null,
            'scheduled_time' => $data['scheduledTime'] ?? null,
            'duration_minutes' => $data['durationMinutes'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'is_recurring' => $data['isRecurring'] ?? false,
            'recurrence_rule' => $data['recurrenceRule'] ?? null,
            'category_id' => $data['categoryId'] ?? null,
            'parent_plan_id' => $data['parentPlanId'] ?? null,
        ];
    }
}
