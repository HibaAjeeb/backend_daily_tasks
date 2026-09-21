<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        return $reminder->user_id === $user->id;
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $this->view($user, $reminder);
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $this->view($user, $reminder);
    }
}
