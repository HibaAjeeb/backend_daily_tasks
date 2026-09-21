<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:study,sport,other'],
            'dueDate' => ['nullable', 'date'],
            'scheduledTime' => ['nullable', 'date_format:H:i'],
            'durationMinutes' => ['nullable', 'integer', 'min:1'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'isRecurring' => ['sometimes', 'boolean'],
            'recurrenceRule' => ['nullable', 'string', 'max:100'],
            'categoryId' => ['nullable', 'string', 'max:64'],
            'parentPlanId' => ['nullable', 'string', 'max:64'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $mapped = [];

        if (array_key_exists('title', $data)) {
            $mapped['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $mapped['description'] = $data['description'];
        }
        if (array_key_exists('type', $data)) {
            $mapped['type'] = $data['type'];
        }
        if (array_key_exists('dueDate', $data)) {
            $mapped['due_date'] = $data['dueDate'];
        }
        if (array_key_exists('scheduledTime', $data)) {
            $mapped['scheduled_time'] = $data['scheduledTime'];
        }
        if (array_key_exists('durationMinutes', $data)) {
            $mapped['duration_minutes'] = $data['durationMinutes'];
        }
        if (array_key_exists('priority', $data)) {
            $mapped['priority'] = $data['priority'];
        }
        if (array_key_exists('isRecurring', $data)) {
            $mapped['is_recurring'] = $data['isRecurring'];
        }
        if (array_key_exists('recurrenceRule', $data)) {
            $mapped['recurrence_rule'] = $data['recurrenceRule'];
        }
        if (array_key_exists('categoryId', $data)) {
            $mapped['category_id'] = $data['categoryId'];
        }
        if (array_key_exists('parentPlanId', $data)) {
            $mapped['parent_plan_id'] = $data['parentPlanId'];
        }

        return $mapped;
    }
}
