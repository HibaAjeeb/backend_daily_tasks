<?php

namespace App\Http\Requests\Reminder;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scheduledFor' => ['sometimes', 'date'],
            'message' => ['sometimes', 'string', 'max:255'],
            'repeat' => ['sometimes', 'in:none,daily,weekly'],
            'isEnabled' => ['sometimes', 'boolean'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $mapped = [];

        if (array_key_exists('scheduledFor', $data)) {
            $mapped['scheduled_for'] = $data['scheduledFor'];
        }
        if (array_key_exists('message', $data)) {
            $mapped['message'] = $data['message'];
        }
        if (array_key_exists('repeat', $data)) {
            $mapped['repeat'] = $data['repeat'];
        }
        if (array_key_exists('isEnabled', $data)) {
            $mapped['is_enabled'] = $data['isEnabled'];
        }

        return $mapped;
    }
}
