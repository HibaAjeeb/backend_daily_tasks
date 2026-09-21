<?php

namespace App\Console\Commands;

use App\Services\ReminderDispatchService;
use Illuminate\Console\Command;

class DispatchDueReminders extends Command
{
    protected $signature = 'reminders:dispatch-due';

    protected $description = 'Dispatch due reminders';

    public function handle(ReminderDispatchService $service): int
    {
        $count = $service->dispatchDue();

        $this->info("Dispatched {$count} reminder(s).");

        return self::SUCCESS;
    }
}
