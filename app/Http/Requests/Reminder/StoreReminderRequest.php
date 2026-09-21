<?php

namespace App\Http\Requests\Reminder;

use Illuminate\Foundation\Http\FormRequest;

class StoreReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'taskId' => ['required', 'string', 'max:64'],
            'scheduledFor' => ['required', 'date', 'after:now'],
            'message' => ['required', 'string', 'max:255'],
            'repeat' => ['nullable', 'in:none,daily,weekly'],
            'isEnabled' => ['nullable', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        return [
            'task_id' => $data['taskId'],
            'scheduled_for' => $data['scheduledFor'],
            'message' => $data['message'],
            'repeat' => $data['repeat'] ?? 'none',
            'is_enabled' => $data['isEnabled'] ?? true,
        ];
    }
}
