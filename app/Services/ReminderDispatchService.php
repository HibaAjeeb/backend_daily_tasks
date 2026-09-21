<?php

namespace App\Services;

use App\Jobs\SendReminderPushJob;
use App\Models\Reminder;

class ReminderDispatchService
{
    public function dispatchDue(int $chunkSize = 200): int
    {
        $count = 0;

        Reminder::where('is_enabled', true)
            ->where('is_dispatched', false)
            ->where('scheduled_for', '<=', now())
            ->chunkById($chunkSize, function ($reminders) use (&$count): void {
                foreach ($reminders as $reminder) {
                    $reminder->update(['is_dispatched' => true, 'dispatched_at' => now()]);

                    SendReminderPushJob::dispatch($reminder)->onQueue('notifications');

                    $count++;
                }
            });

        return $count;
    }
}
