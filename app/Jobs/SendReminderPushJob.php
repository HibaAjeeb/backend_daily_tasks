<?php

namespace App\Jobs;

use App\Models\Reminder;
use App\Notifications\TaskReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendReminderPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(public readonly Reminder $reminder) {}

    public function handle(): void
    {
        $reminder = $this->reminder;
        $user = $reminder->user;

        if (! $user) {
            return;
        }

        $user->notify(new TaskReminderNotification($reminder));

        if ($reminder->repeat !== 'none') {
            $next = $reminder->repeat === 'daily'
                ? $reminder->scheduled_for->copy()->addDay()
                : $reminder->scheduled_for->copy()->addWeek();

            $reminder->update([
                'scheduled_for' => $next,
                'is_dispatched' => false,
            ]);
        }
    }
}
