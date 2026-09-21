<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Device\RegisterDeviceRequest;
use App\Models\Device;
use App\Traits\ApiResponse;

class DeviceController extends Controller
{
    use ApiResponse;

    public function store(RegisterDeviceRequest $request)
    {
        $data = $request->validated();

        $device = Device::updateOrCreate(
            ['user_id' => $request->user()->id, 'device_id' => $data['device_id']],
            array_diff_key($data, ['device_id' => null]),
        );

        return $this->success([
            'deviceId' => $device->device_id,
            'fcmToken' => $device->fcm_token,
            'platform' => $device->platform,
            'lastSyncedAt' => $device->last_synced_at?->toIso8601String(),
        ]);
    }
}
