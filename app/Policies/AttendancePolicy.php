<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAssistant();
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->isAssistant();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return false;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isAssistant();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAssistant();
    }
}