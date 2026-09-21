<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Notifications\TaskReminderNotification;
use App\Services\PushService;
use Illuminate\Notifications\Notification;

class PushChannel
{
    public function __construct(private readonly PushService $push) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof TaskReminderNotification || ! $notifiable instanceof User) {
            return;
        }

        $this->push->sendToUser(
            $notifiable,
            $notification->title(),
            $notification->body(),
            $notification->data(),
        );
    }
}
