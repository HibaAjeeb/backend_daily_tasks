<?php

namespace App\Policies;

use App\Models\StudyPlan;
use App\Models\User;

class StudyPlanPolicy
{
    public function view(User $user, StudyPlan $studyPlan): bool
    {
        return $studyPlan->user_id === $user->id;
    }

    public function update(User $user, StudyPlan $studyPlan): bool
    {
        return $this->view($user, $studyPlan);
    }

    public function delete(User $user, StudyPlan $studyPlan): bool
    {
        return $this->view($user, $studyPlan);
    }
}
