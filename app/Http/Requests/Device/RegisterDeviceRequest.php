<?php

namespace App\Http\Requests\Device;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deviceId' => ['required', 'string', 'max:100'],
            'fcmToken' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'in:android,ios'],
        ];
    }

    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $mapped = ['device_id' => $data['deviceId']];

        if (array_key_exists('fcmToken', $data)) {
            $mapped['fcm_token'] = $data['fcmToken'];
        }
        if (array_key_exists('platform', $data)) {
            $mapped['platform'] = $data['platform'];
        }

        return $mapped;
    }
}
