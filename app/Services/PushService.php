<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Services\Push\PushDriver;

class PushService
{
    public function __construct(private readonly PushDriver $driver) {}

    public function sendToUser(User $user, string $title, string $body, array $data = []): int
    {
        $devices = Device::where('user_id', $user->id)
            ->whereNotNull('fcm_token')
            ->get();

        $sent = 0;

        foreach ($devices as $device) {
            if ($this->driver->send($device->fcm_token, $title, $body, $data)) {
                $sent++;
            }
        }

        return $sent;
    }
}
