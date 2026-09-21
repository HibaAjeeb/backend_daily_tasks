<?php

namespace App\Notifications;

use App\Models\Reminder;
use Illuminate\Notifications\Notification;

class TaskReminderNotification extends Notification
{
    public function __construct(public readonly Reminder $reminder) {}

    public function via(object $notifiable): array
    {
        return ['database', 'push'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'reminderId' => $this->reminder->id,
            'taskId' => $this->reminder->task_id,
            'message' => $this->reminder->message,
        ];
    }

    public function title(): string
    {
        return __('notifications.reminder');
    }

    public function body(): string
    {
        return $this->reminder->message;
    }

    public function data(): array
    {
        return [
            'reminderId' => (string) $this->reminder->id,
            'taskId' => (string) $this->reminder->task_id,
        ];
    }
}
