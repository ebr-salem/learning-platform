<?php

namespace App\Policies;

use App\Models\StudentProfile;
use App\Models\User;

class StudentProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAssistant();
    }

    public function view(User $user, StudentProfile $profile): bool
    {
        return $user->isAssistant();
    }

    public function create(User $user): bool
    {
        return $user->isAssistant();
    }

    public function update(User $user, StudentProfile $profile): bool
    {
        return $user->isAssistant();
    }

    public function delete(User $user, StudentProfile $profile): bool
    {
        return $user->isAssistant();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAssistant();
    }
}