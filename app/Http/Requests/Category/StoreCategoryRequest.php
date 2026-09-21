<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'colorValue' => ['nullable', 'string', 'max:9'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        return [
            'name' => $data['name'],
            'color_value' => $data['colorValue'] ?? null,
            'icon' => $data['icon'] ?? null,
        ];
    }
}
