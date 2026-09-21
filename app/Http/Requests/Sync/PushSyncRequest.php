<?php

namespace App\Http\Requests\Sync;

use Illuminate\Foundation\Http\FormRequest;

class PushSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'string', 'max:100'],
            'changes' => ['required', 'array'],
            'changes.*.entity' => ['required', 'string'],
            'changes.*.operation' => ['required', 'in:create,update,delete'],
            'changes.*.id' => ['required', 'string'],
            'changes.*.data' => ['array'],
            'changes.*.updatedAt' => ['required', 'date'],
        ];
    }
}
