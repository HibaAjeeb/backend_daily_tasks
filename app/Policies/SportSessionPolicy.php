<?php

namespace App\Policies;

use App\Models\SportSession;
use App\Models\User;

class SportSessionPolicy
{
    public function view(User $user, SportSession $sportSession): bool
    {
        return $sportSession->user_id === $user->id;
    }

    public function update(User $user, SportSession $sportSession): bool
    {
        return $this->view($user, $sportSession);
    }

    public function delete(User $user, SportSession $sportSession): bool
    {
        return $this->view($user, $sportSession);
    }
}
