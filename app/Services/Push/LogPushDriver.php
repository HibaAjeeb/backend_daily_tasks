<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogPushDriver implements PushDriver
{
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        Log::info('Push notification (log driver)', [
            'token' => Str::limit($token, 12, '...'),
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return true;
    }
}
