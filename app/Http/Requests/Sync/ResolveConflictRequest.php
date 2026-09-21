<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class ResolveConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution' => ['required', 'in:keep_client,keep_server,merge'],
            'mergedData' => ['required_if:resolution,merge', 'array'],
        ];
    }
}
