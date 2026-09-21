<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'colorValue' => ['nullable', 'string', 'max:9'],
            'icon' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $mapped = [];

        if (array_key_exists('name', $data)) {
            $mapped['name'] = $data['name'];
        }
        if (array_key_exists('colorValue', $data)) {
            $mapped['color_value'] = $data['colorValue'];
        }
        if (array_key_exists('icon', $data)) {
            $mapped['icon'] = $data['icon'];
        }

        return $mapped;
    }
}
