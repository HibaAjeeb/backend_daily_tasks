<?php

namespace App\Http\Requests\SportSession;

use Illuminate\Foundation\Http\FormRequest;

class StoreSportSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exerciseName' => ['required', 'string', 'max:150'],
            'date' => ['required', 'date'],
            'scheduledTime' => ['nullable', 'date_format:H:i'],
            'durationMinutes' => ['required', 'integer', 'min:1'],
            'actualDurationMinutes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        return [
            'exercise_name' => $data['exerciseName'],
            'date' => $data['date'],
            'scheduled_time' => $data['scheduledTime'] ?? null,
            'duration_minutes' => $data['durationMinutes'],
            'actual_duration_minutes' => $data['actualDurationMinutes'] ?? null,
        ];
    }
}
