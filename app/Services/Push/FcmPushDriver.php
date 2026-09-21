<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

class FcmPushDriver implements PushDriver
{
    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        if (! class_exists(\Kreait\Firebase\Factory::class)) {
            Log::warning('FCM push skipped: kreait/firebase-php is not installed.');

            return false;
        }

        $credentials = config('services.fcm.credentials');

        if (! $credentials || ! is_file($credentials)) {
            Log::warning('FCM push skipped: credentials file is missing.');

            return false;
        }

        $messaging = (new \Kreait\Firebase\Factory)
            ->withServiceAccount($credentials)
            ->createMessaging();

        $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $token)
            ->withNotification(['title' => $title, 'body' => $body])
            ->withData(array_map('strval', $data));

        $messaging->send($message);

        return true;
    }
}
