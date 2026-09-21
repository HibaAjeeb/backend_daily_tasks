<?php

namespace App\Http\Requests\StudyPlan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudyPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subjectName' => ['sometimes', 'string', 'max:150'],
            'goal' => ['nullable', 'string'],
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $mapped = [];

        if (array_key_exists('subjectName', $data)) {
            $mapped['subject_name'] = $data['subjectName'];
        }
        if (array_key_exists('goal', $data)) {
            $mapped['goal'] = $data['goal'];
        }
        if (array_key_exists('startDate', $data)) {
            $mapped['start_date'] = $data['startDate'];
        }
        if (array_key_exists('endDate', $data)) {
            $mapped['end_date'] = $data['endDate'];
        }

        return $mapped;
    }
}
