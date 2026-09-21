<?php

namespace App\Http\Requests\SportSession;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSportSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exerciseName' => ['sometimes', 'string', 'max:150'],
            'date' => ['sometimes', 'date'],
            'scheduledTime' => ['nullable', 'date_format:H:i'],
            'durationMinutes' => ['sometimes', 'integer', 'min:1'],
            'actualDurationMinutes' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $mapped = [];

        if (array_key_exists('exerciseName', $data)) {
            $mapped['exercise_name'] = $data['exerciseName'];
        }
        if (array_key_exists('date', $data)) {
            $mapped['date'] = $data['date'];
        }
        if (array_key_exists('scheduledTime', $data)) {
            $mapped['scheduled_time'] = $data['scheduledTime'];
        }
        if (array_key_exists('durationMinutes', $data)) {
            $mapped['duration_minutes'] = $data['durationMinutes'];
        }
        if (array_key_exists('actualDurationMinutes', $data)) {
            $mapped['actual_duration_minutes'] = $data['actualDurationMinutes'];
        }

        return $mapped;
    }
}
